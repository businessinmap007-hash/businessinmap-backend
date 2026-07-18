<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\JobFollow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A user follows job FIELDS — a whole root category or one specialty — and a
 * vacancy posted there notifies them live (JobFollowMatchingService, fired
 * from JobController::store). Mirrors OfferFollowController's shape but
 * without the price/audience axis a job doesn't have.
 */
final class JobFollowController extends Controller
{
    /** GET /api/v2/jobs/follows — my followed fields. */
    public function index(Request $request)
    {
        $user = $request->user();

        $rows = JobFollow::query()
            ->where('user_id', (int) $user->id)
            ->with(['category:id,name_ar,name_en', 'categoryChild:id,name_ar,name_en'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (JobFollow $f) => [
            'id' => $f->id,
            'category' => $f->category ? ['id' => $f->category->id, 'name' => $this->label($f->category)] : null,
            'category_child' => $f->categoryChild ? ['id' => $f->categoryChild->id, 'name' => $this->label($f->categoryChild)] : null,
            'is_active' => (bool) $f->is_active,
            'last_matched_at' => $f->last_matched_at?->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/jobs/follows — follow a category or a specialty. */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'category_child_id' => ['nullable', 'integer', Rule::exists('category_children_master', 'id')],
        ]);

        $categoryId = $data['category_id'] ?? null;
        $childId = $data['category_child_id'] ?? null;

        if (! $categoryId && ! $childId) {
            abort(422, 'Follow a category or a specialty — one of category_id / category_child_id is required.');
        }

        if ($categoryId && $childId) {
            $belongs = DB::table('category_parent_child')
                ->where('parent_id', $categoryId)
                ->where('child_id', $childId)
                ->exists();

            if (! $belongs) {
                abort(422, 'category_child_id does not belong to category_id.');
            }
        }

        // Derive the parent for a child-only follow, so the follow always
        // records which root it sits under.
        if ($childId && ! $categoryId) {
            $categoryId = DB::table('category_parent_child')->where('child_id', $childId)->value('parent_id');
        }

        $follow = JobFollow::query()->updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'category_id' => $categoryId,
                'category_child_id' => $childId,
            ],
            ['is_active' => true, 'meta' => ['source' => 'api_v2_job_follows']]
        );

        return response()->json(['success' => true, 'data' => ['follow' => ['id' => $follow->id]]], 201);
    }

    /** DELETE /api/v2/jobs/follows/{follow} — unfollow. */
    public function destroy(Request $request, int $follow)
    {
        $user = $request->user();

        JobFollow::query()
            ->where('id', $follow)
            ->where('user_id', (int) $user->id)
            ->firstOrFail()
            ->delete();

        return response()->json(['success' => true, 'message' => 'Unfollowed.']);
    }

    private function label($model): ?string
    {
        $ar = trim((string) ($model->name_ar ?? ''));
        $en = trim((string) ($model->name_en ?? ''));

        return $ar !== '' ? $ar : ($en !== '' ? $en : null);
    }
}
