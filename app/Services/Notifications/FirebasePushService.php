<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\UserPushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class FirebasePushService
{
    public function sendToUser(int $userId, AppNotification $notification, array $payload = []): array
    {
        // Read from user_push_tokens — the live device-token store populated by
        // POST api/v2/push-tokens (PushTokenController). The legacy
        // user_device_tokens table is unused/unmigrated.
        $tokens = UserPushToken::query()
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'no_active_device_tokens'];
        }

        $accessToken = $this->accessToken();
        $projectId = $this->projectId();

        if (! $accessToken || ! $projectId) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'firebase_not_configured'];
        }

        $sent = 0;
        $failed = 0;
        $responses = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $notification->displayTitle(),
                            'body' => $notification->displayBody(),
                        ],
                        'data' => array_map('strval', array_filter([
                            'notification_id' => $notification->id,
                            'event_key' => $payload['event_key'] ?? '',
                            'type' => $notification->type,
                            'action_type' => $notification->action_type,
                            'action_url' => $notification->action_url,
                            'sound_key' => $payload['sound_key'] ?? '',
                        ], fn ($v) => $v !== null)),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => $payload['android_channel_id'] ?? 'bim_orders',
                                'sound' => $payload['sound_key'] ?? 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => ($payload['sound_key'] ?? 'default') . '.caf',
                                ],
                            ],
                        ],
                    ],
                ]);

            $ok = $response->successful();
            $sent += $ok ? 1 : 0;
            $failed += $ok ? 0 : 1;
            $responses[] = [
                'ok' => $ok,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ];
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false, 'responses' => $responses];
    }

    private function projectId(): ?string
    {
        $config = $this->serviceAccount();

        return $config['project_id'] ?? env('FCM_PROJECT_ID');
    }

    private function accessToken(): ?string
    {
        $config = $this->serviceAccount();

        if (empty($config['client_email']) || empty($config['private_key']) || empty($config['token_uri'])) {
            return env('FCM_ACCESS_TOKEN') ?: null;
        }

        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->base64Url(json_encode([
            'iss' => $config['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $config['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        // Guard against a malformed/pasted key so the admin "test" button and any
        // live send fail gracefully (null) instead of emitting an openssl warning.
        $privateKey = openssl_pkey_get_private($config['private_key']);
        if ($privateKey === false) {
            return null;
        }

        $signature = '';
        if (! openssl_sign($header . '.' . $claim, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = $header . '.' . $claim . '.' . $this->base64Url($signature);

        try {
            $response = Http::asForm()->post($config['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Attempt to obtain an FCM access token from the current credentials. Used by
     * the AdminV2 settings page to confirm a pasted service-account JSON is valid
     * before go-live.
     *
     * @return array{ok: bool, project_id: ?string, reason: string}
     */
    public function verifyCredentials(): array
    {
        $projectId = $this->projectId();

        if (! $projectId) {
            return ['ok' => false, 'project_id' => null, 'reason' => 'no_project_id'];
        }

        $token = $this->accessToken();

        if (! $token) {
            return ['ok' => false, 'project_id' => $projectId, 'reason' => 'token_exchange_failed'];
        }

        return ['ok' => true, 'project_id' => $projectId, 'reason' => 'ok'];
    }

    private function serviceAccount(): array
    {
        // Prefer the runtime, admin-editable credential (encrypted in push_settings),
        // then fall back to the env baseline.
        $account = app(PushSettingsService::class)->firebaseServiceAccount();

        return ! empty($account) ? $account : [];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
