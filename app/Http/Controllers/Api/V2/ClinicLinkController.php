<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicLink;
use App\Models\User;
use App\Services\Clinics\ClinicLinkService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * A doctor links their own clinic accounts together (mutual consent), so a
 * patient who opens either one sees one unified schedule. See ClinicLinkService.
 */
class ClinicLinkController extends Controller
{
    public function __construct(private readonly ClinicLinkService $service)
    {
    }

    /** GET /api/v2/business/clinic-links — my own: sent/received pending, and accepted. */
    public function index(Request $request)
    {
        $links = $this->service->linksFor(BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => [
            'sent_pending' => $links['sent_pending']->map(fn (ClinicLink $l) => $this->serialize($l))->values(),
            'received_pending' => $links['received_pending']->map(fn (ClinicLink $l) => $this->serialize($l))->values(),
            'accepted' => $links['accepted']->map(fn (ClinicLink $l) => $this->serialize($l))->values(),
        ]]);
    }

    /** POST /api/v2/business/clinic-links — request a link to another clinic account. */
    public function store(Request $request)
    {
        $clinicId = BusinessContext::id($request);

        $data = $request->validate([
            'target_id' => ['required', 'integer', 'exists:users,id', 'different:' . $clinicId],
        ]);

        $requester = User::query()->findOrFail($clinicId);
        $target = User::query()->findOrFail((int) $data['target_id']);

        $link = $this->service->request($requester, $target);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب الربط.'),
            'data' => ['link' => $this->serialize($link->fresh(['requester', 'target']))],
        ], 201);
    }

    /** POST /api/v2/business/clinic-links/{link}/accept — the TARGET side accepts. */
    public function accept(Request $request, int $link)
    {
        $row = ClinicLink::query()->findOrFail($link);
        $actor = User::query()->findOrFail(BusinessContext::id($request));

        $row = $this->service->accept($row, $actor);

        return response()->json([
            'success' => true,
            'message' => __('تم قبول الربط.'),
            'data' => ['link' => $this->serialize($row->fresh(['requester', 'target']))],
        ]);
    }

    /** DELETE /api/v2/business/clinic-links/{link} — decline a pending one, or unlink an accepted one. */
    public function destroy(Request $request, int $link)
    {
        $row = ClinicLink::query()->findOrFail($link);
        $actor = User::query()->findOrFail(BusinessContext::id($request));

        $this->service->remove($row, $actor);

        return response()->json(['success' => true, 'message' => __('تم إلغاء الربط.')]);
    }

    private function serialize(ClinicLink $l): array
    {
        return [
            'id' => (int) $l->id,
            'status' => (string) $l->status,
            'requester' => $l->requester ? ['id' => (int) $l->requester->id, 'name' => $l->requester->displayName()] : ['id' => (int) $l->requester_id],
            'target' => $l->target ? ['id' => (int) $l->target->id, 'name' => $l->target->displayName()] : ['id' => (int) $l->target_id],
        ];
    }
}
