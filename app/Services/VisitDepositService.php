<?php

namespace App\Services;

use App\Contracts\HasOwnDepositTerms;
use App\Contracts\HasVisitDeposit;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\VisitDeposit;
use App\Services\Deposits\DepositCore;
use App\Services\Deposits\DepositOperation;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «عربون الجدية» for health visits — a clinic appointment, a lab/radiology order, a hospital procedure.
 *
 * It is the SAME deposit a business sets in «شروط الحجز» (one policy row, one calculator, one escrow); any business
 * may switch it on or leave it off. What differs is only the moment each step happens:
 *
 *   quote      the visit's value × the business's terms → what is owed, as wallet hold or outside the platform
 *   afford     at the request: the patient's guarantee + free wallet balance must cover his share (nothing is frozen yet)
 *   secure     when the visit stands (clinic confirms / centre accepts / hospital accepts): freeze both shares — each
 *              side's own guarantee coverage first, its wallet for the rest (App\Services\Deposits\DepositCore) — or
 *              record the deposit as owed outside the platform
 *   release    the visit was done — every hold goes back
 *   refund     the visit did not happen (rejected / cancelled / declined) — every hold goes back
 *   dispute    after a no-show or a disagreement: either party opens it, an arbitrator rules on the held deposit
 *
 * It is never confiscated, and it never stops an account: only an opened dispute holds anyone back.
 * Care is never gated on it — a result or a visit is not withheld for a deposit still outstanding.
 */
class VisitDepositService
{
    public function __construct(
        private readonly BookingDepositPolicyResolver $resolver,
        private readonly BookingDepositCalculator $calculator,
        private readonly DepositsEscrowService $escrow,
        private readonly DisputeService $disputes,
        private readonly NotificationDispatcherService $notifications,
        private readonly DepositCore $core,
    ) {
    }

    /** The operation kind a visit is known by to the guarantee services (one per kind, so ids never collide). */
    public static function operationType(Model $subject): string
    {
        return Str::snake(class_basename($subject));
    }

    private function operationOf(Model $subject): DepositOperation
    {
        /** @var HasVisitDeposit&Model $subject */
        $clientId = $subject->visitDepositClientId();
        $businessId = $subject->visitDepositBusinessId();

        return new DepositOperation(
            type: self::operationType($subject),
            id: (int) $subject->getKey(),
            client: User::query()->find($clientId),
            business: User::query()->find($businessId),
            context: [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (int) $subject->getKey(),
                'business_id' => $businessId,
                'client_id' => $clientId,
            ],
            source: 'VisitDepositService',
        );
    }

    /**
     * The calculator's whole result for a visit of this value under the business's terms, or null when they ask nothing.
     *
     * @return array<string,mixed>|null
     */
    private function policyFor(int $businessId, float $value): ?array
    {
        if ($value <= 0) {
            return null;
        }

        $business = User::query()->find($businessId);
        $service = PlatformService::query()->where('key', 'booking')->first();

        if (! $business || ! $service) {
            return null;
        }

        $policy = $this->resolver->resolve($business, $service);

        if (! ($policy['enabled'] ?? false)) {
            return null;
        }

        $out = $this->calculator->calculate($policy, ['total_amount' => $value, 'first_day_amount' => $value]);

        return ($out['required'] ?? false) ? $out : null;
    }

    /* ---------------------------------------------------------------- quote */

    /**
     * The deposit policy this very operation is judged by: its own terms (a menu/retail order asks the whole order's
     * cover once it passes the merchant's threshold), otherwise the business's «شروط الحجز» applied to its value.
     *
     * @return array<string,mixed>|null
     */
    private function policyOf(Model $subject): ?array
    {
        if ($subject instanceof HasOwnDepositTerms) {
            return $subject->depositTerms();
        }

        if (! $subject instanceof HasVisitDeposit) {
            return null;
        }

        return $this->policyFor($subject->visitDepositBusinessId(), $subject->visitDepositValue());
    }

    /** The business's «شروط الحجز» applied to an operation of this value (null when it asks nothing). */
    public function termsFor(int $businessId, float $value): ?array
    {
        return $this->policyFor($businessId, $value);
    }

    /**
     * A policy that asks the customer's cover for the WHOLE of $amount (the carrier's own per-unit deposit, an order's
     * threshold…), and — when the business has in-app «شروط الحجز» with a counter share — that share of it from the
     * business, guarantee first.
     *
     * @return array<string,mixed>
     */
    public function wholeCoverPolicy(int $businessId, float $amount, string $source): array
    {
        $amount = round($amount, 2);
        $counter = 0.0;

        $business = User::query()->find($businessId);
        $service = PlatformService::query()->where('key', 'booking')->first();

        if ($business && $service) {
            $terms = $this->resolver->resolve($business, $service);

            if (($terms['enabled'] ?? false) && ($terms['wallet_hold_enabled'] ?? false) && ($terms['business_counter_hold_enabled'] ?? false)) {
                $counter = round($amount * max(0.0, min((float) ($terms['business_counter_hold_percent'] ?? 0), 100.0)) / 100, 2);
            }
        }

        return [
            'enabled' => true,
            'required' => true,
            'mode' => 'wallet_hold',
            'amount' => $amount,
            'hold' => $amount,
            'configured_percent' => 100,
            'wallet_hold_required' => true,
            'wallet_hold_amount' => $amount,
            'external_deposit_required' => false,
            'external_deposit_amount' => 0.0,
            'business_counter_hold_required' => $counter > 0,
            'business_counter_hold_amount' => $counter,
            'business_counter_hold_raw_amount' => $counter,
            'currency' => 'EGP',
            'source' => $source,
        ];
    }

    /**
     * At the request: refuse — with how the gap may be closed — when the customer's guarantee and wallet cannot stand
     * for what the operation asks. Nothing is frozen; it is frozen when the business confirms.
     */
    public function assertSubjectAffordable(Model $subject): void
    {
        $policy = $this->policyOf($subject);

        if (! $policy || ($policy['external_deposit_required'] ?? false)) {
            return;
        }

        $a = $this->core->assess($this->operationOf($subject), $policy, false);

        if ($a['client_ready']) {
            return;
        }

        $amount = (float) ($policy['wallet_hold_amount'] ?? 0) ?: (float) ($policy['guarantee_hold_amount'] ?? 0);

        $this->refuse(
            __('هذا النشاط يطلب عربونًا :amount ج لتأكيد الحجز، ورصيدك في المحفظة لا يكفي. اشحن محفظتك ثم أعد المحاولة.', ['amount' => $this->fmt($amount)]),
            $a['client_options'],
        );
    }

    /**
     * What the core says of this operation now — both sides' readiness, what is covered by guarantee and what is left
     * for the wallets — or null when it asks for no deposit on the platform.
     *
     * @return array<string,mixed>|null
     */
    public function assessFor(Model $subject): ?array
    {
        $policy = $this->policyOf($subject);

        if (! $policy || ($policy['external_deposit_required'] ?? false)) {
            return null;
        }

        return $this->core->assess($this->operationOf($subject), $policy, $this->rowFor($subject) !== null);
    }

    /**
     * The coverage a friend may still be asked for on this operation: what the customer's own guarantee and wallet leave
     * uncovered. Null when no deposit is asked.
     *
     * @return array{needed:float,deposit:float,combined_coverage:float}|null
     */
    public function friendNeed(Model $subject): ?array
    {
        $policy = $this->policyOf($subject);

        if (! $policy || ($policy['external_deposit_required'] ?? false)) {
            return null;
        }

        $a = $this->core->assess($this->operationOf($subject), $policy, $this->rowFor($subject) !== null);
        $deposit = (float) $a['client_deposit_required'];
        $balance = (float) ($a['client_wallet']['balance'] ?? 0);

        return [
            'deposit' => $deposit,
            'combined_coverage' => (float) $a['combined_guarantee_coverage'],
            // what neither his guarantee (with friends) nor his wallet stands for
            'needed' => round(max($deposit - (float) $a['combined_guarantee_coverage'] - $balance, 0), 2),
        ];
    }

    /**
     * What the business's terms ask for a visit of this value, or null when it asks for nothing.
     *
     * @return array{mode:string,amount:float,counter_amount:float,percent:float}|null
     */
    public function quote(int $businessId, float $value): ?array
    {
        return $this->quoteOf($this->policyFor($businessId, $value));
    }

    /**
     * @param  array<string,mixed>|null  $out  a calculator-shaped policy
     * @return array{mode:string,amount:float,counter_amount:float,percent:float}|null
     */
    private function quoteOf(?array $out): ?array
    {
        if (! $out) {
            return null;
        }

        $external = (bool) ($out['external_deposit_required'] ?? false);
        // wallet hold, or — a business that requires the guarantee — the guarantee frozen in its place
        $amount = (float) ($external ? $out['external_deposit_amount'] : ($out['wallet_hold_amount'] > 0 ? $out['wallet_hold_amount'] : ($out['guarantee_hold_amount'] ?? 0)));

        if ($amount <= 0) {
            return null;
        }

        return [
            'mode' => $external ? VisitDeposit::MODE_EXTERNAL : VisitDeposit::MODE_WALLET,
            'amount' => round($amount, 2),
            'counter_amount' => $external ? 0.0 : round((float) ($out['business_counter_hold_raw_amount'] ?? $out['business_counter_hold_amount']), 2),
            'percent' => (float) ($out['configured_percent'] ?? 0),
        ];
    }

    /** The row of this visit, if its deposit was ever secured. */
    public function rowFor(Model $subject): ?VisitDeposit
    {
        return VisitDeposit::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->first();
    }

    /**
     * The deposit as either side reads it: the secured row, or — while the visit is still ahead — what it WILL ask.
     * Null when the business asks for no deposit.
     *
     * @return array<string,mixed>|null
     */
    public function stateFor(Model $subject): ?array
    {
        $row = $this->rowFor($subject);

        if ($row) {
            return [
                'mode' => $row->mode,
                'status' => $row->status,
                'amount' => (float) $row->amount,
                'counter_amount' => (float) $row->counter_amount,
                // how much of each side's share stands on its guarantee instead of its wallet
                'guarantee_amount' => (float) data_get($row->meta, 'client_guarantee', 0),
                'business_guarantee_amount' => (float) data_get($row->meta, 'business_guarantee', 0),
                'method' => $row->method,
                'reference' => $row->reference,
                'dispute_id' => $row->dispute_id ? (int) $row->dispute_id : null,
            ];
        }

        if (! $subject instanceof HasVisitDeposit || ! $subject->visitDepositOpen()) {
            return null;
        }

        $quote = $this->quoteOf($this->policyOf($subject));

        if (! $quote) {
            return null;
        }

        return [
            'mode' => $quote['mode'],
            'status' => 'quoted',
            'amount' => $quote['amount'],
            'counter_amount' => $quote['counter_amount'],
            'method' => null,
            'reference' => null,
            'dispute_id' => null,
        ];
    }

    /* --------------------------------------------------------------- afford */

    /**
     * At the request: a wallet deposit needs the patient's free balance to cover his share. Nothing is frozen yet —
     * it is frozen when the visit stands. An external deposit needs no balance.
     */
    public function assertCanAfford(int $clientId, int $businessId, float $value): void
    {
        $policy = $this->policyFor($businessId, $value);

        if (! $policy || ($policy['external_deposit_required'] ?? false)) {
            return;
        }

        $op = new DepositOperation(
            type: 'visit',
            id: 0,
            client: User::query()->find($clientId),
            business: User::query()->find($businessId),
            source: 'VisitDepositService.assertCanAfford',
        );

        $assessment = $this->core->assess($op, $policy, false);

        if ($assessment['client_ready']) {
            return;
        }

        $this->refuse(
            __('هذا النشاط يطلب عربونًا :amount ج لتأكيد الحجز، ورصيدك في المحفظة لا يكفي. اشحن محفظتك ثم أعد المحاولة.', [
                'amount' => $this->fmt((float) $policy['wallet_hold_amount']),
            ]),
            $assessment['client_options'],
        );
    }

    /* --------------------------------------------------------------- secure */

    /**
     * The visit stands: freeze both shares, or record the deposit as owed outside. Idempotent. Null when the business
     * asks for none. A wallet short on either side refuses with a plain reason and nothing is left half-done.
     */
    public function secure(Model $subject): ?VisitDeposit
    {
        if (! $subject instanceof HasVisitDeposit) {
            return null;
        }

        if ($row = $this->rowFor($subject)) {
            return $row;
        }

        $clientId = $subject->visitDepositClientId();
        $businessId = $subject->visitDepositBusinessId();
        $policy = $this->policyOf($subject);
        $quote = $this->quoteOf($policy);

        if (! $quote || $clientId <= 0 || $clientId === $businessId) {
            return null;
        }

        if ($quote['mode'] === VisitDeposit::MODE_EXTERNAL) {
            return VisitDeposit::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'client_id' => $clientId,
                'business_id' => $businessId,
                'mode' => VisitDeposit::MODE_EXTERNAL,
                'amount' => $quote['amount'],
                'status' => VisitDeposit::STATUS_PENDING,
            ]);
        }

        $op = $this->operationOf($subject);
        $a = $this->core->assess($op, $policy, false);

        if (! $a['client_ready']) {
            $this->refuse(
                __('رصيد المريض في المحفظة لا يكفي لعربون هذا الموعد (:amount ج).', ['amount' => $this->fmt($a['client_wallet_required'])]),
                $a['client_options'],
            );
        }

        if (! $a['business_ready']) {
            $this->refuse(
                __('رصيد النشاط لا يكفي لتجميد حصته من العربون (:amount ج). اشحن محفظتك ثم أكّد.', ['amount' => $this->fmt($a['business_wallet_required'])]),
            );
        }

        // what the core's judgement means for the freeze: guarantee coverage first, the wallet for the remainder
        $readiness = ['deposit' => [
            'guarantee_applied' => $a['guarantee_applied'],
            'business_guarantee_applied' => $a['business_guarantee_applied'],
        ]];

        return DB::transaction(function () use ($subject, $clientId, $businessId, $quote, $a, $op, $readiness) {
            $deposit = null;
            $walletTotal = round($a['client_wallet_required'] + $a['business_wallet_required'], 2);

            if ($walletTotal > 0) {
                $deposit = $this->escrow->create(
                    clientId: $clientId,
                    businessId: $businessId,
                    totalAmount: $walletTotal,
                    targetType: $subject->getMorphClass(),
                    targetId: (int) $subject->getKey(),
                    clientAmount: $a['client_wallet_required'],
                    businessAmount: $a['business_wallet_required'],
                );
            }

            $clientFrozen = $this->core->freezeOwnGuarantee($op, $readiness);
            $businessFrozen = $this->core->freezeBusinessGuarantee($op, $readiness);

            return VisitDeposit::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'client_id' => $clientId,
                'business_id' => $businessId,
                'mode' => VisitDeposit::MODE_WALLET,
                'amount' => $quote['amount'],
                'counter_amount' => $quote['counter_amount'],
                'status' => VisitDeposit::STATUS_HELD,
                'deposit_id' => $deposit ? (int) $deposit->id : null,
                'meta' => ['client_guarantee' => $clientFrozen, 'business_guarantee' => $businessFrozen],
            ]);
        });
    }

    /* ------------------------------------------------------ release / refund */

    /** The visit was done: every hold goes back to whoever posted it. Safe on a visit with no deposit. */
    public function release(Model $subject): ?VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row) {
            // nothing was secured, but a friend may have frozen coverage for it: it goes back
            $this->core->releaseGuarantees($this->operationOf($subject));

            return null;
        }

        if (! $row->isHeld()) {
            return $row;
        }

        $deposit = $row->deposit_id ? Deposit::query()->find($row->deposit_id) : null;

        if ($deposit && ! $deposit->isFinal()) {
            $this->escrow->release($deposit);
        }

        $this->core->releaseGuarantees($this->operationOf($subject));

        $row->update(['status' => VisitDeposit::STATUS_RELEASED]);

        return $row->fresh();
    }

    /** The visit did not happen: every hold goes back, nothing is kept. Safe on a visit with no deposit. */
    public function refund(Model $subject): ?VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row) {
            // a rejected / cancelled request: whatever a friend froze for it goes back
            $this->core->releaseGuarantees($this->operationOf($subject));

            return null;
        }

        if ($row->mode === VisitDeposit::MODE_EXTERNAL) {
            // nothing moved on the platform; a deposit never confirmed simply lapses
            if ($row->status === VisitDeposit::STATUS_PENDING) {
                $row->update(['status' => VisitDeposit::STATUS_VOID]);
            }

            return $row->fresh();
        }

        if (! $row->isHeld()) {
            return $row;
        }

        $deposit = $row->deposit_id ? Deposit::query()->find($row->deposit_id) : null;

        if ($deposit && ! $deposit->isFinal()) {
            $this->escrow->refund($deposit, true, true);
        }

        $this->core->releaseGuarantees($this->operationOf($subject));

        $row->update(['status' => VisitDeposit::STATUS_REFUNDED]);

        return $row->fresh();
    }

    /** Best-effort wrapper for a state change that must not be undone by the money step: report and go on. */
    public function safely(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ------------------------------------------------------------- external */

    /** The business says the deposit reached it — in cash or by a transfer outside the platform. */
    public function confirmReceived(Model $subject, int $businessId, string $method, ?string $reference = null): VisitDeposit
    {
        $row = $this->pendingExternal($subject, $businessId);

        if (! in_array($method, ['cash', 'transfer'], true)) {
            throw ValidationException::withMessages(['method' => __('اختر كاش أو تحويل.')]);
        }

        $row->update(['status' => VisitDeposit::STATUS_RECEIVED, 'method' => $method, 'reference' => $reference ?: null]);

        $this->tell($row, 'booking.external_deposit_received',
            'استلم النشاط العربون', 'The deposit was received',
            'أكّد النشاط أنه استلم العربون.', 'The business confirmed it received the deposit.');

        return $row->fresh();
    }

    /** The business accepts the visit with no deposit paid outside. */
    public function waive(Model $subject, int $businessId): VisitDeposit
    {
        $row = $this->pendingExternal($subject, $businessId);

        $row->update(['status' => VisitDeposit::STATUS_WAIVED]);

        $this->tell($row, 'booking.external_deposit_waived',
            'قبل النشاط الموعد بلا عربون', 'Accepted without a deposit',
            'قبل النشاط موعدك دون دفع عربون.', 'The business accepted your visit without a deposit.');

        return $row->fresh();
    }

    private function pendingExternal(Model $subject, int $businessId): VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row || (int) $row->business_id !== $businessId) {
            throw ValidationException::withMessages(['deposit' => __('هذا الطلب ليس لنشاطك أو لا يطلب عربونًا.')]);
        }

        if ($row->mode !== VisitDeposit::MODE_EXTERNAL || $row->status !== VisitDeposit::STATUS_PENDING) {
            throw ValidationException::withMessages(['deposit' => __('لا ينطبق ذلك على هذا العربون الآن.')]);
        }

        return $row;
    }

    /* -------------------------------------------------- give back / dispute */

    /**
     * After a no-show the business may give the held deposit back at once (a refund of both holds) — the same
     * outcome both parties could agree on, without an arbitrator.
     */
    public function giveBack(Model $subject, int $businessId): VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row || (int) $row->business_id !== $businessId || ! $row->isHeld()) {
            throw ValidationException::withMessages(['deposit' => __('لا يوجد عربون مجمّد لتعيده.')]);
        }

        return $this->refund($subject);
    }

    /**
     * Either party opens a dispute over the held deposit (a no-show, a disagreement). The deposit stays frozen
     * until the dispute is ruled on — the one situation in which an account is held back.
     */
    public function openDispute(Model $subject, int $userId, ?string $reason = null, ?string $reasonCode = null): Dispute
    {
        $row = $this->rowFor($subject);

        if (! $row || $row->mode !== VisitDeposit::MODE_WALLET) {
            throw ValidationException::withMessages(['deposit' => __('لا يوجد عربون على المنصة لفتح نزاع عليه.')]);
        }

        if (! in_array($userId, [(int) $row->client_id, (int) $row->business_id], true)) {
            throw ValidationException::withMessages(['deposit' => __('لست طرفًا في هذا الموعد.')]);
        }

        if (! in_array($row->status, [VisitDeposit::STATUS_HELD, VisitDeposit::STATUS_DISPUTED], true)) {
            throw ValidationException::withMessages(['deposit' => __('لا يمكن فتح نزاع على هذا العربون الآن.')]);
        }

        $service = PlatformService::query()->where('key', 'booking')->first();

        $dispute = $this->disputes->open(
            platformServiceId: (int) ($service?->id ?? 0),
            disputeableType: $subject->getMorphClass(),
            disputeableId: (int) $subject->getKey(),
            openedByUserId: $userId,
            againstUserId: $userId === (int) $row->client_id ? (int) $row->business_id : (int) $row->client_id,
            actorId: $userId,
            payload: [
                'type' => 'deposit',
                'deposit_id' => $row->deposit_id,
                'reason_code' => $reasonCode,
                'reason_text' => $reason,
                'source' => 'api_v2',
            ],
        );

        $row->update(['status' => VisitDeposit::STATUS_DISPUTED, 'dispute_id' => (int) $dispute->id]);

        return $dispute;
    }

    /** The dispute over this visit was ruled on — the escrow unwound by the ruling, the row follows. */
    public function markResolved(Model $subject): void
    {
        $row = $this->rowFor($subject);

        if ($row && in_array($row->status, [VisitDeposit::STATUS_DISPUTED, VisitDeposit::STATUS_HELD], true)) {
            // the dispute is over: coverage frozen for the visit goes back, it is never charged
            $this->core->releaseGuarantees($this->operationOf($subject));
            $row->update(['status' => VisitDeposit::STATUS_RESOLVED]);
        }
    }

    /* ---------------------------------------------------------------- misc */

    /**
     * A 422 that says what is missing and, for the patient, how the gap may be closed (top up / a better guarantee /
     * a friend's guarantee) — the same options a booking offers.
     *
     * @param array<string,mixed> $options
     */
    private function refuse(string $message, array $options = []): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['deposit' => [$message]],
            'options' => $options === [] ? null : $options,
        ], 422));
    }

    private function fmt(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function tell(VisitDeposit $row, string $eventKey, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, (int) $row->client_id, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => $row->subject_type, 'notifiable_id' => (int) $row->subject_id,
                'source_type' => $row->subject_type, 'source_id' => (int) $row->subject_id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
