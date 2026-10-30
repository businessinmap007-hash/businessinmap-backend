<?php

namespace App\Services\Crm;

use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «دفتر الآجل» — the ONE writer of a customer's credit book. Like the stock ledger: every sale on account, payment or
 * return is a line that is never edited or deleted, the balance is their sum (kept on the file under a row lock so two
 * cashiers cannot race it), and a wrong line is undone by an opposite one. Money itself is always outside the app.
 */
final class CreditLedger
{
    /** Writes one line (idempotent on `uuid`) and refreshes the file's balance. */
    public function post(Customer $customer, string $kind, float $amount, array $extra = []): AccountMove
    {
        $uuid = (string) ($extra['uuid'] ?? '') ?: (string) Str::uuid();

        return DB::transaction(function () use ($customer, $kind, $amount, $extra, $uuid) {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($existing = AccountMove::query()->where('uuid', $uuid)->first()) {
                return $existing;
            }

            $move = AccountMove::create([
                'uuid' => $uuid,
                'business_id' => $locked->business_id,
                'customer_id' => $locked->id,
                'kind' => $kind,
                'amount' => round($amount, 2),
                'source_type' => $extra['source_type'] ?? null,
                'source_id' => $extra['source_id'] ?? null,
                'note' => isset($extra['note']) ? mb_substr((string) $extra['note'], 0, 255) : null,
                'created_by' => $extra['created_by'] ?? null,
                'device_uuid' => $extra['device_uuid'] ?? null,
                'occurred_at' => isset($extra['occurred_at']) ? Carbon::parse($extra['occurred_at']) : now(),
            ]);

            $this->refresh($locked);

            return $move;
        });
    }

    /** Sums the book again onto the file (after a post, a merge). */
    public function refresh(Customer $customer): void
    {
        $sum = (float) AccountMove::query()->where('customer_id', $customer->id)->sum('amount');
        Customer::query()->whereKey($customer->id)->update(['balance' => round($sum, 2)]);
    }

    /** The lines a sale or an order wrote, undone by opposite lines — once (a cancelled order, a returned sale). */
    public function reverseSource(string $sourceType, int $sourceId, string $note, ?int $actorId = null): void
    {
        $moves = AccountMove::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->get();

        foreach ($moves as $move) {
            $customer = Customer::query()->find($move->customer_id);
            if (! $customer) {
                continue;
            }
            $this->post($customer, AccountMove::RETURN, -1 * (float) $move->amount, [
                'uuid' => $this->uuidFrom('reverse|' . $move->uuid),
                'source_type' => 'crm_move',
                'source_id' => $move->id,
                'note' => $note,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * Refuses a new debt that would take the customer past his limit — unless someone allowed to exceed it says so.
     * No limit set = no ceiling.
     */
    public function assertWithinLimit(Customer $customer, float $adding, bool $override): void
    {
        if ($customer->credit_limit === null || $override) {
            return;
        }

        $over = round((float) $customer->balance + $adding - (float) $customer->credit_limit, 2);
        if ($over > 0.001) {
            throw ValidationException::withMessages([
                'credit_limit' => __('يتجاوز حد الآجل بـ :over ج. ادفع الفرق نقدًا أو اطلب موافقة من يملك صلاحية تجاوز الحد.', ['over' => number_format($over, 2)]),
            ]);
        }
    }

    /**
     * How old what he owes is: the payments and returns settle the OLDEST debts first, and what remains is bucketed by
     * its age on [$asOf]. @return array{0_30:float,31_60:float,61_90:float,over_90:float,overdue:float}
     */
    public function aging(Customer $customer, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $moves = AccountMove::query()->where('customer_id', $customer->id)->where('occurred_at', '<=', $asOf)->orderBy('occurred_at')->orderBy('id')->get();

        $credit = (float) $moves->where('amount', '<', 0)->sum(fn ($m) => -1 * (float) $m->amount);
        $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'overdue' => 0.0];
        $terms = $customer->payment_terms_days;

        foreach ($moves->where('amount', '>', 0) as $debit) {
            $open = (float) $debit->amount;
            $take = min($open, $credit);
            $open -= $take;
            $credit -= $take;
            if ($open <= 0.001) {
                continue;
            }
            $days = (int) $debit->occurred_at->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
            $key = $days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90'));
            $buckets[$key] += $open;
            if ($terms !== null && $days > $terms) {
                $buckets['overdue'] += $open;
            }
        }

        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    /** «كشف حساب» between two dates: the balance brought forward, each line with its running balance, and the aging. */
    public function statement(Customer $customer, ?Carbon $from, ?Carbon $to): array
    {
        $to = ($to ?? now())->copy()->endOfDay();
        $opening = $from ? round((float) AccountMove::query()->where('customer_id', $customer->id)->where('occurred_at', '<', $from->copy()->startOfDay())->sum('amount'), 2) : 0.0;

        $lines = AccountMove::query()
            ->where('customer_id', $customer->id)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from->copy()->startOfDay()))
            ->where('occurred_at', '<=', $to)
            ->orderBy('occurred_at')->orderBy('id')
            ->get();

        $running = $opening;
        $rows = [];
        foreach ($lines as $m) {
            $running = round($running + (float) $m->amount, 2);
            $rows[] = [
                'id' => $m->id,
                'kind' => $m->kind,
                'amount' => (float) $m->amount,
                'debit' => $m->amount > 0 ? (float) $m->amount : 0.0,
                'credit' => $m->amount < 0 ? -1 * (float) $m->amount : 0.0,
                'balance' => $running,
                'note' => $m->note,
                'source_type' => $m->source_type,
                'source_id' => $m->source_id,
                'occurred_at' => $m->occurred_at,
            ];
        }

        return [
            'from' => $from?->toDateString(),
            'to' => $to->toDateString(),
            'opening' => $opening,
            'closing' => $running,
            'lines' => $rows,
            'aging' => $this->aging($customer, $to),
        ];
    }

    public function uuidFrom(string $seed): string
    {
        $h = md5($seed);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }
}
