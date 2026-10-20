<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\OperationChatService;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProjectShowcaseService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * «نتائج موثَّقة»: the business asks to show a project's progress as a post; the customer — a party of the order / booking the
 * project is linked to — answers; either may take it down. Two doors, like the agreement's.
 */
class ProjectShowcaseController extends Controller
{
    public function __construct(
        private readonly ProjectShowcaseService $showcases,
        private readonly ProjectService $projects,
        private readonly OperationChatService $operations
    ) {
    }

    /** GET business/projects/{project}/showcase */
    public function show(Request $request, int $project)
    {
        return response()->json(['success' => true, 'data' => ['showcase' => $this->showcases->block($this->owned($request, $project))]]);
    }

    /** POST business/projects/{project}/showcase {public_title, note?, show_photos?} — ask the customer to let the project be shown. */
    public function request(Request $request, int $project)
    {
        $data = $request->validate([
            'public_title' => ['required', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:500'],
            'show_photos' => ['nullable', 'boolean'],
        ]);
        $row = $this->owned($request, $project);

        $this->showcases->request($row, $request->user(), $data['public_title'], $data['note'] ?? null, (bool) ($data['show_photos'] ?? false));

        return response()->json(['success' => true, 'message' => __('أُرسل طلب النشر إلى العميل.'), 'data' => ['showcase' => $this->showcases->block($row)]], 201);
    }

    /** DELETE business/projects/{project}/showcase — the business takes it down (or cancels the request). */
    public function businessWithdraw(Request $request, int $project)
    {
        $row = $this->owned($request, $project);
        $this->showcases->withdraw($row, $request->user());

        return response()->json(['success' => true, 'message' => __('أُوقف عرض المشروع.'), 'data' => ['showcase' => $this->showcases->block($row)]]);
    }

    public function approve(Request $request, string $type, int $id)
    {
        $project = $this->forCustomer($request, $type, $id);
        $this->showcases->decide($project, $request->user(), true);

        return $this->customerReply($project, __('وافقت على عرض المشروع.'));
    }

    public function decline(Request $request, string $type, int $id)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $project = $this->forCustomer($request, $type, $id);
        $this->showcases->decide($project, $request->user(), false, $data['note'] ?? null);

        return $this->customerReply($project, __('رفضت عرض المشروع.'));
    }

    public function customerWithdraw(Request $request, string $type, int $id)
    {
        $project = $this->forCustomer($request, $type, $id);
        $this->showcases->withdraw($project, $request->user());

        return $this->customerReply($project, __('أُوقف عرض المشروع.'));
    }

    private function owned(Request $request, int $projectId): Project
    {
        return Project::query()->where('id', $projectId)->where('business_id', BusinessContext::id($request))->firstOrFail();
    }

    private function forCustomer(Request $request, string $type, int $id): Project
    {
        $operation = $this->operations->resolve($type, $id);
        $this->operations->assertParty($operation, (int) $request->user()->id);

        return Project::query()
            ->where('operation_type', $operation->getMorphClass())->where('operation_id', $operation->getKey())
            ->firstOrFail();
    }

    private function customerReply(Project $project, string $message)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $this->projects->customerView($project->fresh())]);
    }
}
