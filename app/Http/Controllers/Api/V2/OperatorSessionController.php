<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessOperatorSession;
use App\Services\Notifications\BusinessOperatorSessionService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * Operator presence: a business marks itself "online" while an operator watches
 * a service screen, so RealtimeNotificationService will actually push realtime
 * events to it. Session self-expires; the client heartbeats to stay live.
 */
final class OperatorSessionController extends Controller
{
    public function __construct(private readonly BusinessOperatorSessionService $sessions)
    {
    }

    /** POST /api/v2/operator/session/start */
    public function start(Request $request)
    {
        $data = $this->validated($request);

        $session = $this->sessions->start(
            BusinessContext::id($request),
            (int) $request->user()->id,
            $data['service_type'] ?? null,
            $data['screen'] ?? null,
            $data['expected_minutes'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $this->present($session)], 201);
    }

    /** POST /api/v2/operator/session/heartbeat */
    public function heartbeat(Request $request)
    {
        $data = $this->validated($request);

        $session = $this->sessions->heartbeat(
            BusinessContext::id($request),
            (int) $request->user()->id,
            $data['service_type'] ?? null,
            $data['expected_minutes'] ?? null,
        );

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => __('لا توجد جلسة نشطة. ابدأ جلسة أولًا.'),
            ], 409);
        }

        return response()->json(['success' => true, 'data' => $this->present($session)]);
    }

    /** POST /api/v2/operator/session/end */
    public function end(Request $request)
    {
        $data = $request->validate([
            'service_type' => ['nullable', 'string', 'max:40'],
        ]);

        // Pass service_type through only when the caller sent it, so end() can
        // scope to one screen or close all of the operator's sessions.
        $closed = array_key_exists('service_type', $data)
            ? $this->sessions->end(BusinessContext::id($request), (int) $request->user()->id, $data['service_type'])
            : $this->sessions->end(BusinessContext::id($request), (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => ['closed' => $closed]]);
    }

    /** GET /api/v2/operator/session */
    public function show(Request $request)
    {
        $data = $request->validate(['service_type' => ['nullable', 'string', 'max:40']]);

        $session = $this->sessions->current(
            BusinessContext::id($request),
            (int) $request->user()->id,
            $data['service_type'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'online' => (bool) $session,
                'session' => $session ? $this->present($session) : null,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'service_type' => ['nullable', 'string', 'max:40'],
            'screen' => ['nullable', 'string', 'max:60'],
            'expected_minutes' => ['nullable', 'integer', 'min:1', 'max:' . BusinessOperatorSessionService::MAX_MINUTES],
        ]);
    }

    /** @return array<string,mixed> */
    private function present(BusinessOperatorSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'status' => (string) $session->status,
            'service_type' => $session->service_type,
            'screen' => $session->screen,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'expected_until' => optional($session->expected_until)->toIso8601String(),
            'last_activity_at' => optional($session->last_activity_at)->toIso8601String(),
        ];
    }
}
