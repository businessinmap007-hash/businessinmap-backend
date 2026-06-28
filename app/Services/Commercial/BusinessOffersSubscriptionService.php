<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\PlatformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class BusinessOffersSubscriptionService
{
    public const SERVICE_KEY = 'business_offers';

    public function service(): ?object
    {
        if (! Schema::hasTable('platform_services')) {
            return null;
        }

        return DB::table('platform_services')
            ->where('key', self::SERVICE_KEY)
            ->where('is_active', 1)
            ->first();
    }

    public function rules(): array
    {
        $service = $this->service();
        $rules = [];

        if ($service && property_exists($service, 'rules') && $service->rules) {
            $decoded = json_decode((string) $service->rules, true);
            $rules = is_array($decoded) ? $decoded : [];
        }

        if ($service && property_exists($service, 'meta') && $service->meta) {
            $decoded = json_decode((string) $service->meta, true);

            if (is_array($decoded)) {
                $rules = array_merge($rules, $decoded['offers_rules'] ?? []);
            }
        }

        return array_merge([
            'requires_subscription' => true,
            'free_trial_enabled' => false,
            'max_active_offers' => 5,
            'duration_days' => 30,
            'fixed_fee' => 20,
            'currency' => 'EGP',
            'count_sources' => [
                CommercialOffer::SOURCE_DIRECT,
                CommercialOffer::SOURCE_RESELLER,
                CommercialOffer::SOURCE_PROMOTION,
                CommercialOffer::SOURCE_MARKETPLACE,
            ],
        ], $rules);
    }

    public function ensureCanSaveOffer(int $sellerBusinessId, array $data, ?int $ignoreOfferId = null): void
    {
        if ($sellerBusinessId <= 0) {
            throw ValidationException::withMessages([
                'seller_business_id' => 'يجب تحديد البزنس صاحب العرض.',
            ]);
        }

        $sourceType = (string) ($data['source_type'] ?? '');

        if ($sourceType === CommercialOffer::SOURCE_ALLOCATION) {
            return;
        }

        $rules = $this->rules();
        $service = $this->service();

        if (! $service) {
            throw ValidationException::withMessages([
                'source_type' => 'خدمة العروض التجارية business_offers غير مفعلة في platform_services.',
            ]);
        }

        if ((bool) ($rules['requires_subscription'] ?? true) && ! $this->businessSubscribed($sellerBusinessId, (int) $service->id)) {
            throw ValidationException::withMessages([
                'seller_business_id' => 'هذا البزنس غير مشترك في خدمة العروض التجارية.',
            ]);
        }

        $status = (string) ($data['status'] ?? CommercialOffer::STATUS_ACTIVE);

        if ($status !== CommercialOffer::STATUS_ACTIVE) {
            return;
        }

        $countSources = $rules['count_sources'] ?? [];
        $countSources = is_array($countSources) ? $countSources : [];

        if (! in_array($sourceType, $countSources, true)) {
            return;
        }

        $limit = max((int) ($rules['max_active_offers'] ?? 5), 0);

        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'status' => 'اشتراك العروض لا يسمح بأي عروض فعالة حاليًا.',
            ]);
        }

        $activeCount = $this->activeOffersCount($sellerBusinessId, $countSources, $ignoreOfferId);

        if ($activeCount >= $limit) {
            throw ValidationException::withMessages([
                'status' => "تم الوصول للحد الأقصى للعروض الفعالة لهذا الاشتراك ({$limit}).",
            ]);
        }
    }

    public function activeOffersCount(int $sellerBusinessId, array $sources = [], ?int $ignoreOfferId = null): int
    {
        $query = CommercialOffer::query()
            ->where('seller_business_id', $sellerBusinessId)
            ->where('status', CommercialOffer::STATUS_ACTIVE);

        if ($sources) {
            $query->whereIn('source_type', $sources);
        }

        if ($ignoreOfferId) {
            $query->where('id', '!=', $ignoreOfferId);
        }

        return (int) $query->count();
    }

    public function usage(int $sellerBusinessId): array
    {
        $rules = $this->rules();
        $sources = $rules['count_sources'] ?? [];
        $sources = is_array($sources) ? $sources : [];
        $limit = max((int) ($rules['max_active_offers'] ?? 5), 0);
        $active = $this->activeOffersCount($sellerBusinessId, $sources);

        return [
            'service_key' => self::SERVICE_KEY,
            'max_active_offers' => $limit,
            'active_offers' => $active,
            'remaining_offers' => max($limit - $active, 0),
            'duration_days' => (int) ($rules['duration_days'] ?? 30),
            'fixed_fee' => (float) ($rules['fixed_fee'] ?? 20),
            'currency' => (string) ($rules['currency'] ?? 'EGP'),
            'requires_subscription' => (bool) ($rules['requires_subscription'] ?? true),
        ];
    }

    public function businessSubscribed(int $sellerBusinessId, int $platformServiceId): bool
    {
        if (! Schema::hasTable('user_platform_service')) {
            return false;
        }

        $query = DB::table('user_platform_service')
            ->where('user_id', $sellerBusinessId)
            ->where('platform_service_id', $platformServiceId);

        if (Schema::hasColumn('user_platform_service', 'is_active')) {
            $query->where('is_active', 1);
        }

        if (Schema::hasColumn('user_platform_service', 'status')) {
            $query->whereIn('status', ['active', 'enabled', 'approved']);
        }

        if (Schema::hasColumn('user_platform_service', 'starts_at')) {
            $query->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
        }

        if (Schema::hasColumn('user_platform_service', 'ends_at')) {
            $query->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        }

        return $query->exists();
    }
}
