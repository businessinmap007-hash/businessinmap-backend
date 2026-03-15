<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableItemBulkOpsService;
use Illuminate\Http\Request;

class BookableItemBulkController extends Controller
{
    public function index(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId  = (int) $request->get('service_id', 0);

        $businesses = User::query()
            ->where('type', 'business')
            ->orderBy('name')
            ->get(['id', 'name']);

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_en')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        $bookables = BookableItem::query()
            ->with(['business:id,name', 'service:id,key,name_ar,name_en'])
            ->when($businessId > 0, fn ($q) => $q->where('business_id', $businessId))
            ->when($serviceId > 0, fn ($q) => $q->where('service_id', $serviceId))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin-v2.bookable-items.bulk', [
            'businesses' => $businesses,
            'services' => $services,
            'bookables' => $bookables,
            'businessId' => $businessId,
            'serviceId' => $serviceId,
        ]);
    }

    public function applyBlock(Request $request, BookableItemBulkOpsService $service)
    {
        $data = $request->validate([
            'bookable_ids'   => ['required', 'array', 'min:1'],
            'bookable_ids.*' => ['integer', 'exists:bookable_items,id'],
            'starts_at'      => ['required', 'date'],
            'ends_at'        => ['required', 'date', 'after:starts_at'],
            'reason'         => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string'],
        ]);

        $service->applyBlock(
            bookableIds: $data['bookable_ids'],
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            reason: $data['reason'] ?? 'bulk_admin',
            notes: $data['notes'] ?? null,
            actorId: auth()->id(),
        );

        return back()->with('success', 'تم تطبيق الإغلاق بنجاح.');
    }

    public function applyPrice(Request $request, BookableItemBulkOpsService $service)
    {
        $data = $request->validate([
            'bookable_ids'   => ['required', 'array', 'min:1'],
            'bookable_ids.*' => ['integer', 'exists:bookable_items,id'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'price_type'     => ['required', 'string', 'in:fixed,delta,percent'],
            'price_value'    => ['required', 'numeric'],
            'title'          => ['nullable', 'string', 'max:150'],
            'notes'          => ['nullable', 'string'],
            'priority'       => ['nullable', 'integer', 'min:1'],
        ]);

        $service->applyPriceRule(
            bookableIds: $data['bookable_ids'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            priceType: $data['price_type'],
            priceValue: (float) $data['price_value'],
            title: $data['title'] ?? null,
            notes: $data['notes'] ?? null,
            priority: (int) ($data['priority'] ?? 100),
            actorId: auth()->id(),
        );

        return back()->with('success', 'تم تطبيق قاعدة التسعير بنجاح.');
    }
}