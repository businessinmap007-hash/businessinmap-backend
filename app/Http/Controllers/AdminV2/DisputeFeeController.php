<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\DisputeFee;
use App\Models\PlatformService;
use Illuminate\Http\Request;

/**
 * What an arbitration session costs, per service.
 *
 * Here rather than on the arbitrator's screen on purpose: an arbitrator who
 * prices their own session profits from escalating it, and a party cannot argue
 * with a number invented for their case alone. Set once, the same for every
 * case on that service, and quotable to both parties before anyone asks for a
 * ruling.
 *
 * One price per session — never a client price and a business price. The
 * session is one piece of work whoever asked for it.
 */
final class DisputeFeeController extends Controller
{
    public function index()
    {
        $services = PlatformService::query()->orderBy('id')->get(['id', 'key', 'name_ar', 'name_en']);

        $fees = DisputeFee::query()->get()->keyBy(fn ($fee) => $fee->platform_service_id ?? 'default');

        return view('admin-v2.dispute-fees.index', compact('services', 'fees'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Whole numbers by policy: a session price is meant to be quotable
            // in a sentence, and decimals invite 33.33 back into it.
            'default' => ['required', 'integer', 'min:0', 'max:1000000'],
            'services' => ['nullable', 'array'],
            'services.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        DisputeFee::query()->updateOrCreate(
            ['platform_service_id' => null],
            ['amount' => (int) $data['default'], 'is_active' => true, 'updated_by' => (int) auth()->id()]
        );

        foreach ($data['services'] ?? [] as $serviceId => $amount) {
            if ($amount === null || $amount === '') {
                // Cleared means "use the fallback", not "free".
                DisputeFee::query()->where('platform_service_id', (int) $serviceId)->delete();

                continue;
            }

            DisputeFee::query()->updateOrCreate(
                ['platform_service_id' => (int) $serviceId],
                ['amount' => (int) $amount, 'is_active' => true, 'updated_by' => (int) auth()->id()]
            );
        }

        return back()->with('success', __('حُفظت رسوم الجلسات.'));
    }
}
