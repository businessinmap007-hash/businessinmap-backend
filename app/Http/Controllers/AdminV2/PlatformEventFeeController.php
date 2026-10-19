<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformEventFee;
use App\Services\Fees\PlatformEventFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The price list of the events that have no booking to hang a fee on (swapping numbers on an owner listing, changing a
 * training or a nutrition program, …): one row per event and party, fixed or a percent, with a floor and a ceiling. The
 * same shape the delivery and booking fees already have; the events themselves are the registry in
 * {@see PlatformEventFeeService::EVENTS}.
 */
final class PlatformEventFeeController extends Controller
{
    public function index(): View
    {
        $rows = PlatformEventFee::query()->get()->groupBy('event_key');

        return view('admin-v2.event-fees.index', ['events' => PlatformEventFeeService::EVENTS, 'rows' => $rows]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fees' => ['required', 'array'],
            'fees.*' => ['array'],
            'fees.*.*.enabled' => ['nullable', 'boolean'],
            'fees.*.*.calc_type' => ['required', 'in:fixed,percent'],
            'fees.*.*.amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'fees.*.*.min_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'fees.*.*.max_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        foreach ($data['fees'] as $event => $roles) {
            // only the events and parties the platform knows: nothing else can be priced from a form
            if (! isset(PlatformEventFeeService::EVENTS[$event])) {
                continue;
            }

            foreach ($roles as $role => $row) {
                if (! isset(PlatformEventFeeService::EVENTS[$event][3][$role])) {
                    continue;
                }

                if ($row['calc_type'] === 'percent' && (float) $row['amount'] > 100) {
                    return back()->withErrors(['fees' => __('النسبة لا تزيد على 100.')])->withInput();
                }

                PlatformEventFee::query()->updateOrCreate(
                    ['event_key' => $event, 'role' => $role],
                    [
                        'enabled' => ! empty($row['enabled']),
                        'calc_type' => $row['calc_type'],
                        'amount' => $row['amount'],
                        'min_amount' => ($row['min_amount'] ?? '') === '' ? null : $row['min_amount'],
                        'max_amount' => ($row['max_amount'] ?? '') === '' ? null : $row['max_amount'],
                    ]
                );
            }
        }

        return redirect()->route('admin.event-fees.index')->with('success', __('حُفظت الرسوم.'));
    }
}
