<?php

namespace App\Services\Hotels;

use App\Models\Booking;
use App\Models\BusinessBookingSetting;
use App\Models\StaySettlementPayment;
use App\Models\User;
use App\Services\BookingDepositService;
use App\Services\Deposits\DepositCore;
use App\Services\Deposits\DepositOperation;
use App\Services\Guarantees\GuaranteeOperationCoverageService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «التسوية» of a stay at the non-refundable rate (owner, 2026-10-28).
 *
 * The app is never a party to the money between a guest and a hotel: it holds no payment, decides no amount and confiscates nothing.
 * What it does is keep both sides standing behind the deal until they say it is settled:
 *
 *   secure   the hotel accepts → the hotel's «عربون الجدية» % of the price is frozen from the guest AND as much from the hotel (its
 *            counter guarantee) — each side's guarantee first, its wallet for the rest (DepositCore, the same rule as every deposit)
 *   open     either side cancels → «يرجى التسوية لفك العربون» to both; nothing unwinds by itself, ever
 *   confirm  each side says «تمت التسوية» (withdrawable until the other says it too); both → everything unwinds to whoever posted it
 *   payment  the guest records what he paid outside the app — amount, method, reference and his PROOF, acknowledging it is on his own
 *            responsibility; only the hotel (the payee) says it arrived. Arrived on an open settlement → settled
 *   arbitration  three days after the cancellation with no settlement, ONE side may ask a judge: a dispute is opened on the booking and
 *            sent to arbitration; the arbitrator rules from the booking's own lines and the recorded proof, and an unpaid ruling blocks
 *            the loser's new operations (DisputeService / ArbitrationService / DisputeCollectionService)
 *
 * The state lives in the booking's meta under `_stay_settlement`.
 */
final class StaySettlement
{
    public const META_KEY = '_stay_settlement';
    public const DEFAULT_PERCENT = 20.0;
    public const ARBITRATION_AFTER_DAYS = 3;

    public const SECURED = 'secured';
    public const OPEN = 'open';
    public const SETTLED = 'settled';
    public const ARBITRATION = 'arbitration';

    public function __construct(
        private readonly DepositCore $core,
        private readonly BookingDepositService $deposits,
        private readonly NotificationDispatcherService $notifications,
    ) {
    }

    /** The hotel's «عربون الجدية» % of its non-refundable rate — its own, or the default when it never wrote one. */
    public function percentFor(int $businessId): float
    {
        $p = BusinessBookingSetting::query()->where('business_id', $businessId)->value('non_refundable_deposit_percent');

        return $p === null ? self::DEFAULT_PERCENT : (float) $p;
    }

    public function isNonRefundable(Booking $booking): bool
    {
        return data_get($booking->meta, 'rate_plan') === StayTerms::NON_REFUNDABLE;
    }

    /** @return array<string,mixed> */
    public function state(Booking $booking): array
    {
        return (array) data_get($booking->meta, self::META_KEY, []);
    }

    /** The guest's side, before a booking exists: can his guarantee and wallet stand the deposit of this price? */
    public function assertGuestCanSecure(User $guest, int $businessId, float $price): void
    {
        $amount = round($price * $this->percentFor($businessId) / 100, 2);
        $op = new DepositOperation(type: GuaranteeOperationCoverageService::OP_BOOKING, id: 0, client: $guest, business: null, context: [], source: 'StaySettlement.request');
        $readiness = $this->core->assess($op, $this->policy($amount, false), false);

        if (! $readiness['client_ready']) {
            throw ValidationException::withMessages([
                'rate_plan' => __('عربون الجدية لهذا السعر :amount ج: ضمانك ورصيدك لا يغطيانه. أضف رصيدًا أو اطلب ضمان صديق.', ['amount' => $amount]),
            ]);
        }
    }

    /** The hotel accepts a non-refundable stay: both sides' holds are frozen now. Runs inside the acceptance's transaction. */
    public function secure(Booking $booking): void
    {
        if (! $this->isNonRefundable($booking) || ($this->state($booking)['status'] ?? null) !== null) {
            return;
        }

        $booking->loadMissing(['user', 'business']);
        $percent = $this->percentFor((int) $booking->business_id);
        $amount = round((float) $booking->price * $percent / 100, 2);

        if ($amount <= 0) {
            return;
        }

        $op = $this->operation($booking);
        $policy = $this->policy($amount, true);
        $readiness = $this->core->assess($op, $policy, false);

        if (! $readiness['client_ready'] || ! $readiness['business_ready']) {
            throw ValidationException::withMessages([
                'deposit' => array_values(array_filter([
                    $readiness['client_ready'] ? null : __('ضمان العميل ورصيده لا يغطيان عربون الجدية (:amount ج) الآن.', ['amount' => $amount]),
                    $readiness['business_ready'] ? null : __('ضمانك ورصيدك لا يغطيان تأمين الفندق (:amount ج). اشحن محفظتك ثم اقبل الحجز.', ['amount' => $amount]),
                ])),
            ]);
        }

        // the wallets hold only what each guarantee leaves
        $clientWallet = round((float) $readiness['client_wallet_required'], 2);
        $businessWallet = round((float) $readiness['business_wallet_required'], 2);

        if ($clientWallet > 0 || $businessWallet > 0) {
            $policy['wallet_hold_amount'] = $clientWallet;
            $policy['hold'] = $clientWallet;
            $policy['business_counter_hold_amount'] = $businessWallet;
            $this->deposits->freezeForBooking($booking, max($clientWallet, $businessWallet), $policy);
        }

        $this->core->freezeOwnGuarantee($op, ['deposit' => $readiness]);
        $this->core->freezeBusinessGuarantee($op, ['deposit' => $readiness]);

        $this->write($booking, [
            'status' => self::SECURED,
            'percent' => $percent,
            'amount' => $amount,
            'client' => ['guarantee' => round((float) $readiness['guarantee_applied'], 2), 'wallet' => $clientWallet],
            'business' => ['guarantee' => round((float) $readiness['business_guarantee_applied'], 2), 'wallet' => $businessWallet],
            'secured_at' => now()->toDateTimeString(),
        ]);
    }

    /** Either side cancels (or the hotel rejects) a secured stay: the holds stay frozen and both are asked to settle. */
    public function open(Booking $booking, int $actorId): void
    {
        $state = $this->state($booking);

        if (($state['status'] ?? null) !== self::SECURED) {
            return;
        }

        $this->write($booking, array_merge($state, [
            'status' => self::OPEN,
            'opened_at' => now()->toDateTimeString(),
            'cancelled_by' => $actorId === (int) $booking->user_id ? 'client' : 'business',
            'client_confirmed_at' => null,
            'business_confirmed_at' => null,
        ]), true);

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $to) {
            $this->tell($booking, $to, 'booking.settlement_required',
                'يرجى التسوية لفك العربون', 'Please settle to release the deposit',
                'أُلغي الحجز بالسعر غير القابل للاسترداد. العربون المجمّد لدى الطرفين يُفك عندما تؤكدان معًا «تمت التسوية».',
                'The non-refundable booking was cancelled. The deposits held from both sides are released when you both confirm it is settled.');
        }
    }

    /** One side says «تمت التسوية»; the second confirmation unwinds every hold. */
    public function confirm(Booking $booking, int $userId, ?int $actorId = null): array
    {
        $side = $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);
        $state[$side . '_confirmed_at'] = $state[$side . '_confirmed_at'] ?? now()->toDateTimeString();
        // the person who pressed it — the owner, or the staff member acting for the hotel
        $state[$side . '_confirmed_by'] = $state[$side . '_confirmed_by'] ?? ($actorId ?: $userId);
        $this->write($booking, $state, true);

        if (! empty($state['client_confirmed_at']) && ! empty($state['business_confirmed_at'])) {
            $this->settle($booking->fresh(), 'mutual');
        } else {
            $other = $side === 'client' ? (int) $booking->business_id : (int) $booking->user_id;
            $this->tell($booking, $other, 'booking.settlement_confirmed',
                'أكّد الطرف الآخر التسوية', 'The other side confirmed the settlement',
                'أكّد الطرف الآخر «تمت التسوية». أكّد أنت أيضًا لفك العربون.',
                'The other side confirmed the settlement. Confirm too to release the deposit.');
        }

        return $this->state($booking->fresh());
    }

    /** Withdraw one's own confirmation while the other has not confirmed yet. */
    public function withdraw(Booking $booking, int $userId): array
    {
        $side = $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);
        $state[$side . '_confirmed_at'] = null;
        $state[$side . '_confirmed_by'] = null;
        $this->write($booking, $state, true);

        return $this->state($booking->fresh());
    }

    /** The guest records a payment made outside the app, with his proof and his acknowledgement. */
    public function recordPayment(Booking $booking, int $userId, array $data, UploadedFile $proof): StaySettlementPayment
    {
        if ($userId !== (int) $booking->user_id) {
            throw ValidationException::withMessages(['payment' => __('يسجّل الدفعة من دفعها، أي العميل.')]);
        }

        if (! $this->isNonRefundable($booking) || ! in_array($this->state($booking)['status'] ?? null, [self::SECURED, self::OPEN], true)) {
            throw ValidationException::withMessages(['payment' => __('لا يوجد ما يُسوّى في هذا الحجز الآن.')]);
        }

        if (StaySettlementPayment::query()->where('booking_id', $booking->id)->where('status', StaySettlementPayment::RECORDED)->exists()) {
            throw ValidationException::withMessages(['payment' => __('لديك دفعة بانتظار رد الفندق.')]);
        }

        $payment = StaySettlementPayment::create([
            'booking_id' => (int) $booking->id,
            'payer_id' => $userId,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'proof_image' => app(\App\Services\Media\ImageUploadService::class)->storePrivate($proof),
            'acknowledged_at' => now(),
            'status' => StaySettlementPayment::RECORDED,
        ]);

        $this->tell($booking, (int) $booking->business_id, 'booking.settlement_payment',
            'سجّل العميل دفعة خارج التطبيق', 'The guest recorded a payment',
            'سجّل العميل دفعة ' . $payment->amount . ' ج مع إثباتها. أكّد الاستلام أو اذكر أنها لم تصل.',
            'The guest recorded a payment of ' . $payment->amount . ' EGP with its proof. Confirm it arrived or say it did not.');

        return $payment;
    }

    /** The hotel — the payee, and only it — says the recorded payment arrived, or did not. */
    public function answerPayment(Booking $booking, StaySettlementPayment $payment, int $businessId, bool $received, ?string $note = null, ?int $actorId = null): StaySettlementPayment
    {
        if ($businessId !== (int) $booking->business_id || (int) $payment->booking_id !== (int) $booking->id) {
            throw ValidationException::withMessages(['payment' => __('هذه الدفعة ليست لحجز في نشاطك.')]);
        }

        if ($payment->status !== StaySettlementPayment::RECORDED) {
            throw ValidationException::withMessages(['payment' => __('تم الرد على هذه الدفعة من قبل.')]);
        }

        $payment->update([
            'status' => $received ? StaySettlementPayment::RECEIVED : StaySettlementPayment::REFUSED,
            'answer_note' => $note,
            'answered_at' => now(),
            'answered_by' => $actorId ?: $businessId, // the person: the owner, or the staff member acting for the hotel
        ]);

        $status = $this->state($booking)['status'] ?? null;

        if ($received && $status === self::OPEN) {
            $this->settle($booking->fresh(), 'payment_received');
        } elseif ($received && $status === self::SECURED && $booking->status === Booking::STATUS_ACCEPTED) {
            // before any cancellation it IS the stay's prepayment: the hotel has it
            app(\App\Services\ExternalDepositService::class)->confirmReceived($booking->fresh(), $businessId, $payment->method, $payment->reference);
        }

        $this->tell($booking, (int) $booking->user_id, 'booking.settlement_payment_answered',
            $received ? 'أكّد الفندق استلام دفعتك' : 'الفندق يقول إن الدفعة لم تصل',
            $received ? 'The hotel confirmed your payment' : 'The hotel says the payment did not arrive',
            $received ? 'أكّد الفندق أنه استلم الدفعة.' : 'ذكر الفندق أن الدفعة لم تصل' . ($note ? ': ' . $note : '.'),
            $received ? 'The hotel confirmed it received the payment.' : 'The hotel says the payment did not arrive' . ($note ? ': ' . $note : '.'));

        return $payment->fresh();
    }

    /**
     * «التسوية من محفظة التطبيق» — a way to settle faster, never taken by one side alone (owner, 2026-10-28: «إذا عرض العميل التسوية من
     * المحفظة أو وافق على ذلك فهو إجراء لتسريع حل النزاعات»). Either side proposes an amount up to the guest's held deposit — the guest
     * offers to pay it, or the hotel asks for it — and it is taken only when the OTHER side accepts. One proposal at a time; the
     * proposer may withdraw it until it is answered.
     */
    public function offerWallet(Booking $booking, int $userId, ?float $amount = null, ?int $actorId = null): array
    {
        $side = $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);
        $max = $this->collectable($state);
        $amount = round($amount ?? $max, 2);

        if ($amount <= 0 || $amount > $max) {
            throw ValidationException::withMessages(['amount' => __('المبلغ بين 1 و:max (عربون العميل المجمّد).', ['max' => $max])]);
        }

        if (! empty($state['wallet_offer'])) {
            throw ValidationException::withMessages(['settlement' => __('يوجد عرض تسوية من المحفظة بانتظار الرد.')]);
        }

        $state['wallet_offer'] = ['by' => $side, 'amount' => $amount, 'at' => now()->toDateTimeString(), 'by_user' => $actorId ?: $userId];
        $this->write($booking, $state, true);

        $other = $side === 'client' ? (int) $booking->business_id : (int) $booking->user_id;
        $side === 'client'
            ? $this->tell($booking, $other, 'booking.settlement_wallet_offer',
                'العميل يعرض التسوية من المحفظة', 'The guest offers to settle from the wallet',
                'يعرض العميل أن تأخذ ' . $amount . ' ج من عربونه المجمّد وتتم التسوية. وافق أو ارفض.',
                'The guest offers ' . $amount . ' EGP of the held deposit to settle. Accept or decline.')
            : $this->tell($booking, $other, 'booking.settlement_wallet_offer',
                'الفندق يطلب التسوية من المحفظة', 'The hotel asks to settle from the wallet',
                'يطلب الفندق ' . $amount . ' ج من عربونك المجمّد ليتم التسوية ويعود لك الباقي. وافق أو ارفض.',
                'The hotel asks ' . $amount . ' EGP of your held deposit to settle; the rest comes back to you. Accept or decline.');

        return $this->state($booking->fresh());
    }

    /** The proposer withdraws an unanswered proposal. */
    public function withdrawWalletOffer(Booking $booking, int $userId): array
    {
        $side = $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);

        if (($state['wallet_offer']['by'] ?? null) !== $side) {
            throw ValidationException::withMessages(['settlement' => __('لا يوجد عرض منك لسحبه.')]);
        }

        unset($state['wallet_offer']);
        $this->write($booking, $state, true);

        return $this->state($booking->fresh());
    }

    /** The OTHER side answers the proposal: accepted, the amount is taken and it is settled; declined, the proposal is gone. */
    public function answerWalletOffer(Booking $booking, int $userId, bool $accept, ?int $actorId = null): array
    {
        $side = $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);
        $offer = $state['wallet_offer'] ?? null;

        if (! $offer || $offer['by'] === $side) {
            throw ValidationException::withMessages(['settlement' => __('لا يوجد عرض من الطرف الآخر للرد عليه.')]);
        }

        if (! $accept) {
            unset($state['wallet_offer']);
            $this->write($booking, $state, true);
            $this->tell($booking, $side === 'client' ? (int) $booking->business_id : (int) $booking->user_id, 'booking.settlement_wallet_declined',
                'رُفض عرض التسوية من المحفظة', 'The wallet settlement was declined',
                'رفض الطرف الآخر عرض التسوية من المحفظة. يمكنكما الاتفاق بطريقة أخرى أو طلب التحكيم.',
                'The other side declined the wallet settlement. You may agree otherwise or ask for arbitration.');

            return $this->state($booking->fresh());
        }

        $this->collect($booking, (float) $offer['amount'], $offer['by'], $actorId ?: $userId);

        return $this->state($booking->fresh());
    }

    /** What the guest has held for the stay — the most a wallet settlement can take. */
    private function collectable(array $state): float
    {
        return round((float) data_get($state, 'client.wallet', 0) + (float) data_get($state, 'client.guarantee', 0), 2);
    }

    /**
     * Take [amount] from the guest's hold for the hotel — the guest's wallet hold first, then his guarantee (which takes his free
     * balance before the guarantee itself) — and unwind everything else. Only ever after both sides agreed (answerWalletOffer).
     */
    private function collect(Booking $booking, float $amount, string $proposedBy, ?int $acceptedBy = null): void
    {
        $state = $this->assertOpen($booking);
        $wallet = round((float) data_get($state, 'client.wallet', 0), 2);
        $amount = round($amount, 2);

        DB::transaction(function () use ($booking, $amount, $wallet, $state, $proposedBy, $acceptedBy) {
            $fromHold = round(min($amount, $wallet), 2);
            $deposit = $this->deposits->latestDeposit($booking);

            if ($deposit && ! $deposit->isFinal()) {
                $total = round((float) $deposit->client_amount + (float) $deposit->business_amount, 2);
                // the guest keeps what is left of his hold; the hotel gets its own hold back and the amount taken
                $clientPercent = $total > 0 ? round(((float) $deposit->client_amount - $fromHold) / $total * 100, 6) : 0;
                app(\App\Services\DepositsEscrowService::class)->split($deposit, $clientPercent, 100 - $clientPercent);
            }

            // every guarantee frozen for the stay goes back — the guest's is then charged for what the wallet hold did not cover
            $this->core->releaseGuarantees($this->operation($booking));
            $rest = round($amount - $fromHold, 2);

            if ($rest > 0) {
                $guest = User::query()->findOrFail((int) $booking->user_id);
                app(\App\Services\Guarantees\GuaranteePenaltyService::class)->applyPenalty(
                    user: $guest,
                    amount: $rest,
                    targetType: \App\Models\GuaranteeLevel::TARGET_CLIENT,
                    referenceType: Booking::class,
                    referenceId: (int) $booking->id,
                    reason: 'Non-refundable stay cancelled by the guest: the hotel collected its deposit',
                    meta: ['idempotency_key' => 'stay_settlement_collect_' . $booking->id]
                );
                app(\App\Services\WalletService::class)->deposit(
                    userId: (int) $booking->business_id,
                    amount: $rest,
                    note: 'عربون حجز غير قابل للاسترداد #' . $booking->id,
                    referenceType: 'stay_settlement',
                    referenceId: (string) $booking->id,
                    idempotencyKey: 'stay_settlement_collect_' . $booking->id . '_payee',
                    meta: ['booking_id' => (int) $booking->id]
                );
            }

            unset($state['wallet_offer']);
            $state['status'] = self::SETTLED;
            $state['settled_at'] = now()->toDateTimeString();
            $state['settled_by'] = 'wallet_collection';
            $state['collected'] = $amount;
            $state['collection_proposed_by'] = $proposedBy;
            $state['collection_accepted_by'] = $acceptedBy;
            $this->write($booking, $state, true);
        });

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $to) {
            $this->tell($booking, $to, 'booking.settlement_collected',
                'تمت التسوية من محفظة التطبيق', 'Settled from the app wallet',
                'باتفاق الطرفين أُخذ ' . $amount . ' ج من عربون العميل للفندق، وعاد الباقي لأصحابه وتمت التسوية.',
                'By both sides\' agreement ' . $amount . ' EGP of the guest\'s deposit went to the hotel; the rest went back and it is settled.');
        }
    }

    /**
     * The guest paid the hotel and the stay begins: the guest has the service, the hotel has its money — nothing is left to secure,
     * so both holds unwind at once (owner, 2026-10-28). Runs inside the check-in.
     */
    public function releaseOnCheckIn(Booking $booking): void
    {
        if (($this->state($booking)['status'] ?? null) !== self::SECURED) {
            return;
        }

        $deposit = $this->deposits->latestDeposit($booking);

        if ($deposit && ! $deposit->isFinal()) {
            $this->deposits->refundForBooking($booking, true, true);
        } else {
            $this->core->releaseGuarantees($this->operation($booking));
        }

        $state = $this->state($booking->fresh());
        $state['status'] = self::SETTLED;
        $state['settled_at'] = now()->toDateTimeString();
        $state['settled_by'] = 'stay_started';
        $this->write($booking, $state, true);
    }

    /** Three days after the cancellation, one side may take it to a judge. */
    public function requestArbitration(Booking $booking, int $userId): \App\Models\Dispute
    {
        $this->sideOf($booking, $userId);
        $state = $this->assertOpen($booking);

        $from = \Illuminate\Support\Carbon::parse($state['opened_at'])->addDays(self::ARBITRATION_AFTER_DAYS);

        if (now()->lt($from)) {
            throw ValidationException::withMessages([
                'arbitration' => __('يمكن طلب التحكيم بعد :days أيام من الإلغاء إن لم تتم التسوية (من :date).', ['days' => self::ARBITRATION_AFTER_DAYS, 'date' => $from->format('Y-m-d')]),
            ]);
        }

        $disputes = app(\App\Services\DisputeService::class);
        $dispute = $this->deposits->latestDeposit($booking)
            ? $this->deposits->openDisputeForBooking($booking, $userId, $userId, ['reason_code' => 'other', 'reason_text' => 'تسوية حجز بسعر غير قابل للاسترداد', 'source' => 'stay_settlement'])
            : $disputes->openForBooking($booking, $userId, $userId, ['reason_code' => 'other', 'reason_text' => 'تسوية حجز بسعر غير قابل للاسترداد', 'source' => 'stay_settlement']);
        $disputes->recordCooperation($dispute, $userId);
        $dispute = $disputes->requestArbitration($dispute->fresh(), $userId);

        $state['status'] = self::ARBITRATION;
        $state['dispute_id'] = (int) $dispute->id;
        $state['arbitration_at'] = now()->toDateTimeString();
        $this->write($booking, $state, true);

        return $dispute;
    }

    /** Every booking with a settlement still open for this account — the badge beside the notifications. */
    /** @param list<int> $employers the hotels this account works for with «الحجوزات» (staff delegation) */
    public function pendingFor(int $userId, array $employers = []): \Illuminate\Support\Collection
    {
        return Booking::query()
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhereIn('business_id', array_values(array_unique([...$employers, $userId]))))
            ->where('meta->' . self::META_KEY . '->status', self::OPEN)
            ->orderByDesc('id')->limit(50)->get();
    }

    /** @return array<string,mixed> what the app shows of a booking's settlement, for whichever side reads it */
    public function payload(Booking $booking, int $viewerId): ?array
    {
        $state = $this->state($booking);

        if ($state === []) {
            return null;
        }

        // the guest reads the client side; the hotel's owner AND its staff read the business side
        $side = $viewerId === (int) $booking->user_id ? 'client' : 'business';
        $opened = isset($state['opened_at']) ? \Illuminate\Support\Carbon::parse($state['opened_at']) : null;

        return [
            'status' => $state['status'] ?? null,
            'percent' => (float) ($state['percent'] ?? 0),
            'amount' => (float) ($state['amount'] ?? 0),
            'client_hold' => $state['client'] ?? null,
            'business_hold' => $state['business'] ?? null,
            'cancelled_by' => $state['cancelled_by'] ?? null,
            'opened_at' => $state['opened_at'] ?? null,
            'client_confirmed_at' => $state['client_confirmed_at'] ?? null,
            'business_confirmed_at' => $state['business_confirmed_at'] ?? null,
            // who pressed «تمت التسوية» for the hotel — its owner, or a staff member by name
            'business_confirmed_by' => isset($state['business_confirmed_by']) ? User::query()->whereKey((int) $state['business_confirmed_by'])->value('name') : null,
            'my_side' => $side,
            'arbitration_from' => $opened?->copy()->addDays(self::ARBITRATION_AFTER_DAYS)->toDateTimeString(),
            'can_request_arbitration' => ($state['status'] ?? null) === self::OPEN && $opened && now()->gte($opened->copy()->addDays(self::ARBITRATION_AFTER_DAYS)),
            // «التسوية من محفظة التطبيق»: one side proposes (up to the guest's whole hold), the other accepts
            'collectable' => $this->collectable($state),
            'wallet_offer' => $state['wallet_offer'] ?? null,
            'collected' => $state['collected'] ?? null,
            'settled_by' => $state['settled_by'] ?? null,
            'dispute_id' => $state['dispute_id'] ?? null,
            'settled_at' => $state['settled_at'] ?? null,
            'payments' => StaySettlementPayment::query()->where('booking_id', $booking->id)->orderBy('id')->get()
                ->map(fn (StaySettlementPayment $p) => $p->payload() + ['proof_url' => $this->proofUrl($p)])->all(),
        ];
    }

    public function proofUrl(StaySettlementPayment $payment): string
    {
        // a link that lives 6–12 hours, the same for hours so the app's image cache holds
        $window = 21600;
        $expiry = now()->setTimestamp((intdiv(now()->timestamp, $window) + 2) * $window);

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('stay-payment-proofs.show', $expiry, ['payment' => $payment->id]);
    }

    /** Everything held for the stay goes back to whoever posted it. */
    private function settle(Booking $booking, string $how): void
    {
        DB::transaction(function () use ($booking, $how) {
            $deposit = $this->deposits->latestDeposit($booking);

            if ($deposit && ! $deposit->isFinal()) {
                $this->deposits->refundForBooking($booking, true, true); // also returns the guarantees
            } else {
                $this->core->releaseGuarantees($this->operation($booking));
            }

            $state = $this->state($booking);
            $state['status'] = self::SETTLED;
            $state['settled_at'] = now()->toDateTimeString();
            $state['settled_by'] = $how;
            $this->write($booking, $state, true);
        });

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $to) {
            $this->tell($booking, $to, 'booking.settlement_done',
                'تمت التسوية وفُك العربون', 'Settled — the deposit is released',
                'تمت التسوية، وعاد كل ما جُمّد لهذا الحجز لصاحبه.',
                'The settlement is done; everything held for this booking went back to whoever posted it.');
        }
    }

    /** @return array<string,mixed> */
    private function assertOpen(Booking $booking): array
    {
        $state = $this->state($booking);

        if (($state['status'] ?? null) !== self::OPEN) {
            throw ValidationException::withMessages(['settlement' => __('لا توجد تسوية مفتوحة لهذا الحجز.')]);
        }

        return $state;
    }

    private function sideOf(Booking $booking, int $userId): string
    {
        return match ($userId) {
            (int) $booking->user_id => 'client',
            (int) $booking->business_id => 'business',
            default => throw ValidationException::withMessages(['settlement' => __('لست طرفًا في هذا الحجز.')]),
        };
    }

    /** @return array<string,mixed> a deposit policy of $amount from the guest, and as much from the hotel when $counter */
    private function policy(float $amount, bool $counter): array
    {
        return [
            'required' => true,
            'wallet_hold_required' => true,
            'wallet_hold_amount' => $amount,
            'hold' => $amount,
            'amount' => $amount,
            'business_counter_hold_raw_amount' => $counter ? $amount : 0,
            'business_counter_hold_amount' => $counter ? $amount : 0,
            'external_deposit_required' => false,
            'source' => 'non_refundable_rate',
        ];
    }

    private function operation(Booking $booking): DepositOperation
    {
        return new DepositOperation(
            type: GuaranteeOperationCoverageService::OP_BOOKING,
            id: (int) $booking->id,
            client: $booking->user,
            business: $booking->business,
            context: ['booking_id' => (int) $booking->id, 'business_id' => (int) $booking->business_id, 'client_id' => (int) $booking->user_id],
            source: 'StaySettlement',
        );
    }

    /** @param array<string,mixed> $state */
    private function write(Booking $booking, array $state, bool $replace = false): void
    {
        $booking->refresh();
        $meta = (array) ($booking->meta ?? []);
        $meta[self::META_KEY] = $replace ? $state : array_merge((array) ($meta[self::META_KEY] ?? []), $state);
        $booking->meta = $meta;
        $booking->save();
    }

    private function tell(Booking $booking, int $to, string $event, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($event, $to, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => Booking::class, 'notifiable_id' => (int) $booking->id,
                'source_type' => Booking::class, 'source_id' => (int) $booking->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
