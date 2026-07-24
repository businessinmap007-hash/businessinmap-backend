<?php

namespace App\Services\Payments;

use App\Models\PaymentSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime, DB-backed store for payment gateway credentials, layered on top of
 * the .env / config baseline. An admin pastes live gateway codes in the AdminV2
 * panel; the gateway factory reads the merged config from here — no redeploy,
 * no .env edit. Secret values (security keys) are encrypted at rest.
 *
 * Merge order (later wins): config('services.fawry') → DB override.
 */
final class PaymentSettingsService
{
    /**
     * Fawry field map. `secret` fields are encrypted at rest and never rendered
     * back to the form; `config` is the env fallback used when there is no DB
     * override.
     *
     * @var array<string, array{secret: bool, config: string}>
     */
    private const FAWRY_FIELDS = [
        'base_url' => ['secret' => false, 'config' => 'services.fawry.base_url'],
        'merchant_code' => ['secret' => false, 'config' => 'services.fawry.merchant_code'],
        'security_key' => ['secret' => true, 'config' => 'services.fawry.security_key'],
        'currency' => ['secret' => false, 'config' => 'services.fawry.currency'],
        'return_url' => ['secret' => false, 'config' => 'services.fawry.return_url'],
    ];

    private const FAWRY_PREFIX = 'fawry.';

    /** Effective Fawry config for the gateway: env baseline overlaid with DB values. */
    public function fawryConfig(): array
    {
        $config = (array) config('services.fawry', []);

        if (! $this->tableReady()) {
            return $config;
        }

        foreach (array_keys(self::FAWRY_FIELDS) as $field) {
            $value = $this->get(self::FAWRY_PREFIX . $field);
            if ($value !== null && $value !== '') {
                $config[$field] = $value;
            }
        }

        return $config;
    }

    /**
     * View model for the admin form. Non-secret fields expose their current
     * value (DB override if any, else the env baseline). Secret fields expose
     * only whether a value is configured — never the value itself.
     *
     * @return array<string, array{secret: bool, value: string, is_set: bool, source: string}>
     */
    public function fawryFormState(): array
    {
        $state = [];

        foreach (self::FAWRY_FIELDS as $field => $meta) {
            $dbValue = $this->tableReady() ? $this->get(self::FAWRY_PREFIX . $field) : null;
            $envValue = (string) (config($meta['config']) ?? '');
            $hasDb = $dbValue !== null && $dbValue !== '';
            $effective = $hasDb ? (string) $dbValue : $envValue;

            $state[$field] = [
                'secret' => $meta['secret'],
                'value' => $meta['secret'] ? '' : $effective,
                'is_set' => $effective !== '',
                'source' => $hasDb ? 'db' : ($envValue !== '' ? 'env' : 'none'),
            ];
        }

        return $state;
    }

    /**
     * Persist admin-supplied Fawry values. Non-secret fields are stored as given
     * (empty clears the override → falls back to env). Secret fields are only
     * written when a non-empty value is supplied, so leaving the box blank keeps
     * the existing key.
     *
     * @param array<string, string|null> $input
     */
    public function saveFawry(array $input): void
    {
        foreach (self::FAWRY_FIELDS as $field => $meta) {
            $key = self::FAWRY_PREFIX . $field;
            $raw = array_key_exists($field, $input) ? trim((string) ($input[$field] ?? '')) : null;

            if ($meta['secret']) {
                // Blank secret = keep the existing key untouched.
                if ($raw === null || $raw === '') {
                    continue;
                }
                $this->put($key, $raw, true);
                continue;
            }

            if ($raw !== null) {
                $this->put($key, $raw, false);
            }
        }
    }

    /**
     * Whether the per-merchant sub-account routing feature is ON. When off, all
     * charges use the platform's own Fawry account (the default). Stored as a
     * plain '1'/'0' flag; defaults OFF so nothing changes until an admin enables it.
     */
    public function subMerchantEnabled(): bool
    {
        return $this->tableReady() && $this->get(self::FAWRY_PREFIX . 'submerchant_enabled') === '1';
    }

    /** Turn the sub-account routing feature on or off (admin action). */
    public function setSubMerchantEnabled(bool $on): void
    {
        $this->put(self::FAWRY_PREFIX . 'submerchant_enabled', $on ? '1' : '0', false);
    }

    /** Decrypted value for a key, or null if unset. */
    private function get(string $key): ?string
    {
        $row = PaymentSetting::query()->where('key', $key)->first();

        if (! $row) {
            return null;
        }

        if ($row->is_encrypted && $row->value !== null && $row->value !== '') {
            try {
                return Crypt::decryptString($row->value);
            } catch (DecryptException) {
                return null;
            }
        }

        return $row->value;
    }

    private function put(string $key, ?string $value, bool $secret): void
    {
        $stored = $secret && $value !== null && $value !== ''
            ? Crypt::encryptString($value)
            : $value;

        PaymentSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_encrypted' => $secret],
        );
    }

    /** Guard so a fresh install (pre-migration) still falls back to env cleanly. */
    private function tableReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            $ready = Schema::hasTable('payment_settings');
        }

        return $ready;
    }
}
