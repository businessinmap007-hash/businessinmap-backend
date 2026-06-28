<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Commercial\BusinessOffersSubscriptionService;
use App\Services\WalletLedgerService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class BusinessOffersSubscriptionController extends Controller
{
    public function form(Request $request, BusinessOffersSubscriptionService $offersService)
    {
        $businessId = (int) $request->get('business_id', 0);

        $business = $businessId
            ? User::query()->where('type', User::TYPE_BUSINESS)->find($businessId)
            : null;

        $businesses = User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'category_id', 'category_child_id']);

        $service = $this->platformService();
        $rules = $offersService->rules();
        $usage = $business ? $offersService->usage((int) $business->id) : null;
        $subscription = $business && $service ? $this->subscriptionRow((int) $business->id, (int) $service->id) : null;
        $wallet = $business ? Wallet::query()->where('user_id', (int) $business->id)->first() : null;

        return view('admin-v2.business-offers-subscriptions.form', compact(
            'business',
            'businesses',
            'service',
            'rules',
            'usage',
            'subscription',
            'wallet'
        ));
    }

    public function activate(
        Request $request,
        BusinessOffersSubscriptionService $offersService,
        WalletLedgerService $ledger
    ) {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'charge_wallet' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $business = User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->findOrFail((int) $data['business_id']);

        $service = $this->platformService();

        if (! $service) {
            throw ValidationException::withMessages([
                'business_id' => 'خدمة business_offers غير موجودة أو غير مفعلة في platform_services.',
            ]);
        }

        $rules = $offersService->rules();
        $fee = round((float) ($rules['fixed_fee'] ?? 20), 2);
        $durationDays = max((int) ($rules['duration_days'] ?? 30), 1);
        $chargeWallet = $request->boolean('charge_wallet', true) && $fee > 0;

        $walletTx = DB::transaction(function () use ($business, $service, $rules, $fee, $durationDays, $chargeWallet, $ledger, $data) {
            $walletTx = null;

            if ($chargeWallet) {
                $wallet = Wallet::query()->firstOrCreate(
                    ['user_id' => (int) $business->id],
                    ['balance' => 0, 'locked_balance' => 0, 'status' => 'active']
                );

                $walletTx = $ledger->withdraw(
                    walletId: (int) $wallet->id,
                    userId: (int) $business->id,
                    amount: $fee,
                    op: [
                        'type' => 'platform_service_fee',
                        'reference_type' => 'platform_service',
                        'reference_id' => (string) $service->id,
                        'idempotency_key' => 'business_offers_subscription:' . $business->id . ':' . now()->format('YmdHis') . ':' . uniqid(),
                        'meta' => [
                            'source' => 'admin-v2',
                            'service_key' => BusinessOffersSubscriptionService::SERVICE_KEY,
                            'platform_service_id' => (int) $service->id,
                            'business_id' => (int) $business->id,
                            'admin_id' => auth()->id(),
                            'rules' => $rules,
                            'note' => $data['note'] ?? null,
                        ],
                    ]
                );
            }

            $this->upsertSubscription(
                businessId: (int) $business->id,
                serviceId: (int) $service->id,
                rules: $rules,
                durationDays: $durationDays,
                walletTransactionId: $walletTx ? (int) $walletTx->id : null,
                chargeWallet: $chargeWallet
            );

            return $walletTx;
        });

        $message = 'تم تفعيل اشتراك العروض التجارية للبزنس.';

        if ($walletTx) {
            $message .= ' وتم خصم الرسوم من المحفظة.';
        }

        return redirect()
            ->route('admin.business-offers-subscriptions.form', ['business_id' => (int) $business->id])
            ->with('success', $message);
    }

    public function deactivate(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $service = $this->platformService();

        if (! $service) {
            return back()->withErrors('خدمة business_offers غير موجودة.');
        }

        if (! Schema::hasTable('user_platform_service')) {
            return back()->withErrors('جدول user_platform_service غير موجود.');
        }

        $updates = [];

        if (Schema::hasColumn('user_platform_service', 'is_active')) {
            $updates['is_active'] = 0;
        }

        if (Schema::hasColumn('user_platform_service', 'status')) {
            $updates['status'] = 'paused';
        }

        if (Schema::hasColumn('user_platform_service', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        if ($updates === []) {
            DB::table('user_platform_service')
                ->where('user_id', (int) $data['business_id'])
                ->where('platform_service_id', (int) $service->id)
                ->delete();
        } else {
            DB::table('user_platform_service')
                ->where('user_id', (int) $data['business_id'])
                ->where('platform_service_id', (int) $service->id)
                ->update($updates);
        }

        return back()->with('success', 'تم إيقاف اشتراك العروض التجارية.');
    }

    private function platformService(): ?object
    {
        return DB::table('platform_services')
            ->where('key', BusinessOffersSubscriptionService::SERVICE_KEY)
            ->where('is_active', 1)
            ->first();
    }

    private function subscriptionRow(int $businessId, int $serviceId): ?object
    {
        if (! Schema::hasTable('user_platform_service')) {
            return null;
        }

        return DB::table('user_platform_service')
            ->where('user_id', $businessId)
            ->where('platform_service_id', $serviceId)
            ->first();
    }

    private function upsertSubscription(
        int $businessId,
        int $serviceId,
        array $rules,
        int $durationDays,
        ?int $walletTransactionId,
        bool $chargeWallet
    ): void {
        if (! Schema::hasTable('user_platform_service')) {
            throw ValidationException::withMessages([
                'business_id' => 'جدول user_platform_service غير موجود.',
            ]);
        }

        $now = now();
        $values = [
            'user_id' => $businessId,
            'platform_service_id' => $serviceId,
        ];

        if (Schema::hasColumn('user_platform_service', 'is_active')) {
            $values['is_active'] = 1;
        }

        if (Schema::hasColumn('user_platform_service', 'status')) {
            $values['status'] = 'active';
        }

        if (Schema::hasColumn('user_platform_service', 'starts_at')) {
            $values['starts_at'] = $now;
        }

        if (Schema::hasColumn('user_platform_service', 'ends_at')) {
            $values['ends_at'] = $now->copy()->addDays($durationDays);
        }

        $meta = [
            'source' => 'business_offers_subscription_admin',
            'service_key' => BusinessOffersSubscriptionService::SERVICE_KEY,
            'rules' => $rules,
            'wallet_transaction_id' => $walletTransactionId,
            'charge_wallet' => $chargeWallet,
            'activated_by' => auth()->id(),
            'activated_at' => $now->toDateTimeString(),
        ];

        foreach (['meta', 'rules', 'settings'] as $column) {
            if (Schema::hasColumn('user_platform_service', $column)) {
                $values[$column] = json_encode($column === 'meta' ? $meta : $rules, JSON_UNESCAPED_UNICODE);
            }
        }

        if (Schema::hasColumn('user_platform_service', 'created_at')) {
            $values['created_at'] = $now;
        }

        if (Schema::hasColumn('user_platform_service', 'updated_at')) {
            $values['updated_at'] = $now;
        }

        $existing = DB::table('user_platform_service')
            ->where('user_id', $businessId)
            ->where('platform_service_id', $serviceId)
            ->first();

        if ($existing) {
            unset($values['created_at']);

            DB::table('user_platform_service')
                ->where('user_id', $businessId)
                ->where('platform_service_id', $serviceId)
                ->update($values);

            return;
        }

        DB::table('user_platform_service')->insert($values);
    }
}
