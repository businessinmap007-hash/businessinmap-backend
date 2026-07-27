<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight of every clinic's appointments. An admin can browse all
 * appointments (filter by status/date, search a clinic or patient) and open one
 * to see its details and whether a prescription was written during the visit.
 * Nothing here edits an appointment.
 */
class ClinicAppointmentAdminController extends Controller
{
    /** GET admin/clinic-appointments — all appointments across clinics. */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $date = trim((string) $request->get('date', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = ClinicAppointment::query()
            ->with(['clinic:id,name', 'patient:id,name', 'prescription:id,appointment_id'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($date !== '', fn ($query) => $query->whereDate('scheduled_at', $date))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('reason', 'like', "%{$q}%")
                        ->orWhereHas('clinic', fn ($b) => $b->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('patient', fn ($b) => $b->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('scheduled_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin-v2.clinic-appointments.index', [
            'rows' => $rows,
            'statuses' => ClinicAppointment::STATUSES,
            'filters' => ['status' => $status, 'date' => $date, 'q' => $q],
        ]);
    }

    /** GET admin/clinic-appointments/{appointment} — one appointment in full. */
    public function show(ClinicAppointment $appointment): View
    {
        $appointment->load([
            'clinic:id,name',
            'patient:id,name,phone',
            'prescription:id,appointment_id,status,diagnosis',
        ]);

        return view('admin-v2.clinic-appointments.show', ['appointment' => $appointment]);
    }
}
