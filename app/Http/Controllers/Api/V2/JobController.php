<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Apply;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\JobPost;
use App\Models\Post;
use App\Services\Jobs\JobFollowMatchingService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Job postings: a business advertises a vacancy (any field), a client
 * applies. Jobs are `posts` with type='job' (the v1 shape, given real
 * fields — see 2026_08_08_000000_add_job_fields_to_posts). Deliberately its
 * own surface, not a platform service: there is no operation being executed,
 * no fee split, no rating — just an ad and applications, closer to Offers
 * than to Booking/Menu/Delivery.
 *
 * Visibility rule: the public sees a job and an applicant COUNT; only the
 * posting business sees who applied.
 */
final class JobController extends Controller
{
    /** GET /api/v2/jobs — public, open (active + not expired) jobs. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_child_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $jobs = Post::query()->openJobs()
            ->with(['user:id,name,logo', 'category:id,name_ar,name_en', 'categoryChild:id,name_ar,name_en'])
            ->when(! empty($data['category_id']), fn ($w) => $w->where('category_id', $data['category_id']))
            ->when(! empty($data['category_child_id']), fn ($w) => $w->where('category_child_id', $data['category_child_id']))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('title_ar', 'like', "%{$q}%")
                ->orWhere('title_en', 'like', "%{$q}%")))
            ->withCount('applies')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->appends($request->query());

        $jobs->getCollection()->transform(fn (Post $p) => $this->publicShape($p));

        return response()->json(['success' => true, 'data' => $jobs]);
    }

    /** GET /api/v2/jobs/{post} — public job detail + applicant count only. */
    public function show(JobPost $post)
    {
        $post->loadMissing(['user:id,name,logo', 'category:id,name_ar,name_en', 'categoryChild:id,name_ar,name_en']);
        $post->loadCount('applies');

        return response()->json(['success' => true, 'data' => $this->publicShape($post, withBody: true)]);
    }

    /**
     * GET /api/v2/jobs/categories — only categories/children that actually
     * have an open job, with a count each (parent total = sum of children).
     */
    public function categories()
    {
        $childCounts = Post::query()->openJobs()
            ->whereNotNull('category_id')->whereNotNull('category_child_id')
            ->groupBy('category_id', 'category_child_id')
            ->select('category_id', 'category_child_id', DB::raw('COUNT(*) as c'))
            ->get();

        $rootOnly = Post::query()->openJobs()
            ->whereNotNull('category_id')->whereNull('category_child_id')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('COUNT(*) as c'))
            ->get();

        $categoryIds = $childCounts->pluck('category_id')->merge($rootOnly->pluck('category_id'))->unique();
        $childIds = $childCounts->pluck('category_child_id')->unique();

        $categories = Category::query()->whereIn('id', $categoryIds)->get(['id', 'name_ar', 'name_en'])->keyBy('id');
        $children = CategoryChild::query()->whereIn('id', $childIds)->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        $byRoot = [];
        foreach ($childCounts as $row) {
            $byRoot[$row->category_id]['total'] = ($byRoot[$row->category_id]['total'] ?? 0) + (int) $row->c;
            $byRoot[$row->category_id]['children'][] = [
                'id' => (int) $row->category_child_id,
                'name' => $this->label($children->get($row->category_child_id)),
                'jobs_count' => (int) $row->c,
            ];
        }
        foreach ($rootOnly as $row) {
            $byRoot[$row->category_id]['total'] = ($byRoot[$row->category_id]['total'] ?? 0) + (int) $row->c;
        }

        $result = [];
        foreach ($byRoot as $catId => $info) {
            $cat = $categories->get($catId);
            if (! $cat) {
                continue;
            }
            $result[] = [
                'id' => (int) $catId,
                'name' => $this->label($cat),
                'jobs_count' => $info['total'],
                'children' => $info['children'] ?? [],
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** POST /api/v2/jobs — a business posts a vacancy. */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isBusiness()) {
            abort(403, 'Only a business account can post a job.');
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'category_child_id' => ['nullable', 'integer', Rule::exists('category_children_master', 'id')],
            'title_ar' => ['required_without:title_en', 'nullable', 'string', 'max:191'],
            'title_en' => ['required_without:title_ar', 'nullable', 'string', 'max:191'],
            'body' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'salary' => ['nullable', 'string', 'max:191'],
            'interview_starts_at' => ['nullable', 'date'],
            'expire_at' => ['nullable', 'date', 'after_or_equal:interview_starts_at'],
        ]);

        if (! empty($data['category_child_id'])) {
            $belongs = DB::table('category_parent_child')
                ->where('parent_id', $data['category_id'])
                ->where('child_id', $data['category_child_id'])
                ->exists();

            if (! $belongs) {
                abort(422, 'category_child_id does not belong to category_id.');
            }
        }

        $data['user_id'] = $user->id;
        $data['is_active'] = true;
        $data['share_count'] = 0;

        $post = JobPost::create($data);

        // Live-notify everyone following this job's field.
        app(JobFollowMatchingService::class)->notifyForJob($post);

        return response()->json(['success' => true, 'data' => $this->publicShape($post, withBody: true)], 201);
    }

    /** POST /api/v2/jobs/{post}/apply — a client applies. */
    public function apply(Request $request, JobPost $post)
    {
        $user = $request->user();

        if ((int) $post->user_id === (int) $user->id) {
            abort(422, 'A business cannot apply to its own job.');
        }

        if (! $post->is_active || ($post->expire_at && $post->expire_at->isPast())) {
            abort(422, 'This job is no longer accepting applications.');
        }

        $already = Apply::query()->where('post_id', $post->id)->where('user_id', $user->id)->exists();

        if ($already) {
            abort(422, 'You already applied to this job.');
        }

        $apply = Apply::create(['post_id' => $post->id, 'user_id' => $user->id]);

        return response()->json(['success' => true, 'data' => ['id' => $apply->id, 'applied_at' => $apply->created_at?->toIso8601String()]], 201);
    }

    /** GET /api/v2/jobs/{post}/applicants — the posting business only. */
    public function applicants(Request $request, JobPost $post)
    {
        $user = $request->user();

        if ((int) $post->user_id !== (int) $user->id) {
            abort(403, 'Only the business that posted this job can see its applicants.');
        }

        $applicants = Apply::query()
            ->where('post_id', $post->id)
            ->with('user:id,name,phone,email,image')
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20));

        $applicants->getCollection()->transform(fn (Apply $a) => [
            'id' => $a->id,
            'user' => $a->user ? [
                'id' => $a->user->id,
                'name' => $a->user->name,
                'phone' => $a->user->phone,
                'email' => $a->user->email,
                'image' => $a->user->image,
            ] : null,
            'applied_at' => $a->created_at?->toIso8601String(),
            'approved_at' => $a->approved_at?->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'data' => $applicants]);
    }

    /**
     * POST /api/v2/jobs/{post}/applicants/{apply}/approve — the posting
     * business accepts one applicant. Idempotent; notifies the applicant.
     * Does NOT close the job (a business may hire several) — that is /close.
     */
    public function approveApplicant(Request $request, JobPost $post, Apply $apply)
    {
        abort_if((int) $apply->post_id !== (int) $post->id, 404);

        $user = $request->user();

        if ((int) $post->user_id !== (int) $user->id) {
            abort(403, 'Only the business that posted this job can accept an applicant.');
        }

        if (! $apply->approved_at) {
            $apply->approved_at = now();
            $apply->save();

            app(NotificationDispatcherService::class)->dispatch('job_application_approved', (int) $apply->user_id, [
                'actor_id' => (int) $post->user_id,
                'body_ar' => $post->title_ar ?: $post->title_en ?: __('وظيفة'),
                'body_en' => $post->title_en ?: $post->title_ar ?: 'Job',
                'action_type' => 'open_job',
                'action_url' => '/jobs/' . $post->id,
                'notifiable_type' => Post::class,
                'notifiable_id' => (int) $post->id,
                'source_type' => 'job_application_approved',
                'source_id' => (int) $apply->id,
                'meta' => ['job_id' => (int) $post->id, 'apply_id' => (int) $apply->id],
            ]);
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $apply->id,
            'approved_at' => $apply->approved_at?->toIso8601String(),
        ]]);
    }

    /** POST /api/v2/jobs/{post}/close — the posting business stops accepting. */
    public function close(Request $request, JobPost $post)
    {
        $user = $request->user();

        if ((int) $post->user_id !== (int) $user->id) {
            abort(403, 'Only the business that posted this job can close it.');
        }

        $post->is_active = false;
        $post->save();

        return response()->json(['success' => true, 'data' => ['id' => $post->id, 'is_active' => false]]);
    }

    /**
     * GET /api/v2/jobs/mine/stats — the counters for the signed-in business:
     * how many jobs it posted, total applicants, total accepted.
     */
    public function myStats(Request $request)
    {
        $user = $request->user();

        if (! $user->isBusiness()) {
            abort(403, 'Only a business account has job stats.');
        }

        $jobIds = Post::query()->jobs()->where('user_id', $user->id)->pluck('id');

        $applicantsTotal = Apply::query()->whereIn('post_id', $jobIds)->count();
        $approvedTotal = Apply::query()->whereIn('post_id', $jobIds)->whereNotNull('approved_at')->count();

        return response()->json(['success' => true, 'data' => [
            'jobs_posted' => $jobIds->count(),
            'jobs_open' => Post::query()->openJobs()->where('user_id', $user->id)->count(),
            'applicants_total' => $applicantsTotal,
            'approved_total' => $approvedTotal,
        ]]);
    }

    /**
     * GET /api/v2/jobs/stats — the same four counters, but for the whole
     * platform instead of one business. Public: these are aggregates, they
     * name nobody, so the visibility rule (only the poster sees applicant
     * identities) is untouched.
     */
    public function platformStats()
    {
        // Scoped through the post on purpose: `applies` has no type column,
        // and a row can outlive a deleted post.
        $jobApplies = fn () => Apply::query()->whereHas('post', fn ($w) => $w->where('type', 'job'));

        return response()->json(['success' => true, 'data' => [
            'jobs_posted' => Post::query()->jobs()->count(),
            'jobs_open' => Post::query()->openJobs()->count(),
            'applicants_total' => $jobApplies()->count(),
            'approved_total' => $jobApplies()->whereNotNull('approved_at')->count(),
            'businesses_hiring' => Post::query()->openJobs()->distinct()->count('user_id'),
        ]]);
    }

    private function publicShape(Post $post, bool $withBody = false): array
    {
        $out = [
            'id' => $post->id,
            'title' => $post->title,
            'title_ar' => $post->title_ar,
            'title_en' => $post->title_en,
            'salary' => $post->salary,
            'interview_starts_at' => $post->interview_starts_at?->toIso8601String(),
            'expire_at' => $post->expire_at?->toIso8601String(),
            'category' => $post->category ? ['id' => $post->category->id, 'name' => $this->label($post->category)] : null,
            'category_child' => $post->categoryChild ? ['id' => $post->categoryChild->id, 'name' => $this->label($post->categoryChild)] : null,
            'business' => $post->relationLoaded('user') && $post->user ? [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'logo' => $post->user->logo,
            ] : null,
            'applicants_count' => (int) ($post->applies_count ?? 0),
            'created_at' => $post->created_at?->toIso8601String(),
        ];

        if ($withBody) {
            $out['body'] = $post->body;
            $out['requirements'] = $post->requirements;
        }

        return $out;
    }

    private function label($model): ?string
    {
        if (! $model) {
            return null;
        }

        $ar = trim((string) ($model->name_ar ?? ''));
        $en = trim((string) ($model->name_en ?? ''));

        $primary   = app()->getLocale() === 'en' ? $en : $ar;
        $secondary = app()->getLocale() === 'en' ? $ar : $en;

        return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : null);
    }
}
