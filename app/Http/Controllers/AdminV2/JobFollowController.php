<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Apply;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\JobFollow;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Oversight for the jobs board: the platform-wide counters, plus who follows
 * which field.
 *
 * Read-only on purpose. A follow is the user's own subscription, managed from
 * the app (`/api/v2/jobs/follows`) — an admin watches demand here, it does not
 * edit somebody's notification preferences for them.
 *
 * The interesting column is demand-vs-supply: a field with many followers and
 * no open jobs is a gap worth selling into.
 */
class JobFollowController extends Controller
{
    private const TOP_FIELDS_LIMIT = 25;

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        return view('admin-v2.jobs.follows', [
            'q' => $q,
            'stats' => $this->platformStats(),
            'topFields' => $this->topFollowedFields(),
            'follows' => $this->followsList($request, $q),
        ]);
    }

    /** Mirrors GET /api/v2/jobs/stats so the two never drift apart. */
    private function platformStats(): array
    {
        $jobApplies = fn () => Apply::query()->whereHas('post', fn ($w) => $w->where('type', 'job'));

        return [
            'jobs_posted' => Post::query()->jobs()->count(),
            'jobs_open' => Post::query()->openJobs()->count(),
            'applicants_total' => $jobApplies()->count(),
            'approved_total' => $jobApplies()->whereNotNull('approved_at')->count(),
            'follows_active' => JobFollow::query()->where('is_active', true)->count(),
            'businesses_hiring' => Post::query()->openJobs()->distinct()->count('user_id'),
        ];
    }

    /**
     * Followers per field, next to how many open jobs that field actually has.
     * A child-level follow counts against the child's open jobs; a root-level
     * one against the whole category.
     */
    private function topFollowedFields(): array
    {
        $rows = JobFollow::query()
            ->where('is_active', true)
            ->select('category_id', 'category_child_id', DB::raw('COUNT(*) as followers'))
            ->groupBy('category_id', 'category_child_id')
            ->orderByDesc('followers')
            ->limit(self::TOP_FIELDS_LIMIT)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $openByChild = Post::query()->openJobs()->whereNotNull('category_child_id')
            ->select('category_child_id', DB::raw('COUNT(*) as c'))
            ->groupBy('category_child_id')
            ->pluck('c', 'category_child_id');

        $openByCategory = Post::query()->openJobs()->whereNotNull('category_id')
            ->select('category_id', DB::raw('COUNT(*) as c'))
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        $categories = Category::query()
            ->whereIn('id', $rows->pluck('category_id')->filter()->unique())
            ->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        $children = CategoryChild::query()
            ->whereIn('id', $rows->pluck('category_child_id')->filter()->unique())
            ->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        return $rows->map(function ($row) use ($categories, $children, $openByChild, $openByCategory) {
            $childId = $row->category_child_id;
            $catId = $row->category_id;

            return [
                'category' => $this->label($categories->get($catId)),
                'child' => $childId ? $this->label($children->get($childId)) : null,
                'followers' => (int) $row->followers,
                'open_jobs' => (int) ($childId
                    ? ($openByChild[$childId] ?? 0)
                    : ($openByCategory[$catId] ?? 0)),
            ];
        })->all();
    }

    private function followsList(Request $request, string $q)
    {
        return JobFollow::query()
            ->with([
                'user:id,name,phone',
                'category:id,name_ar,name_en',
                'categoryChild:id,name_ar,name_en',
            ])
            ->when($q !== '', fn ($w) => $w->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->paginate(50)
            ->appends($request->query());
    }

    private function label($model): ?string
    {
        if (! $model) {
            return null;
        }

        $ar = trim((string) ($model->name_ar ?? ''));
        $en = trim((string) ($model->name_en ?? ''));

        return $ar !== '' ? $ar : ($en !== '' ? $en : null);
    }
}
