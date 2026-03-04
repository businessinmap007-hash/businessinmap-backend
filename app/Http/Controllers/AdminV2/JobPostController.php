<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class JobPostController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];
    private const SORT_ALLOWED = ['id','title_ar','title_en','user_id','is_active','expire_at','share_count','created_at'];

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
        return Post::query()
            ->where('type', 'job')
            ->with(['user']); // اختياري
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
                $w->where('title_ar', 'like', "%{$q}%")
                  ->orWhere('title_en', 'like', "%{$q}%")
                  ->orWhere('body_ar', 'like', "%{$q}%")
                  ->orWhere('body_en', 'like', "%{$q}%");
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
            '' => 'الكل',
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

    public function show(Request $request, Post $post)
    {
        abort_if($post->type !== 'job', 404);

        $post->loadMissing(['user','images']);

        $qsKeep = $request->only(['q','expire','per_page','sort','dir']);

        return view('admin-v2.jobs.show', [
            'item' => $post,
            'qsKeep' => $qsKeep,
        ]);
    }

    

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['type'] = 'job';

        if (!array_key_exists('share_count', $data) || $data['share_count'] === null) {
            $data['share_count'] = 0;
        }

        $post = Post::create($data);

        return redirect()
            ->route('admin.jobs.edit', ['post' => $post->id])
            ->with('success', 'تم إنشاء Job بنجاح');
    }

    public function edit(Post $post)
    {
        abort_if($post->type !== 'job', 404);

        $item = $post;
        return view('admin-v2.jobs.edit', compact('item'));
    }

    public function update(Request $request, Post $post)
    {
        abort_if($post->type !== 'job', 404);

        $data = $this->validateData($request);
        $data['type'] = 'job';

        $post->update($data);

        return redirect()
            ->route('admin.jobs.edit', ['post' => $post->id] + $request->query())
            ->with('success', 'تم تحديث Job بنجاح');
    }

    public function destroy(Post $post)
    {
        abort_if($post->type !== 'job', 404);

        $post->delete();

        return redirect()
            ->route('admin.jobs.index')
            ->with('success', 'تم حذف Job');
    }

    public function toggleActive(Post $post)
    {
        abort_if($post->type !== 'job', 404);

        $post->is_active = !$post->is_active;
        $post->save();

        return response()->json(['ok' => true, 'is_active' => (bool)$post->is_active]);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['nullable','integer'],

            'title_ar' => ['nullable','string','max:191'],
            'title_en' => ['nullable','string','max:191'],

            'body' => ['nullable','string'],


            'image' => ['nullable','string','max:500'],

            'expire_at' => ['nullable','date'],
            'is_active' => ['nullable','boolean'],

            'share_count' => ['nullable','integer','min:0'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        // لازم عنوان واحد على الأقل
        $titleAr = trim((string)($data['title_ar'] ?? ''));
        $titleEn = trim((string)($data['title_en'] ?? ''));
         if (
        empty(trim($data['title_ar'] ?? '')) &&
        empty(trim($data['title_en'] ?? ''))
    ) {
        abort(
            redirect()->back()
                ->withErrors(['title_ar' => 'أدخل عنوان عربي أو إنجليزي على الأقل'])
                ->withInput()
        );
    }

    return $data;
    }
}
