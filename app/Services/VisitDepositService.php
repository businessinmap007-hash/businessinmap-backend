<?php

namespace App\Services;

use App\Contracts\HasVisitDeposit;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\VisitDeposit;
use App\Models\Wallet;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «عربون الجدية» for health visits — a clinic appointment, a lab/radiology order, a hospital procedure.
 *
 * It is the SAME deposit a business sets in «شروط الحجز» (one policy row, one calculator, one escrow); any business
 * may switch it on or leave it off. What differs is only the moment each step happens:
 *
 *   quote      the visit's value × the business's terms → what is owed, as wallet hold or outside the platform
 *   afford     at the request: the patient's free wallet balance must cover his share (nothing is frozen yet)
 *   secure     when the visit stands (clinic confirms / centre accepts / hospital accepts): freeze both shares,
 *              or record the deposit as owed outside the platform
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
    ) {
    }

    /* ---------------------------------------------------------------- quote */

    /**
     * What the business's terms ask for a visit of this value, or null when it asks for nothing.
     *
     * @return array{mode:string,amount:float,counter_amount:float,percent:float}|null
     */
    public function quote(int $businessId, float $value): ?array
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

        if (! ($out['required'] ?? false)) {
            return null;
        }

        $external = (bool) ($out['external_deposit_required'] ?? false);
        $amount = (float) ($external ? $out['external_deposit_amount'] : $out['wallet_hold_amount']);

        if ($amount <= 0) {
            return null;
        }

        return [
            'mode' => $external ? VisitDeposit::MODE_EXTERNAL : VisitDeposit::MODE_WALLET,
            'amount' => round($amount, 2),
            'counter_amount' => $external ? 0.0 : round((float) $out['business_counter_hold_amount'], 2),
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
                'method' => $row->method,
                'reference' => $row->reference,
                'dispute_id' => $row->dispute_id ? (int) $row->dispute_id : null,
            ];
        }

        if (! $subject instanceof HasVisitDeposit || ! $subject->visitDepositOpen()) {
            return null;
        }

        $quote = $this->quote($subject->visitDepositBusinessId(), $subject->visitDepositValue());

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
        $quote = $this->quote($businessId, $value);

        if (! $quote || $quote['mode'] !== VisitDeposit::MODE_WALLET) {
            return;
        }

        $balance = (float) (Wallet::query()->where('user_id', $clientId)->value('balance') ?? 0);

        if ($balance + 0.0001 < $quote['amount']) {
            throw ValidationException::withMessages([
                'deposit' => __('هذا النشاط يطلب عربونًا :amount ج لتأكيد الحجز، ورصيدك في المحفظة لا يكفي. اشحن محفظتك ثم أعد المحاولة.', [
                    'amount' => $this->fmt($quote['amount']),
                ]),
            ]);
        }
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
        $quote = $this->quote($businessId, $subject->visitDepositValue());

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

        $this->assertWalletCovers($clientId, $quote['amount'], __('رصيد المريض في المحفظة لا يكفي لعربون هذا الموعد (:amount ج).', ['amount' => $this->fmt($quote['amount'])]));
        $this->assertWalletCovers($businessId, $quote['counter_amount'], __('رصيد النشاط لا يكفي لتجميد حصته من العربون (:amount ج). اشحن محفظتك ثم أكّد.', ['amount' => $this->fmt($quote['counter_amount'])]));

        return DB::transaction(function () use ($subject, $clientId, $businessId, $quote) {
            $deposit = $this->escrow->create(
                clientId: $clientId,
                businessId: $businessId,
                totalAmount: $quote['amount'] + $quote['counter_amount'],
                targetType: $subject->getMorphClass(),
                targetId: (int) $subject->getKey(),
                clientAmount: $quote['amount'],
                businessAmount: $quote['counter_amount'],
            );

            return VisitDeposit::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'client_id' => $clientId,
                'business_id' => $businessId,
                'mode' => VisitDeposit::MODE_WALLET,
                'amount' => $quote['amount'],
                'counter_amount' => $quote['counter_amount'],
                'status' => VisitDeposit::STATUS_HELD,
                'deposit_id' => (int) $deposit->id,
            ]);
        });
    }

    /* ------------------------------------------------------ release / refund */

    /** The visit was done: every hold goes back to whoever posted it. Safe on a visit with no deposit. */
    public function release(Model $subject): ?VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row || ! $row->isHeld()) {
            return $row;
        }

        $deposit = Deposit::query()->find($row->deposit_id);

        if ($deposit && ! $deposit->isFinal()) {
            $this->escrow->release($deposit);
        }

        $row->update(['status' => VisitDeposit::STATUS_RELEASED]);

        return $row->fresh();
    }

    /** The visit did not happen: every hold goes back, nothing is kept. Safe on a visit with no deposit. */
    public function refund(Model $subject): ?VisitDeposit
    {
        $row = $this->rowFor($subject);

        if (! $row) {
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

        $deposit = Deposit::query()->find($row->deposit_id);

        if ($deposit && ! $deposit->isFinal()) {
            $this->escrow->refund($deposit, true, true);
        }

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

        if ($row && $row->status === VisitDeposit::STATUS_DISPUTED) {
            $row->update(['status' => VisitDeposit::STATUS_RESOLVED]);
        }
    }

    /* ---------------------------------------------------------------- misc */

    private function assertWalletCovers(int $userId, float $amount, string $message): void
    {
        if ($amount <= 0) {
            return;
        }

        $balance = (float) (Wallet::query()->where('user_id', $userId)->value('balance') ?? 0);

        if ($balance + 0.0001 < $amount) {
            throw ValidationException::withMessages(['deposit' => $message]);
        }
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
