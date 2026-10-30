<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use App\Models\Crm\Payment;
use App\Models\Order;
use App\Models\User;
use App\Services\Crm\CreditLedger;
use App\Services\Crm\CustomerFiles;
use App\Services\Crm\PaymentMatching;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\BusinessCapability;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * «ملفات العملاء ودفتر الآجل» — the business's side (desktop suite phase 3, owner approved 2026-10-30). A file per
 * customer; what he bought from the app and at the counter; his credit book with its limit, aging and statement; the
 * payments that count only once both sides confirm them; and a manual reminder (no marketing blasts — law 151/2020).
 */
class CustomerFileController extends Controller
{
    public function __construct(
        private readonly CustomerFiles $files,
        private readonly CreditLedger $ledger,
        private readonly PaymentMatching $payments,
    ) {}

    /**
     * GET /business/customers — search by name or phone; filter all|owing|top|frequent|new|lapsed|overdue|tag; the
     * lapsed window is ?lapsed_days (default 60).
     */
    public function index(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'filter' => ['nullable', Rule::in(['all', 'owing', 'top', 'frequent', 'new', 'lapsed', 'overdue'])],
            'tag' => ['nullable', 'string', 'max:40'],
            'lapsed_days' => ['nullable', 'integer', 'min:7', 'max:730'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));
        $customers = Customer::query()
            ->where('business_id', $businessId)
            ->whereNull('anonymized_at')
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->when($digits !== '', fn ($y) => $y->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->orderBy('name')
            ->limit(2000)
            ->get();

        if (! empty($data['tag'])) {
            $customers = $customers->filter(fn ($c) => in_array($data['tag'], (array) $c->tags, true))->values();
        }

        $stats = $this->files->stats($businessId, $customers);
        $filter = $data['filter'] ?? 'all';
        $lapsed = now()->subDays((int) ($data['lapsed_days'] ?? 60))->toDateTimeString();
        $monthStart = now()->startOfMonth();

        $rows = $customers->map(fn (Customer $c) => $this->row($c, $stats[$c->id]));
        $rows = match ($filter) {
            'owing' => $rows->filter(fn ($r) => $r['balance'] > 0)->sortByDesc('balance'),
            'top' => $rows->filter(fn ($r) => $r['spend'] > 0)->sortByDesc('spend'),
            'frequent' => $rows->filter(fn ($r) => $r['visits'] > 0)->sortByDesc('visits'),
            'new' => $rows->filter(fn ($r) => $r['created_at'] !== null && Carbon::parse($r['created_at'])->gte($monthStart)),
            'lapsed' => $rows->filter(fn ($r) => $r['last_at'] !== null && $r['last_at'] < $lapsed)->sortBy('last_at'),
            'overdue' => $rows->filter(fn ($r) => $r['balance'] > 0)
                ->map(fn ($r) => $r + ['overdue' => $this->ledger->aging($customers->firstWhere('id', $r['id']))['overdue']])
                ->filter(fn ($r) => $r['overdue'] > 0)->sortByDesc('overdue'),
            default => $rows,
        };

        $all = Customer::query()->where('business_id', $businessId)->whereNull('anonymized_at');

        return $this->ok([
            'customers' => $rows->take((int) ($data['limit'] ?? 300))->values(),
            'summary' => [
                'count' => (clone $all)->count(),
                'owing_count' => (clone $all)->where('balance', '>', 0)->count(),
                'owed_total' => round((float) (clone $all)->where('balance', '>', 0)->sum('balance'), 2),
                'payments_this_month' => round((float) Payment::query()->where('business_id', $businessId)->where('status', Payment::CONFIRMED)->where('confirmed_at', '>=', $monthStart)->sum('amount'), 2),
                'new_this_month' => (clone $all)->where('created_at', '>=', $monthStart)->count(),
                'pending_payments' => Payment::query()->where('business_id', $businessId)->where('status', Payment::PENDING)->where('started_by', Payment::BY_CUSTOMER)->count(),
            ],
        ]);
    }

    /** POST /business/customers — a customer outside the app (name, phone, …), from the cashier or the customers screen. */
    public function store(Request $request)
    {
        $businessId = BusinessContext::id($request);
        $data = $request->validate($this->rules(true));

        $phone = isset($data['phone']) ? preg_replace('/[^\d+]/', '', (string) $data['phone']) : null;
        if ($phone && ($same = Customer::query()->where('business_id', $businessId)->whereNull('anonymized_at')->where('phone', $phone)->first())) {
            return $this->ok(['customer' => $this->row($same, $this->files->stats($businessId, collect([$same]))[$same->id]), 'existing' => true]);
        }

        $customer = DB::transaction(function () use ($businessId, $data, $phone, $request) {
            $customer = Customer::create([
                'business_id' => $businessId,
                'name' => $data['name'],
                'phone' => $phone ?: null,
                'address' => $data['address'] ?? null,
                'tags' => $this->tags($data['tags'] ?? null),
                'notes' => $data['notes'] ?? null,
                'credit_limit' => $this->mayManageCredit($request) ? ($data['credit_limit'] ?? null) : null,
                'payment_terms_days' => $data['payment_terms_days'] ?? null,
                'created_by' => (int) $request->user()->id,
            ]);
            if (! empty($data['opening_balance']) && $this->mayManageCredit($request)) {
                $this->ledger->post($customer, AccountMove::OPENING, (float) $data['opening_balance'], ['note' => __('رصيد سابق'), 'created_by' => (int) $request->user()->id]);
            }

            return $customer->fresh();
        });

        return $this->ok(['customer' => $this->row($customer, $this->files->stats($businessId, collect([$customer]))[$customer->id]), 'existing' => false], 201);
    }

    /** GET /business/customers/{customer} — the whole file. */
    public function show(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $stats = $this->files->stats($c->business_id, collect([$c]))[$c->id];

        return $this->ok([
            'customer' => $this->row($c, $stats) + [
                'notes' => $c->notes,
                'address' => $c->address,
                'average_ticket' => $stats['visits'] > 0 ? round($stats['spend'] / $stats['visits'], 2) : 0.0,
                'spend_this_year' => $this->spendSince($c, now()->startOfYear()),
                'aging' => $this->ledger->aging($c),
            ],
            'moves' => $this->ledger->statement($c, null, null)['lines'],
            'purchases' => $this->files->purchases($c, 60),
            'top_items' => $this->files->topItems($c),
            'payments' => Payment::query()->where('customer_id', $c->id)->latest('id')->limit(50)->get()->map(fn ($p) => $this->payments->view($p, Payment::BY_BUSINESS))->values(),
        ]);
    }

    /** PATCH /business/customers/{customer} — the business's own data about him. Limits and terms need the credit right. */
    public function update(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $data = $request->validate($this->rules(false));

        if ((array_key_exists('credit_limit', $data) || array_key_exists('payment_terms_days', $data)) && ! $this->mayManageCredit($request)) {
            throw ValidationException::withMessages(['credit_limit' => __('حد الآجل ومدة السداد لصاحب النشاط أو من يملك صلاحية «تجاوز حد الآجل».')]);
        }
        if (array_key_exists('phone', $data)) {
            $data['phone'] = $data['phone'] ? preg_replace('/[^\d+]/', '', (string) $data['phone']) : null;
        }
        if (array_key_exists('tags', $data)) {
            $data['tags'] = $this->tags($data['tags']);
        }
        // an app customer's name is his account's; the business keeps its own notes and tags instead
        if ($c->user_id) {
            unset($data['name']);
        }
        unset($data['opening_balance']);

        $c->update($data);

        return $this->show($request, $c->id);
    }

    /** DELETE /business/customers/{customer} — the customer asked to be forgotten (the book stays, nameless). */
    public function destroy(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $this->files->anonymize($c);

        return $this->ok(['deleted' => true]);
    }

    /** POST /business/customers/resolve-code — the cashier types or scans the customer's «كود حسابي»: his file here. */
    public function resolveCode(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $businessId = BusinessContext::id($request);
        $userId = $this->files->userForCode($data['code']);
        $c = $this->files->ensureForUser($businessId, $userId);
        if (! $c) {
            throw ValidationException::withMessages(['code' => __('لا يُربط ملف عميل بحساب النشاط نفسه.')]);
        }

        return $this->ok(['customer' => $this->row($c, $this->files->stats($businessId, collect([$c]))[$c->id])]);
    }

    /** POST /business/customers/{customer}/link — an off-app file joins the account whose code the customer showed. */
    public function link(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $linked = $this->files->link($c, $this->files->userForCode($data['code']));

        return $this->show($request, $linked->id);
    }

    /** GET /business/customers/{customer}/statement?from=&to= — «كشف حساب». */
    public function statement(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return $this->ok([
            'customer' => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'credit_limit' => $c->credit_limit, 'payment_terms_days' => $c->payment_terms_days],
            'statement' => $this->ledger->statement($c, isset($data['from']) ? Carbon::parse($data['from']) : null, isset($data['to']) ? Carbon::parse($data['to']) : null),
        ]);
    }

    /** POST /business/customers/{customer}/charge-order — an app order of his collected on account instead of cash. */
    public function chargeOrder(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $data = $request->validate(['order_id' => ['required', 'integer'], 'credit_override' => ['nullable', 'boolean']]);

        if (! $c->user_id) {
            throw ValidationException::withMessages(['order_id' => __('طلبات التطبيق تخص عملاء التطبيق فقط.')]);
        }
        $order = Order::query()->where('business_id', $c->business_id)->where('user_id', $c->user_id)->find((int) $data['order_id']);
        if (! $order || in_array($order->status, ['cancelled', 'rejected'], true)) {
            throw ValidationException::withMessages(['order_id' => __('الطلب غير موجود أو أُلغي.')]);
        }
        if (AccountMove::query()->where('source_type', 'order')->where('source_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['order_id' => __('هذا الطلب على الحساب بالفعل.')]);
        }

        $override = (bool) ($data['credit_override'] ?? false);
        if ($override && ! $this->mayManageCredit($request)) {
            throw ValidationException::withMessages(['credit_override' => __('تجاوز حد الآجل يحتاج صلاحية «تجاوز حد الآجل».')]);
        }
        $amount = round((float) ($order->final_total ?? $order->total ?? 0), 2);
        $this->ledger->assertWithinLimit($c, $amount, $override);
        $this->ledger->post($c, AccountMove::ORDER, $amount, [
            'uuid' => $this->ledger->uuidFrom('order|' . $order->id),
            'source_type' => 'order',
            'source_id' => $order->id,
            'note' => __('طلب تطبيق #:n — تحصيل آجل', ['n' => $order->id]),
            'created_by' => (int) $request->user()->id,
        ]);

        return $this->show($request, $c->id);
    }

    /** POST /business/customers/{customer}/adjust — a correction with its reason (+ he owes more, − less). */
    public function adjust(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        if (! $this->mayManageCredit($request)) {
            throw ValidationException::withMessages(['amount' => __('التسوية لصاحب النشاط أو من يملك صلاحية «تجاوز حد الآجل».')]);
        }
        $data = $request->validate(['uuid' => ['nullable', 'uuid'], 'amount' => ['required', 'numeric', 'not_in:0', 'between:-10000000,10000000'], 'reason' => ['required', 'string', 'max:200']]);
        $this->ledger->post($c, AccountMove::ADJUSTMENT, (float) $data['amount'], ['uuid' => $data['uuid'] ?? null, 'note' => $data['reason'], 'created_by' => (int) $request->user()->id]);

        return $this->show($request, $c->id);
    }

    /** POST /business/customers/{customer}/payments — «استلمت»: the business records a payment; the customer confirms. */
    public function storePayment(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $payment = $this->payments->start($c, Payment::BY_BUSINESS, (int) $request->user()->id, $request->validate($this->paymentRules()));

        return $this->ok(['payment' => $this->payments->view($payment, Payment::BY_BUSINESS)], 201);
    }

    /** GET /business/customer-payments?status=pending — payments waiting on either side. */
    public function payments(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in([Payment::PENDING, Payment::CONFIRMED, Payment::MISMATCH, Payment::REJECTED, Payment::CANCELLED])]]);
        $rows = Payment::query()
            ->where('business_id', BusinessContext::id($request))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->limit(200)->get();
        $names = Customer::query()->whereIn('id', $rows->pluck('customer_id'))->pluck('name', 'id');

        return $this->ok(['payments' => $rows->map(fn ($p) => $this->payments->view($p, Payment::BY_BUSINESS) + ['customer_name' => $names[$p->customer_id] ?? null])->values()]);
    }

    /** POST /business/customer-payments/{payment}/confirm|reject|cancel */
    public function confirmPayment(Request $request, int $payment)
    {
        $p = $this->findPayment($request, $payment);
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:64']]);

        return $this->ok(['payment' => $this->payments->view($this->payments->confirm($p, Payment::BY_BUSINESS, (int) $request->user()->id, $data['reference'] ?? null), Payment::BY_BUSINESS)]);
    }

    public function rejectPayment(Request $request, int $payment)
    {
        $p = $this->findPayment($request, $payment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->ok(['payment' => $this->payments->view($this->payments->reject($p, Payment::BY_BUSINESS, $data['reason'] ?? null), Payment::BY_BUSINESS)]);
    }

    public function cancelPayment(Request $request, int $payment)
    {
        $p = $this->findPayment($request, $payment);

        return $this->ok(['payment' => $this->payments->view($this->payments->cancel($p, Payment::BY_BUSINESS), Payment::BY_BUSINESS)]);
    }

    /**
     * POST /business/customers/{customer}/remind — «ذكّره»: an app customer gets a notice inside the app (once a day at
     * most); for anyone, the text comes back for the business to share from its own phone.
     */
    public function remind(Request $request, int $customer)
    {
        $c = $this->find($request, $customer);
        $business = (string) optional(User::query()->find($c->business_id))->name;
        $amount = number_format(max(0, (float) $c->balance), 2);
        $text = __('تذكير من :business: رصيدك المستحق :amount ج. شكرًا لتعاملك معنا.', ['business' => $business, 'amount' => $amount]);

        $sent = false;
        if ($c->user_id && (float) $c->balance > 0 && (! $c->last_reminded_at || $c->last_reminded_at->lt(now()->subDay()))) {
            try {
                app(NotificationDispatcherService::class)->dispatch('crm_reminder', (int) $c->user_id, [
                    'body_ar' => "تذكير من {$business}: رصيدك المستحق {$amount} ج.",
                    'body_en' => "A reminder from {$business}: your balance due is {$amount} EGP.",
                    'source_type' => 'crm_customer',
                    'source_id' => $c->id,
                ]);
                $c->update(['last_reminded_at' => now()]);
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->ok(['sent' => $sent, 'text' => $text, 'phone' => $c->phone]);
    }

    private function row(Customer $c, array $stats): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'on_app' => $c->user_id !== null,
            'tags' => array_values((array) $c->tags),
            'balance' => (float) $c->balance,
            'credit_limit' => $c->credit_limit,
            'payment_terms_days' => $c->payment_terms_days,
            'spend' => $stats['spend'],
            'visits' => $stats['visits'],
            'last_at' => $stats['last_at'],
            'created_at' => $c->created_at?->toDateTimeString(),
        ];
    }

    private function spendSince(Customer $c, Carbon $since): float
    {
        $orders = $c->user_id
            ? (float) Order::query()->where('business_id', $c->business_id)->where('user_id', $c->user_id)->whereNotIn('status', ['cancelled', 'rejected'])->where('created_at', '>=', $since)->sum(DB::raw('COALESCE(final_total, total, 0)'))
            : 0.0;
        $sales = (float) DB::table('inv_pos_sales')->where('customer_id', $c->id)->where('status', 'paid')->where('sold_at', '>=', $since)->sum('total');

        return round($orders + $sales, 2);
    }

    private function find(Request $request, int $id): Customer
    {
        return Customer::query()->where('business_id', BusinessContext::id($request))->whereNull('anonymized_at')->findOrFail($id);
    }

    private function findPayment(Request $request, int $id): Payment
    {
        return Payment::query()->where('business_id', BusinessContext::id($request))->findOrFail($id);
    }

    private function mayManageCredit(Request $request): bool
    {
        return BusinessContext::isOwner($request) || in_array(BusinessCapability::CREDIT_OVERRIDE, BusinessContext::capabilities($request), true);
    }

    private function tags(mixed $tags): ?array
    {
        $clean = array_values(array_unique(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 40), (array) $tags))));

        return $clean === [] ? null : array_slice($clean, 0, 12);
    }

    private function rules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'payment_terms_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'opening_balance' => ['nullable', 'numeric', 'between:-100000000,100000000'],
        ];
    }

    public static function paymentRules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
            'device_uuid' => ['nullable', 'uuid'],
        ];
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
