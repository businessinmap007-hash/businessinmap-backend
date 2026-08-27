<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight of every prescription — who wrote it, who it is for,
 * which pharmacy is fulfilling it, the medicine invoice, and delivery status.
 * Nothing here edits a prescription; amending is the original doctor's own
 * door (revise()), never the admin's.
 */
class PrescriptionAdminController extends Controller
{
    /** GET admin/prescriptions — every prescription across every clinic. */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $fulfillment = trim((string) $request->get('fulfillment', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = Prescription::query()
            ->with(['doctor:id,name', 'patient:id,name', 'pharmacy:id,name'])
            ->when(in_array($status, Prescription::STATUSES, true), fn ($x) => $x->where('status', $status))
            ->when(in_array($fulfillment, Prescription::FULFILLMENTS, true), fn ($x) => $x->where('fulfillment_type', $fulfillment))
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($inner) use ($q) {
                    $inner->orWhereHas('doctor', fn ($b) => $b->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('patient', fn ($b) => $b->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('pharmacy', fn ($b) => $b->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $summary = Prescription::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin-v2.prescriptions.index', [
            'rows' => $rows,
            'statuses' => Prescription::STATUSES,
            'fulfillments' => Prescription::FULFILLMENTS,
            'filters' => ['status' => $status, 'fulfillment' => $fulfillment, 'q' => $q],
            'summary' => $summary,
        ]);
    }

    /** GET admin/prescriptions/{prescription} — one prescription in full. */
    public function show(Prescription $prescription): View
    {
        $prescription->load([
            'items',
            'images',
            'doctor:id,name,phone',
            'patient:id,name,phone',
            'pharmacy:id,name,phone',
            'deliveryDriver.user:id,name,phone',
            'shares.doctor:id,name',
            'revises:id,status',
            'revisedBy:id,revises_prescription_id,status',
        ]);

        return view('admin-v2.prescriptions.show', ['prescription' => $prescription]);
    }
}
