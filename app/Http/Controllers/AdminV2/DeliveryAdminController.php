<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCompletion;
use App\Models\DeliveryDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 oversight of the connected delivery loop (drivers + the
 * delivery_completions success ledger). Read + activate/deactivate only; the
 * two-stage QR flow itself lives in the v2 API (DeliveryDispatchService).
 */
class DeliveryAdminController extends Controller
{
    /** GET admin/delivery/drivers */
    public function drivers(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $drivers = DeliveryDriver::query()
            ->with('user:id,name,email,phone')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%"));
            })
            ->orderByDesc('delivered_count')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.delivery.drivers', ['drivers' => $drivers, 'q' => $q]);
    }

    /** POST admin/delivery/drivers/{driver}/toggle */
    public function toggle(int $driver): RedirectResponse
    {
        $model = DeliveryDriver::query()->findOrFail($driver);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', __('تم تحديث حالة الموصّل.'));
    }

    /** GET admin/delivery/completions */
    public function completions(Request $request): View
    {
        $completions = DeliveryCompletion::query()
            ->with(['order:id,final_total,fulfillment_type', 'driver.user:id,name', 'business:id,name'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin-v2.delivery.completions', ['completions' => $completions]);
    }
}
