<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Category;   // ✅ مهم
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

final class AlbumController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only(['q','user_id','category_id','per_page','sort','dir']);
    }

    private function sortWhitelist(): array
    {
        return ['id','created_at','updated_at','user_id','title_ar','title_en'];
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, $this->sortWhitelist(), true) ? $sort : 'id';
    }

    private function normalizeDir(string $dir): string
    {
        return $dir === 'asc' ? 'asc' : 'desc';
    }

    public function index(Request $request)
    {
        $q          = trim((string)$request->get('q', ''));
        $userId     = (string)$request->get('user_id', '');
        $categoryId = (string)$request->get('category_id', '');

        $perPage = $this->normalizePerPage($request->get('per_page', 50));
        $sort    = $this->normalizeSort((string)$request->get('sort', 'id'));
        $dir     = $this->normalizeDir((string)$request->get('dir', 'desc'));

        $query = Album::query()->with(['user:id,name,email,phone,type,category_id']);

        // ===== Search =====
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int)$q)
                      ->orWhere('user_id', (int)$q);
                }

                $w->orWhere('title_ar', 'like', "%{$q}%")
                  ->orWhere('title_en', 'like', "%{$q}%")
                  ->orWhere('description_ar', 'like', "%{$q}%")
                  ->orWhere('description_en', 'like', "%{$q}%");

                $w->orWhereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
                });
            });
        }

        // ===== Filter by user_id =====
        if ($userId !== '' && ctype_digit($userId)) {
            $query->where('user_id', (int)$userId);
        }

        // ===== Filter by category_id via user's category_id =====
        if ($categoryId !== '' && ctype_digit($categoryId)) {
            $query->whereHas('user', function ($u) use ($categoryId) {
                $u->where('category_id', (int)$categoryId);
            });
        }

        $items = $query->orderBy($sort, $dir)
            ->paginate($perPage)
            ->appends($this->keepQs($request));

        $usersForFilter = User::query()
            ->select('id','name','email','type')
            ->orderBy('id','desc')
            ->limit(60)
            ->get();

        // ✅ Root categories only
        $categoriesForFilter = Category::query()
            ->select('id','parent_id','name_ar')
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->orderBy('id','desc')
            ->limit(200)
            ->get();

        return view('admin-v2.albums.index', [
            'items' => $items,

            'q' => $q,
            'user_id' => $userId,
            'category_id' => $categoryId,

            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,

            'usersForFilter' => $usersForFilter,
            'categoriesForFilter' => $categoriesForFilter,
        ]);
    }

    public function show(Album $album)
    {
        $album->load(['user:id,name,email,phone,type,category_id','images']);
        return view('admin-v2.albums.show', ['album' => $album]);
    }

    public function create()
    {
        $users = User::query()
            ->select('id','name','email','type','category_id')
            ->orderBy('id','desc')
            ->limit(200)
            ->get();

        return view('admin-v2.albums.create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'        => ['nullable','integer','exists:users,id'],
            'image'          => ['nullable','string','max:191'],
            'title_ar'       => ['nullable','string','max:191'],
            'title_en'       => ['nullable','string','max:191'],
            'description_ar' => ['nullable','string'],
            'description_en' => ['nullable','string'],
        ]);

        $album = Album::create($data);

        return redirect()
            ->route('admin.albums.show', $album->id)
            ->with('success', 'تم إنشاء الألبوم بنجاح');
    }

    public function edit(Album $album)
    {
        $album->load(['user:id,name,email,type,category_id']);

        $users = User::query()
            ->select('id','name','email','type','category_id')
            ->orderBy('id','desc')
            ->limit(200)
            ->get();

        return view('admin-v2.albums.edit', [
            'album' => $album,
            'users' => $users,
        ]);
    }

    public function update(Request $request, Album $album)
    {
        $data = $request->validate([
            'user_id'        => ['nullable','integer','exists:users,id'],
            'image'          => ['nullable','string','max:191'],
            'title_ar'       => ['nullable','string','max:191'],
            'title_en'       => ['nullable','string','max:191'],
            'description_ar' => ['nullable','string'],
            'description_en' => ['nullable','string'],
        ]);

        $oldImage = (string)($album->image ?? '');
        $newImage = (string)($data['image'] ?? '');

        $album->update($data);

        if ($oldImage !== '' && $newImage !== '' && $oldImage !== $newImage) {
            $publicPath = public_path(ltrim($oldImage, '/'));
            if (File::exists($publicPath)) {
                @File::delete($publicPath);
            }
        }

        return redirect()
            ->route('admin.albums.show', $album->id)
            ->with('success', 'تم تحديث الألبوم بنجاح');
    }

    public function destroy(Album $album)
    {
        $img = (string)($album->image ?? '');
        if ($img !== '') {
            $publicPath = public_path(ltrim($img, '/'));
            if (File::exists($publicPath)) {
                @File::delete($publicPath);
            }
        }

        $album->delete();

        return redirect()
            ->route('admin.albums.index')
            ->with('success', 'تم حذف الألبوم');
    }

   

    private function tryDeletePublicFile(string $path): void
    {
        $path = trim($path);
        if ($path === '') return;

        // نتجنب حذف URL خارجي
        if (preg_match('#^https?://#i', $path)) return;

        $publicPath = public_path(ltrim($path, '/'));
        if (File::exists($publicPath)) {
            @File::delete($publicPath);
        }
    }


    
    private function findAlbumImageOrFail(Album $album, int $imageId): Image
{
    $img = Image::query()->findOrFail($imageId);

    if ((int)$img->imageable_id !== (int)$album->id) abort(404);

    // لو عندك النوع مختلف سيبه:
    $type = (string)$img->imageable_type;
    if ($type !== Album::class && $type !== 'App\\Models\\Album') abort(404);

    return $img;
}

private function imagePathFrom(Image $img): string
{
    return (string)($img->path ?? $img->url ?? $img->image ?? $img->src ?? '');
}

public function setCover(Request $request, Album $album, int $imageId)
{
    $img = $this->findAlbumImageOrFail($album, $imageId);
    $path = $this->imagePathFrom($img);

    if ($path === '') {
        return response()->json(['ok'=>false,'message'=>'مسار الصورة غير موجود'], 422);
    }

    $album->image = $path;
    $album->save();

    return response()->json(['ok'=>true,'cover'=>$path]);
}

public function deleteImage(Request $request, Album $album, int $imageId)
{
    $img  = $this->findAlbumImageOrFail($album, $imageId);
    $path = $this->imagePathFrom($img);

    if ($path !== '' && (string)$album->image === $path) {
        return response()->json(['ok'=>false,'message'=>'هذه صورة الغلاف. عيّن غلافًا آخر أولاً ثم احذفها.'], 422);
    }

    // حذف الملف لو محلي (اختياري)
    $this->tryDeletePublicFile($path);

    $img->delete(); // ✅ حذف من DB نهائي

    return response()->json(['ok'=>true]);
}
}