<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\AgendaItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight of every user's personal agenda: appointments, service
 * bookings, medication doses and personal tasks across all users, filterable by
 * user/kind/status/date. Nothing here edits an item; it is a support window into
 * "why does this user's day look like this".
 */
class AgendaAdminController extends Controller
{
    /** GET admin/agenda — all agenda items across users. */
    public function index(Request $request): View
    {
        $kind = trim((string) $request->get('kind', ''));
        $status = trim((string) $request->get('status', AgendaItem::STATUS_ACTIVE));
        $date = trim((string) $request->get('date', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = AgendaItem::query()
            ->with('user:id,name,phone')
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($date !== '', fn ($query) => $query->whereDate('starts_at', $date))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('starts_at')
            ->paginate(40)
            ->withQueryString();

        return view('admin-v2.agenda.index', [
            'rows' => $rows,
            'kinds' => AgendaItem::KINDS,
            'statuses' => [AgendaItem::STATUS_ACTIVE, AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED],
            'filters' => ['kind' => $kind, 'status' => $status, 'date' => $date, 'q' => $q],
        ]);
    }
}
