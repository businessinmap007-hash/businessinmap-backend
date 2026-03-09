<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookableItemController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $serviceId = (int) $request->get('service_id', 0);
        $businessId = (int) $request->get('business_id', 0);
        $isActive = $request->get('is_active', '');

        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en'])
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $rows = BookableItem::query()
            ->with([
                'service:id,key,name_ar,name_en',
                'business:id,name',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('item_type', 'like', "%{$q}%");
                });
            })
            ->when($serviceId > 0, function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId);
            })
            ->when($businessId > 0, function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })
            ->when($isActive !== '' && $isActive !== null, function ($query) use ($isActive) {
                $query->where('is_active', (int) $isActive);
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.bookable-items.index', compact(
            'rows',
            'services',
            'businesses',
            'q',
            'serviceId',
            'businessId',
            'isActive'
        ));
    }

    public function create()
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row = new BookableItem([
            'price' => 0,
            'quantity' => 1,
            'is_active' => 1,
            'deposit_enabled' => 0,
            'deposit_percent' => 0,
        ]);

        return view('admin-v2.bookable-items.create', compact('row', 'services', 'businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = BookableItem::create($data);

        return redirect()
            ->route('admin.bookable-items.edit', $row)
            ->with('success', 'تم إنشاء العنصر القابل للحجز بنجاح.');
    }

    public function edit(BookableItem $bookableItem)
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row = $bookableItem->load([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'business:id,name',
        ]);

        return view('admin-v2.bookable-items.edit', compact('row', 'services', 'businesses'));
    }

    public function update(Request $request, BookableItem $bookableItem)
    {
        $data = $this->validateData($request, $bookableItem->id);

        $bookableItem->update($data);

        return back()->with('success', 'تم تحديث العنصر القابل للحجز بنجاح.');
    }

    public function destroy(BookableItem $bookableItem)
    {
        $bookableItem->delete();

        return redirect()
            ->route('admin.bookable-items.index')
            ->with('success', 'تم حذف العنصر بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'item_type' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
            'deposit_enabled' => ['nullable'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'meta' => ['nullable'],
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['deposit_enabled'] = (int) $request->boolean('deposit_enabled');
        $data['quantity'] = (int) ($data['quantity'] ?? 1);
        $data['deposit_percent'] = (int) ($data['deposit_percent'] ?? 0);

        $service = PlatformService::query()->find($data['service_id']);

        if (!$service) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير موجودة.',
            ]);
        }

        if (!(bool) $service->supports_deposit) {
            $data['deposit_enabled'] = 0;
            $data['deposit_percent'] = 0;
        } else {
            if (!$data['deposit_enabled']) {
                $data['deposit_percent'] = 0;
            } else {
                $maxAllowed = (int) ($service->max_deposit_percent ?? 0);

                if ($data['deposit_percent'] > $maxAllowed) {
                    throw ValidationException::withMessages([
                        'deposit_percent' => "نسبة الديبوزت تتجاوز الحد المسموح للخدمة ({$maxAllowed}%).",
                    ]);
                }
            }
        }

        if (isset($data['meta']) && is_string($data['meta']) && trim($data['meta']) !== '') {
            $decoded = json_decode($data['meta'], true);
            $data['meta'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } else {
            $data['meta'] = null;
        }

        return $data;
    }
}