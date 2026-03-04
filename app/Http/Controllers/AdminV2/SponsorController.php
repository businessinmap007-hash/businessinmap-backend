<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SponsorController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    public function index(Request $request)
    {
        $q        = trim((string) $request->get('q', ''));      // id أو user_id أو price
        $type     = trim((string) $request->get('type', ''));   // paid|free|''
        $status   = trim((string) $request->get('status', '')); // active|inactive|expired|''
        $perPage  = $this->normalizePerPage($request->get('per_page', 50));

        $sort = (string) $request->get('sort', 'id');
        $dir  = (string) $request->get('dir', 'desc');
        $dir  = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        $allowedSort = ['id','user_id','type','expire_at','activated_at','created_at'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'id';

        $items = Sponsor::query()
            ->when($q !== '', function ($qq) use ($q) {
                if (is_numeric($q)) {
                    $qq->where('id', (int)$q)->orWhere('user_id', (int)$q);
                    return;
                }
                $qq->where('price','like',"%{$q}%");
            })
            ->when($type !== '', fn($qq) => $qq->where('type', $type))
            ->when($status !== '', function ($qq) use ($status) {
                if ($status === 'active') {
                    return $qq->whereNotNull('activated_at')
                        ->where(function ($w) {
                            $w->whereNull('expire_at')->orWhere('expire_at', '>=', now());
                        });
                }

                if ($status === 'expired') {
                    return $qq->whereNotNull('expire_at')->where('expire_at', '<', now());
                }

                if ($status === 'inactive') {
                    return $qq->where(function ($w) {
                        $w->whereNull('activated_at')
                          ->orWhere(function ($x) {
                              $x->whereNotNull('expire_at')->where('expire_at', '<', now());
                          });
                    });
                }

                return $qq;
            })
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->appends($request->query());

        return view('admin-v2.sponsors.index', compact(
            'items','q','type','status','perPage','sort','dir'
        ));
    }

    public function create()
    {
        $sponsor = new Sponsor();
        return view('admin-v2.sponsors.create', compact('sponsor'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, false);

        // image required في create لأن الجدول NOT NULL
        $data['image'] = $this->storeImagePath($request);

        Sponsor::create($data);

        return redirect()->route('admin.sponsors.index')
            ->with('success', 'تم إنشاء Sponsor بنجاح');
    }

    public function edit(Sponsor $sponsor)
    {
        return view('admin-v2.sponsors.edit', compact('sponsor'));
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        $data = $this->validateData($request, true);

        // لو تم رفع صورة جديدة: خزّن الجديدة + احذف القديمة
        if ($request->hasFile('image')) {
            $newPath = $this->storeImagePath($request);

            $old = public_path($sponsor->image);
            if ($sponsor->image && file_exists($old)) {
                @unlink($old);
            }


            $data['image'] = $newPath;
        } else {
            unset($data['image']);
        }

        $sponsor->update($data);

        return redirect()->route('admin.sponsors.index')
            ->with('success', 'تم تحديث Sponsor بنجاح');
    }

    public function destroy(Sponsor $sponsor)
    {
        // احذف الصورة مع السجل
        $old = public_path($sponsor->image);
            if ($sponsor->image && file_exists($old)) {
                @unlink($old);
            }


        $sponsor->delete();

        return back()->with('success', 'تم حذف Sponsor');
    }


    private function deleteOldImage(?string $path): void
    {
        if (!$path) return;

        $full = public_path($path);
        if (file_exists($full)) {
            @unlink($full);
        }
    }


    private function validateData(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'user_id'      => 'nullable|integer|min:1',
            'image'        => $isUpdate ? 'nullable|image|max:2048' : 'required|image|max:2048',
            'price'        => 'nullable|string|max:191',
            'type'         => 'required|in:paid,free',
            'expire_at'    => 'nullable|date',
            'activated_at' => 'nullable|date', // إدخال يدوي (Datetime)
        ], [], [
            'user_id' => 'المستخدم',
            'image' => 'الصورة',
            'price' => 'السعر',
            'type' => 'النوع',
            'expire_at' => 'تاريخ الانتهاء',
            'activated_at' => 'تاريخ التفعيل',
        ]);
    }

    /**
     * يخزن الصورة في: storage/app/public/file/uploads
     * ويعيد path كامل يُحفظ في DB: file/uploads/1589048759.image.jpg
     */
    private function storeImagePath(Request $request): string
        {
            $file = $request->file('image');

            $name = time().'.'.$file->getClientOriginalName(); // 1589xxx.image.jpg
            $dest = public_path('files/uploads');

            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }

            $file->move($dest, $name);

            // نخزن نفس الصيغة القديمة المتوافقة مع component
            return 'files/uploads/'.$name;
        }




}
