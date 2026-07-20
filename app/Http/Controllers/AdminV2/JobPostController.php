<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Apply;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\JobPost;
use Illuminate\Http\Request;

class JobPostController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];
    private const SORT_ALLOWED = ['id','title','user_id','is_active','expire_at','share_count','created_at'];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function normalizeSort($sort): string
    {
        $sort = (string) $sort;
        return in_array($sort, self::SORT_ALLOWED, true) ? $sort : 'id';
    }

    private function normalizeDir($dir): string
    {
        return strtolower((string) $dir) === 'asc' ? 'asc' : 'desc';
    }

    private function normalizeExpire($expire): string
    {
        // '' | 'expired' | 'not_expired'
        $expire = (string) $expire;
        return in_array($expire, ['expired', 'not_expired'], true) ? $expire : '';
    }

    private function baseQuery()
    {
        // No ->where('type','job') — JobPost's global scope carries it.
        return JobPost::query()->with(['user']);
    }

    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $expire  = $this->normalizeExpire($request->get('expire', ''));
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sort = $this->normalizeSort($request->get('sort', 'id'));
        $dir  = $this->normalizeDir($request->get('dir', 'desc'));

        $query = $this->baseQuery();

        // Search
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                // `body`, not body_ar/body_en — those were unified away by
                // 2026_02_16_180855 and no longer exist, so any search here
                // was a guaranteed "Unknown column 'body_ar'" 500.
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('body', 'like', "%{$q}%");
            });
        }

        // ✅ Expire filter بدل Active filter
        if ($expire !== '') {
            $now = now();

            if ($expire === 'expired') {
                // منتهي: expire_at < now
                $query->whereNotNull('expire_at')
                      ->where('expire_at', '<', $now);
            } else { // not_expired
                // غير منتهي: expire_at is null OR >= now
                $query->where(function ($w) use ($now) {
                    $w->whereNull('expire_at')
                      ->orWhere('expire_at', '>=', $now);
                });
            }
        }

        $posts = $query
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->appends($request->query());

        // options for view (بدل activeOptions)
        $expireOptions = [
            '' => __('الكل'),
            'not_expired' => 'Not Expired',
            'expired' => 'Expired',
        ];

        $perPageOptions = self::PER_PAGE_ALLOWED;

        return view('admin-v2.jobs.index', [
            'posts' => $posts,
            'q' => $q,
            'expire' => $expire,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'expireOptions' => $expireOptions,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    // Every {post} below binds as JobPost, whose global scope makes route-model
    // binding 404 on a non-job id by itself — that is what the repeated
    // `abort_if($post->type !== 'job', 404)` used to do in each method.
    public function show(Request $request, JobPost $post)
    {
        $post->loadMissing(['user','images']);

        $qsKeep = $request->only(['q','expire','per_page','sort','dir']);

        return view('admin-v2.jobs.show', [
            'item' => $post,
            'qsKeep' => $qsKeep,
        ]);
    }

    

    public function create()
    {
        $item = new JobPost();
        $categories = Category::query()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $categoryChildren = CategoryChild::query()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.jobs.create', compact('item', 'categories', 'categoryChildren'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if (!array_key_exists('share_count', $data) || $data['share_count'] === null) {
            $data['share_count'] = 0;
        }

        // type is stamped by JobPost's creating hook.
        $post = JobPost::create($data);

        return redirect()
            ->route('admin.jobs.edit', ['post' => $post->id])
            ->with('success', __('تم إنشاء Job بنجاح'));
    }

    public function edit(JobPost $post)
    {
        $item = $post;
        $categories = Category::query()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $categoryChildren = CategoryChild::query()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.jobs.edit', compact('item', 'categories', 'categoryChildren'));
    }

    /** Oversight only — who applied. Never edits an application. */
    public function applicants(JobPost $post)
    {
        $applicants = Apply::query()
            ->where('post_id', $post->id)
            ->with('user:id,name,phone,email')
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin-v2.jobs.applicants', ['item' => $post, 'applicants' => $applicants]);
    }

    public function update(Request $request, JobPost $post)
    {
        $data = $this->validateData($request);

        $post->update($data);

        return redirect()
            ->route('admin.jobs.edit', ['post' => $post->id] + $request->query())
            ->with('success', __('تم تحديث Job بنجاح'));
    }

    public function destroy(JobPost $post)
    {
        $post->delete();

        return redirect()
            ->route('admin.jobs.index')
            ->with('success', __('تم حذف Job'));
    }

    public function toggleActive(JobPost $post)
    {
        $post->is_active = !$post->is_active;
        $post->save();

        return response()->json(['ok' => true, 'is_active' => (bool)$post->is_active]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['nullable','integer'],

            'title' => ['required','string','max:191'],

            'body' => ['nullable','string'],


            'image' => ['nullable','string','max:500'],

            'expire_at' => ['nullable','date'],
            'is_active' => ['nullable','boolean'],

            'share_count' => ['nullable','integer','min:0'],

            'category_id' => ['nullable','integer','exists:categories,id'],
            'category_child_id' => ['nullable','integer','exists:category_children_master,id'],
            // Free text on purpose — salary is often "يحدد بعد المقابلة", not a number.
            'salary' => ['nullable','string','max:191'],
            'requirements' => ['nullable','string'],
            'interview_starts_at' => ['nullable','date'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        // A single `title` column now, so `required` above is the whole check —
        // the old "at least one of the two languages" dance is gone.
        return $data;
    }
}
