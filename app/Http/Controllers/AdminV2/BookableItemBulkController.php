<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookableItem;
use App\Models\Service;
use App\Models\User;
use App\Services\Bookable\BookableItemBulkOpsService;

class BookableItemBulkController extends Controller
{

    public function index(Request $request)
    {
        $businesses = User::query()
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $services = Service::query()
            ->orderBy('name_en')
            ->get();

        $bookables = BookableItem::query()
            ->when($request->business_id, fn($q) =>
                $q->where('business_id', $request->business_id)
            )
            ->when($request->service_id, fn($q) =>
                $q->where('service_id', $request->service_id)
            )
            ->limit(200)
            ->get();

        return view('admin-v2.bookable-items.bulk', [
            'businesses' => $businesses,
            'services' => $services,
            'bookables' => $bookables,
        ]);
    }

    public function applyBlock(Request $request)
    {
        $request->validate([
            'bookable_ids' => ['required','array'],
            'starts_at' => ['required','date'],
            'ends_at' => ['required','date','after_or_equal:starts_at'],
        ]);

        app(BookableItemBulkOpsService::class)->applyBlock(
            $request->bookable_ids,
            $request->starts_at,
            $request->ends_at
        );

        return back()->with('success','تم تطبيق الإغلاق بنجاح');
    }

    public function applyPrice(Request $request)
    {
        $request->validate([
            'bookable_ids' => ['required','array'],
            'starts_at' => ['required','date'],
            'ends_at' => ['required','date'],
            'price' => ['required','numeric'],
        ]);

        app(BookableItemBulkOpsService::class)->applyPriceRule(
            $request->bookable_ids,
            $request->starts_at,
            $request->ends_at,
            $request->price
        );

        return back()->with('success','تم تطبيق السعر بنجاح');
    }
}
