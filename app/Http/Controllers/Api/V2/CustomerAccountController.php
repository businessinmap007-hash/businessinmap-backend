<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Crm\AccountMove;
use App\Models\Crm\Customer;
use App\Models\Crm\Payment;
use App\Models\User;
use App\Services\Crm\CreditLedger;
use App\Services\Crm\CustomerFiles;
use App\Services\Crm\PaymentMatching;
use Illuminate\Http\Request;

/**
 * «حسابي عند المحلات» — the customer's side of the credit book: what he owes at each business that keeps his file, its
 * lines, and the payments waiting for him to confirm (or that he recorded himself). He reads and confirms; he never
 * edits the book, and never sees the business's private notes. «كود حسابي» is the short code he shows at a counter to
 * link his file there.
 */
class CustomerAccountController extends Controller
{
    public function __construct(
        private readonly CustomerFiles $files,
        private readonly CreditLedger $ledger,
        private readonly PaymentMatching $payments,
    ) {}

    /** GET /customer-accounts — every business that keeps a file on me with something in it. */
    public function index(Request $request)
    {
        $me = (int) $request->user()->id;
        $files = Customer::query()->where('user_id', $me)->whereNull('anonymized_at')->get();
        $pending = Payment::query()->whereIn('customer_id', $files->pluck('id'))->where('status', Payment::PENDING)->get()->groupBy('customer_id');
        $withMoves = AccountMove::query()->whereIn('customer_id', $files->pluck('id'))->distinct()->pluck('customer_id')->flip();
        $names = User::query()->whereIn('id', $files->pluck('business_id'))->pluck('name', 'id');

        $rows = $files
            ->filter(fn ($c) => isset($withMoves[$c->id]) || isset($pending[$c->id]))
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'business_id' => $c->business_id,
                'business_name' => $names[$c->business_id] ?? null,
                'balance' => (float) $c->balance,
                'credit_limit' => $c->credit_limit,
                'payment_terms_days' => $c->payment_terms_days,
                'awaiting_me' => ($pending[$c->id] ?? collect())->where('started_by', Payment::BY_BUSINESS)->count(),
                'updated_at' => $c->updated_at,
            ])
            ->sortByDesc('balance')
            ->values();

        return $this->ok(['accounts' => $rows]);
    }

    /** GET /customer-accounts/{customer} — one business: the book with running balance, aging and payments. */
    public function show(Request $request, int $customer)
    {
        $c = $this->mine($request, $customer);
        $statement = $this->ledger->statement($c, null, null);

        return $this->ok([
            'account' => [
                'id' => $c->id,
                'business_id' => $c->business_id,
                'business_name' => optional(User::query()->find($c->business_id))->name,
                'balance' => (float) $c->balance,
                'credit_limit' => $c->credit_limit,
                'payment_terms_days' => $c->payment_terms_days,
                'aging' => $statement['aging'],
            ],
            'moves' => array_map(fn ($l) => array_intersect_key($l, array_flip(['id', 'kind', 'amount', 'debit', 'credit', 'balance', 'note', 'occurred_at'])), $statement['lines']),
            'payments' => Payment::query()->where('customer_id', $c->id)->latest('id')->limit(50)->get()->map(fn ($p) => $this->payments->view($p, Payment::BY_CUSTOMER))->values(),
        ]);
    }

    /** POST /customer-accounts/{customer}/payments — «دفعت»: I record a payment; the business confirms it. */
    public function storePayment(Request $request, int $customer)
    {
        $c = $this->mine($request, $customer);
        $payment = $this->payments->start($c, Payment::BY_CUSTOMER, (int) $request->user()->id, $request->validate(CustomerFileController::paymentRules()));

        return $this->ok(['payment' => $this->payments->view($payment, Payment::BY_CUSTOMER)], 201);
    }

    /** POST /customer-accounts/payments/{payment}/confirm|reject|cancel */
    public function confirmPayment(Request $request, int $payment)
    {
        $p = $this->myPayment($request, $payment);
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:64']]);

        return $this->ok(['payment' => $this->payments->view($this->payments->confirm($p, Payment::BY_CUSTOMER, (int) $request->user()->id, $data['reference'] ?? null), Payment::BY_CUSTOMER)]);
    }

    public function rejectPayment(Request $request, int $payment)
    {
        $p = $this->myPayment($request, $payment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->ok(['payment' => $this->payments->view($this->payments->reject($p, Payment::BY_CUSTOMER, $data['reason'] ?? null), Payment::BY_CUSTOMER)]);
    }

    public function cancelPayment(Request $request, int $payment)
    {
        $p = $this->myPayment($request, $payment);

        return $this->ok(['payment' => $this->payments->view($this->payments->cancel($p, Payment::BY_CUSTOMER), Payment::BY_CUSTOMER)]);
    }

    /** POST /customer-accounts/link-code — «كود حسابي» for 10 minutes. */
    public function linkCode(Request $request)
    {
        return $this->ok($this->files->linkCode((int) $request->user()->id));
    }

    private function mine(Request $request, int $id): Customer
    {
        return Customer::query()->where('user_id', (int) $request->user()->id)->whereNull('anonymized_at')->findOrFail($id);
    }

    private function myPayment(Request $request, int $id): Payment
    {
        $p = Payment::query()->findOrFail($id);
        $this->mine($request, (int) $p->customer_id);

        return $p;
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
