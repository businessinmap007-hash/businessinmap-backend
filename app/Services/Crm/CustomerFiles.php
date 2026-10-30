<?php

namespace App\Services\Crm;

use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use App\Models\Crm\Payment;
use App\Models\Inventory\PosSale;
use App\Models\Inventory\PosSaleLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «ملفات العملاء» — who a business's customers are and what each one bought from it. An app customer's file opens by
 * itself from his first order; one outside the app is typed in. A business sees only those who dealt with IT, and
 * never what they bought elsewhere. Linking an off-app file to an app account happens only when the customer shows his
 * own account code at the counter — consent by his own act, never a phone match in the background.
 */
final class CustomerFiles
{
    /** Orders that never happened do not count as purchases. */
    private const DEAD_ORDER = ['cancelled', 'rejected'];

    private const LINK_CODE_TTL_MINUTES = 10;

    public function __construct(private readonly CreditLedger $ledger) {}

    /** The app customer's file at this business, opened on first contact. */
    public function ensureForUser(int $businessId, int $userId): ?Customer
    {
        if ($businessId === $userId) {
            return null;
        }

        $existing = Customer::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        if ($existing) {
            return $existing;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return null;
        }

        return Customer::query()->firstOrCreate(
            ['business_id' => $businessId, 'user_id' => $userId],
            ['name' => mb_substr((string) ($user->name ?: '—'), 0, 120)],
        )->fresh();
    }

    /**
     * Spend, visits and last visit for each file — app orders and cashier sales together.
     *
     * @param  Collection<int,Customer>  $customers
     * @return array<int,array{spend:float,visits:int,last_at:?string,first_at:?string}>
     */
    public function stats(int $businessId, Collection $customers): array
    {
        $out = [];
        foreach ($customers as $c) {
            $out[$c->id] = ['spend' => 0.0, 'visits' => 0, 'last_at' => null, 'first_at' => $c->created_at?->toDateTimeString()];
        }

        $byUser = $customers->whereNotNull('user_id')->pluck('id', 'user_id');
        if ($byUser->isNotEmpty()) {
            $rows = Order::query()
                ->where('business_id', $businessId)
                ->whereIn('user_id', $byUser->keys())
                ->whereNotIn('status', self::DEAD_ORDER)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(COALESCE(final_total, total, 0)) as spend, COUNT(*) as visits, MAX(created_at) as last_at')
                ->get();
            foreach ($rows as $r) {
                $id = $byUser[$r->user_id];
                $out[$id]['spend'] += (float) $r->spend;
                $out[$id]['visits'] += (int) $r->visits;
                $out[$id]['last_at'] = $this->later($out[$id]['last_at'], $r->last_at);
            }
        }

        $rows = PosSale::query()
            ->where('business_id', $businessId)
            ->whereIn('customer_id', $customers->pluck('id'))
            ->where('status', PosSale::PAID)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(total) as spend, COUNT(*) as visits, MAX(sold_at) as last_at')
            ->get();
        foreach ($rows as $r) {
            $out[$r->customer_id]['spend'] += (float) $r->spend;
            $out[$r->customer_id]['visits'] += (int) $r->visits;
            $out[$r->customer_id]['last_at'] = $this->later($out[$r->customer_id]['last_at'], $r->last_at);
        }

        foreach ($out as &$row) {
            $row['spend'] = round($row['spend'], 2);
        }

        return $out;
    }

    /** Everything this customer bought here, newest first: app orders and cashier receipts. */
    public function purchases(Customer $customer, int $limit = 100): array
    {
        $rows = [];

        if ($customer->user_id) {
            foreach (Order::query()->where('business_id', $customer->business_id)->where('user_id', $customer->user_id)->latest('id')->limit($limit)->get() as $o) {
                $rows[] = [
                    'source' => 'order',
                    'id' => $o->id,
                    'number' => (string) $o->id,
                    'total' => round((float) ($o->final_total ?? $o->total ?? 0), 2),
                    'status' => (string) $o->status,
                    'at' => $o->created_at,
                    'charged' => AccountMove::query()->where('source_type', 'order')->where('source_id', $o->id)->exists(),
                ];
            }
        }

        foreach (PosSale::query()->where('business_id', $customer->business_id)->where('customer_id', $customer->id)->latest('id')->limit($limit)->get() as $s) {
            $rows[] = [
                'source' => 'pos_sale',
                'id' => $s->id,
                'number' => (string) $s->number,
                'total' => round((float) $s->total, 2),
                'on_account' => round((float) $s->on_account, 2),
                'status' => (string) $s->status,
                'at' => $s->sold_at,
                'charged' => (float) $s->on_account > 0,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($rows, 0, $limit);
    }

    /** What he buys most at the counter (by quantity). */
    public function topItems(Customer $customer, int $limit = 8): array
    {
        return PosSaleLine::query()
            ->join('inv_pos_sales as s', 's.id', '=', 'inv_pos_sale_lines.sale_id')
            ->where('s.customer_id', $customer->id)
            ->where('s.status', PosSale::PAID)
            ->groupBy('inv_pos_sale_lines.name')
            ->orderByDesc(DB::raw('SUM(inv_pos_sale_lines.qty)'))
            ->limit($limit)
            ->selectRaw('inv_pos_sale_lines.name as name, SUM(inv_pos_sale_lines.qty) as qty, SUM(inv_pos_sale_lines.total) as total')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => round((float) $r->qty, 3), 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** «كود حسابي» — a short code the customer shows at the counter for 10 minutes. */
    public function linkCode(int $userId): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Cache::has('crm_link:' . $code));

        $expires = now()->addMinutes(self::LINK_CODE_TTL_MINUTES);
        Cache::put('crm_link:' . $code, $userId, $expires);

        return ['code' => $code, 'expires_at' => $expires];
    }

    public function userForCode(string $code): int
    {
        $userId = Cache::get('crm_link:' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
        if (! $userId) {
            throw ValidationException::withMessages(['code' => __('الكود غير صحيح أو انتهت صلاحيته. اطلب من العميل كودًا جديدًا من تطبيقه.')]);
        }

        return (int) $userId;
    }

    /**
     * An off-app file joins the customer's app account. If he already has an app file here, the off-app one folds into
     * it — its book, receipts and payments move over and it disappears; nothing is lost.
     */
    public function link(Customer $offline, int $userId): Customer
    {
        if ($offline->user_id !== null) {
            throw ValidationException::withMessages(['code' => __('هذا الملف مربوط بحساب على التطبيق بالفعل.')]);
        }
        if ($userId === (int) $offline->business_id) {
            throw ValidationException::withMessages(['code' => __('لا يُربط ملف عميل بحساب النشاط نفسه.')]);
        }

        return DB::transaction(function () use ($offline, $userId) {
            $target = Customer::query()->where('business_id', $offline->business_id)->where('user_id', $userId)->lockForUpdate()->first();

            if (! $target) {
                $offline->update(['user_id' => $userId]);

                return $offline->fresh();
            }

            AccountMove::query()->where('customer_id', $offline->id)->update(['customer_id' => $target->id]);
            Payment::query()->where('customer_id', $offline->id)->update(['customer_id' => $target->id]);
            PosSale::query()->where('customer_id', $offline->id)->update(['customer_id' => $target->id]);

            $target->update([
                'phone' => $target->phone ?: $offline->phone,
                'address' => $target->address ?: $offline->address,
                'notes' => trim(implode("\n", array_filter([$target->notes, $offline->notes]))) ?: null,
                'tags' => array_values(array_unique(array_merge((array) $target->tags, (array) $offline->tags))) ?: null,
                'credit_limit' => $target->credit_limit ?? $offline->credit_limit,
                'payment_terms_days' => $target->payment_terms_days ?? $offline->payment_terms_days,
            ]);
            $offline->delete();
            $this->ledger->refresh($target);

            return $target->fresh();
        });
    }

    /**
     * The customer asked to be forgotten: his name, phone, address and the business's notes go; the receipts and the
     * book stay, under «عميل محذوف», because accounts are never erased.
     */
    public function anonymize(Customer $customer): Customer
    {
        $customer->update([
            'name' => 'عميل محذوف',
            'user_id' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
            'tags' => null,
            'anonymized_at' => now(),
        ]);

        return $customer->fresh();
    }

    private function later(?string $a, $b): ?string
    {
        $b = $b === null ? null : (string) $b;
        if ($a === null) {
            return $b;
        }

        return ($b !== null && strcmp($b, $a) > 0) ? $b : $a;
    }
}
