<?php

namespace App\Services\Payments;

use App\Models\MerchantPaymentAccount;
use Illuminate\Support\Facades\Schema;

/**
 * Owns per-merchant gateway sub-accounts: whether the feature is on, and the
 * effective Fawry config that routes a charge to a given merchant's sub-account.
 *
 * The routing config reuses the PLATFORM's non-secret settings (base_url,
 * return_url, currency) but swaps in the MERCHANT's own merchant_code +
 * security_key — so a FawryGateway built from it signs and bills the merchant's
 * account. Returns null (→ fall back to the platform gateway) whenever the
 * feature is off or the merchant is not fully configured.
 */
final class MerchantPaymentAccountService
{
    public function __construct(private readonly PaymentSettingsService $settings)
    {
    }

    public function isEnabled(): bool
    {
        return $this->settings->subMerchantEnabled();
    }

    /**
     * Fawry config that routes a charge to this business's sub-account, or null
     * to fall back to the platform account.
     *
     * @return array<string,mixed>|null
     */
    public function configFor(int $businessId): ?array
    {
        if (! $this->isEnabled() || ! Schema::hasTable('merchant_payment_accounts')) {
            return null;
        }

        $row = MerchantPaymentAccount::query()
            ->where('business_id', $businessId)
            ->where('gateway', 'fawry')
            ->where('is_active', true)
            ->first();

        if (! $row) {
            return null;
        }

        $code = (string) $row->merchant_code;
        $key = (string) $row->security_key; // decrypted by the model cast

        // A half-configured merchant must NOT silently fall through to the
        // platform's key with the merchant's code (that would mis-sign) — require both.
        if ($code === '' || $key === '') {
            return null;
        }

        $config = $this->settings->fawryConfig();
        $config['merchant_code'] = $code;
        $config['security_key'] = $key;

        return $config;
    }

    /**
     * Upsert a merchant's sub-account credentials. A blank security key keeps the
     * existing one (mirrors the platform settings form), so an admin can edit the
     * code or active flag without re-entering the secret.
     */
    public function save(int $businessId, ?string $merchantCode, ?string $securityKey, bool $isActive): void
    {
        $row = MerchantPaymentAccount::query()->firstOrNew([
            'business_id' => $businessId,
            'gateway' => 'fawry',
        ]);

        if ($merchantCode !== null) {
            $row->merchant_code = trim($merchantCode) ?: null;
        }

        $key = $securityKey !== null ? trim($securityKey) : '';
        if ($key !== '') {
            $row->security_key = $key;
        }

        $row->is_active = $isActive;
        $row->save();
    }

    /**
     * Admin view model for one business: never exposes the key itself, only
     * whether it is set.
     *
     * @return array{merchant_code: string, key_is_set: bool, is_active: bool}
     */
    public function formStateFor(int $businessId): array
    {
        $row = Schema::hasTable('merchant_payment_accounts')
            ? MerchantPaymentAccount::query()->where('business_id', $businessId)->where('gateway', 'fawry')->first()
            : null;

        return [
            'merchant_code' => (string) ($row->merchant_code ?? ''),
            'key_is_set' => $row !== null && (string) $row->security_key !== '',
            'is_active' => (bool) ($row->is_active ?? false),
        ];
    }
}
