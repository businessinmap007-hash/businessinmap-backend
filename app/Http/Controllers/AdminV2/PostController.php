<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class PostController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    // ✅ حذفنا type من qs
    private function keepQs(Request $request): array
    {
        return $request->only(['q','active','per_page','sort','dir']);
    }

    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $active  = $request->get('active'); // '' | 0 | 1
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sort = (string) $request->get('sort', 'id');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['id', 'share_count', 'expire_at', 'created_at'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'id';

        $posts = Post::query()
            ->with(['user:id,name,email,phone'])
            ->when($q !== '', function ($qq) use ($q) {

                // ✅ ابحث في الأعمدة الموجودة فعلاً فقط
                $possibleCols = [
                    'title_ar','title_en',
                    'body','body_ar','body_en',
                    'content_ar','content_en',
                ];

                $existing = [];
                foreach ($possibleCols as $c) {
                    if (Schema::hasColumn('posts', $c)) {
                        $existing[] = $c;
                    }
                }

                $qq->where(function ($x) use ($q, $existing) {

                    if (!empty($existing)) {
                        $x->where(function ($w) use ($q, $existing) {
                            foreach ($existing as $col) {
                                $w->orWhere($col, 'like', "%{$q}%");
                            }
                        });
                    }

                    $x->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%")
                          ->orWhere('phone', 'like', "%{$q}%");
                    });
                });
            })
            // ✅ حذفنا type filter بالكامل
            ->when($active !== null && $active !== '', fn ($qq) => $qq->where('is_active', (int) $active))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.posts.index', [
            'posts' => $posts,
            'q' => $q,
            'active' => (string) ($active ?? ''),
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,

            // ✅ حذفنا typeOptions
            'activeOptions' => [
                ''  => 'الكل',
                '1' => 'Active',
                '0' => 'Inactive',
            ],
            'perPageOptions' => self::PER_PAGE_ALLOWED,
        ]);
    }

    public function edit(Request $request, Post $post)
    {
        $post->load('images');

        return view('admin-v2.posts.edit', [
            'post'   => $post,
            'qsKeep' => $this->keepQs($request),
        ]);
    }

    public function show(Request $request, Post $post)
    {
        $post->load([
            'images:id,image,imageable_id,imageable_type',
            'user:id,name,email,phone'
        ]);

        return view('admin-v2.posts.show', [
            'post'   => $post,
            'qsKeep' => $this->keepQs($request),
        ]);
    }

    public function update(Request $request, Post $post)
    {
        // ✅ validate عام، ثم هنفلتر الأعمدة حسب الموجود فعلاً
        $data = $request->validate([
            'title_ar'  => 'nullable|string|max:191',
            'title_en'  => 'nullable|string|max:191',

            // أي محتوى ممكن يكون موجود حسب جدولك
            'body'      => 'nullable|string',
            'body_ar'   => 'nullable|string',
            'body_en'   => 'nullable|string',
            'content_ar'=> 'nullable|string',
            'content_en'=> 'nullable|string',

            'expire_at' => 'nullable|date',
            'is_active' => 'required|in:0,1',

            'image'     => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'image_current' => 'nullable|string|max:255',
        ]);

        if (empty($data['expire_at'])) $data['expire_at'] = null;

        // ✅ فلترة البيانات حسب الأعمدة الموجودة فعلاً لتجنب Unknown column
        $allowed = ['title_ar','title_en','expire_at','is_active'];
        foreach (['body','body_ar','body_en','content_ar','content_en'] as $col) {
            if (Schema::hasColumn('posts', $col)) {
                $allowed[] = $col;
            } else {
                unset($data[$col]); // مهم
            }
        }

        // ✅ legacy upload to public/files/uploads
        if ($request->hasFile('image')) {
            $dir = public_path('files/uploads');
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $file = $request->file('image');
            $name = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $file->move($dir, $name);

            $data['image'] = 'files/uploads/' . $name;
            $allowed[] = 'image';
        } else {
            unset($data['image']); // don't overwrite
        }

        unset($data['image_current']);

        // ✅ طبّق الفلترة النهائية
        $data = array_intersect_key($data, array_flip($allowed));

        $post->update($data);

        return redirect()
            ->route('admin.posts.show', ['post' => $post->id] + $this->keepQs($request))
            ->with('success', 'تم الحفظ بنجاح');
    }

    public function toggleActive(Request $request, Post $post)
    {
        $post->is_active = ! (bool) $post->is_active;
        $post->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'is_active' => (int) $post->is_active,
                'label' => $post->is_active ? 'Active' : 'Inactive',
            ]);
        }

        return back()->with('success', 'تم تغيير الحالة');
    }

    public function destroy(Request $request, Post $post)
    {
        $post->delete();
        return back()->with('success', 'Deleted');
    }

    public function destroyMainImage(Request $request, Post $post)
    {
        $path = $post->image;
        if (!empty($path)) {
            $full = public_path(ltrim($path, '/'));
            if (is_file($full)) @unlink($full);
        }

        $post->image = null;
        $post->save();

        return back()->with('success', 'تم حذف الصورة الرئيسية');
    }

    public function destroyImage(Request $request, Post $post, Image $image)
    {
        if (
            (int)$image->imageable_id !== (int)$post->id ||
            $image->imageable_type !== Post::class
        ) {
            abort(404);
        }

        $path = $image->image;
        if (!empty($path)) {
            $full = public_path(ltrim($path, '/'));
            if (is_file($full)) @unlink($full);
        }

        $image->delete();

        return back()->with('success', 'تم حذف صورة المعرض');
    }
}
