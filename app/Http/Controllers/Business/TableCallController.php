<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\TableServiceCallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The owner-panel screen for live dine-in table service calls (BIM-13.3): the
 * pending "waiter please / the bill" queue and marking one handled. Push already
 * reaches staff on mobile; this is the standing board on the panel. Scoped to
 * Auth::id() like the rest of the business web panel.
 */
class TableCallController extends Controller
{
    public function __construct(protected TableServiceCallService $tableCalls)
    {
    }

    public function index(): View
    {
        return view('business.table-calls.index', [
            'calls' => $this->tableCalls->pendingFor((int) Auth::id()),
        ]);
    }

    public function resolve(int $id): RedirectResponse
    {
        $this->tableCalls->resolve((int) Auth::id(), $id, (int) Auth::id());

        return back()->with('success', 'تم إغلاق النداء.');
    }
}
