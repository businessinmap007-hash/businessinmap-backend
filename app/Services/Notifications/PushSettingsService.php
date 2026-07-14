<?php

namespace App\Services\Notifications;

use App\Models\PushSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime, DB-backed store for Firebase (FCM) credentials, layered on top of the
 * .env baseline. An admin pastes the Firebase service-account JSON in the AdminV2
 * panel; FirebasePushService reads it from here — no redeploy, no .env edit. The
 * JSON contains a private key, so it is encrypted at rest.
 *
 * Resolution order (later wins): env (FCM_SERVICE_ACCOUNT_JSON / _PATH) → DB.
 */
final class PushSettingsService
{
    private const KEY_SERVICE_ACCOUNT = 'firebase.service_account_json';

    /** Keys of a Firebase service-account JSON that FirebasePushService needs. */
    private const REQUIRED_JSON_KEYS = ['project_id', 'client_email', 'private_key', 'token_uri'];

    /**
     * The effective Firebase service-account as an array, or [] when unset.
     * DB override wins over the env baseline.
     */
    public function firebaseServiceAccount(): array
    {
        $json = $this->firebaseServiceAccountJson();

        if (! $json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /** Raw service-account JSON string: DB override, else env baseline, else null. */
    public function firebaseServiceAccountJson(): ?string
    {
        if ($this->tableReady()) {
            $db = $this->get(self::KEY_SERVICE_ACCOUNT);
            if ($db !== null && $db !== '') {
                return $db;
            }
        }

        $env = (string) env('FCM_SERVICE_ACCOUNT_JSON', '');
        if ($env !== '') {
            return $env;
        }

        $path = (string) env('FCM_SERVICE_ACCOUNT_PATH', '');
        if ($path !== '' && is_file(base_path($path))) {
            return (string) file_get_contents(base_path($path));
        }

        return null;
    }

    /**
     * View model for the admin form. Never returns the secret JSON — only whether
     * it is configured, where it comes from, and the non-secret identifiers
     * (project_id / client_email) parsed out for confirmation.
     *
     * @return array{is_set: bool, source: string, project_id: ?string, client_email: ?string}
     */
    public function firebaseFormState(): array
    {
        $dbJson = $this->tableReady() ? $this->get(self::KEY_SERVICE_ACCOUNT) : null;
        $hasDb = $dbJson !== null && $dbJson !== '';

        $account = $this->firebaseServiceAccount();
        $envConfigured = (string) env('FCM_SERVICE_ACCOUNT_JSON', '') !== ''
            || ((string) env('FCM_SERVICE_ACCOUNT_PATH', '') !== '' && is_file(base_path((string) env('FCM_SERVICE_ACCOUNT_PATH'))));

        return [
            'is_set' => ! empty($account),
            'source' => $hasDb ? 'db' : ($envConfigured ? 'env' : 'none'),
            'project_id' => $account['project_id'] ?? null,
            'client_email' => $account['client_email'] ?? null,
        ];
    }

    /**
     * Persist an admin-supplied service-account JSON. Blank keeps the existing
     * value. Returns a validation error string, or null on success.
     */
    public function saveFirebase(?string $serviceAccountJson): ?string
    {
        $raw = trim((string) ($serviceAccountJson ?? ''));

        // Blank = keep the existing credential untouched.
        if ($raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return 'ملف JSON غير صالح — تأكّد من لصق محتوى ملف الحساب الخدمي كاملًا.';
        }

        foreach (self::REQUIRED_JSON_KEYS as $required) {
            if (empty($data[$required])) {
                return "ملف الحساب الخدمي ينقصه الحقل: {$required}.";
            }
        }

        $this->put(self::KEY_SERVICE_ACCOUNT, $raw, true);

        return null;
    }

    /** Decrypted value for a key, or null if unset. */
    private function get(string $key): ?string
    {
        $row = PushSetting::query()->where('key', $key)->first();

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

        PushSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_encrypted' => $secret],
        );
    }

    /** Guard so a fresh install (pre-migration) still falls back to env cleanly. */
    private function tableReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            $ready = Schema::hasTable('push_settings');
        }

        return $ready;
    }
}
