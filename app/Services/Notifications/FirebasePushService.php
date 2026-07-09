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

        $signature = '';
        openssl_sign($header . '.' . $claim, $signature, $config['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $header . '.' . $claim . '.' . $this->base64Url($signature);

        $response = Http::asForm()->post($config['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('access_token');
    }

    private function serviceAccount(): array
    {
        $json = env('FCM_SERVICE_ACCOUNT_JSON');

        if (! $json && env('FCM_SERVICE_ACCOUNT_PATH') && is_file(base_path(env('FCM_SERVICE_ACCOUNT_PATH')))) {
            $json = file_get_contents(base_path(env('FCM_SERVICE_ACCOUNT_PATH')));
        }

        $data = $json ? json_decode($json, true) : [];

        return is_array($data) ? $data : [];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
