<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\HospitalDoctor;
use App\Models\User;
use App\Services\Hospitals\HospitalDoctorService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * Doctors under a hospital's departments (see HospitalDoctorService).
 *
 *   patient   GET hospitals/{hospital}/departments
 *   hospital  GET|POST business/hospital-doctors, DELETE business/hospital-doctors/{id}, GET …/find-doctors?q=
 *   doctor    GET business/hospital-invitations, POST …/{id}/accept, DELETE …/{id}
 */
class HospitalDoctorController extends Controller
{
    public function __construct(private readonly HospitalDoctorService $service)
    {
    }

    /** GET /api/v2/hospitals/{hospital}/departments — what a patient reads on the hospital's page. */
    public function departments(int $hospital)
    {
        $user = User::query()->where('type', User::TYPE_BUSINESS)->findOrFail($hospital);
        abort_unless(HospitalDoctor::isHospital($user), 404);

        return response()->json(['success' => true, 'data' => ['departments' => $this->service->departmentsOf($user)]]);
    }

    private function hospitalOrFail(Request $request): User
    {
        $hospital = User::query()->findOrFail(BusinessContext::id($request));
        abort_unless(HospitalDoctor::isHospital($hospital), 403, __('الأقسام للمستشفيات والمراكز الطبية فقط.'));

        return $hospital;
    }

    /** GET /api/v2/business/hospital-doctors — the hospital's own view, pending invitations included. */
    public function index(Request $request)
    {
        $hospital = $this->hospitalOrFail($request);

        return response()->json(['success' => true, 'data' => ['departments' => $this->service->departmentsOf($hospital, true)]]);
    }

    /** GET /api/v2/business/hospital-doctors/find-doctors?q= — doctor accounts to invite, by name. */
    public function findDoctors(Request $request)
    {
        $this->hospitalOrFail($request);

        return response()->json(['success' => true, 'data' => ['doctors' => $this->service->findDoctors((string) $request->query('q', ''))]]);
    }

    /** POST /api/v2/business/hospital-doctors — put a doctor under a department. */
    public function store(Request $request)
    {
        $hospital = $this->hospitalOrFail($request);

        $data = $request->validate([
            'option_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:60'],
        ]);

        $row = $this->service->add($hospital, (int) $data['option_id'], isset($data['user_id']) ? (int) $data['user_id'] : null, $data['name'] ?? null, $data['title'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $row->status === HospitalDoctor::STATUS_PENDING ? __('أُرسلت الدعوة إلى الطبيب.') : __('تمت إضافة الطبيب.'),
            'data' => ['doctor' => ['id' => (int) $row->id, 'name' => $row->shownName(), 'business_id' => $row->user_id ? (int) $row->user_id : null, 'status' => $row->status]],
        ], 201);
    }

    /** DELETE /api/v2/business/hospital-doctors/{id} — the hospital drops a doctor. */
    public function destroy(Request $request, int $row)
    {
        $hospital = $this->hospitalOrFail($request);
        $doctor = HospitalDoctor::query()->where('hospital_id', (int) $hospital->id)->findOrFail($row);

        $this->service->remove($doctor, $hospital);

        return response()->json(['success' => true, 'message' => __('تمت إزالة الطبيب من القسم.')]);
    }

    // ───────────────────────── the doctor ─────────────────────────

    /** GET /api/v2/business/hospital-invitations — who invited this doctor, and where the doctor is listed. */
    public function invitations(Request $request)
    {
        $doctor = User::query()->findOrFail(BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => $this->service->invitationsFor($doctor)]);
    }

    /** POST /api/v2/business/hospital-invitations/{id}/accept */
    public function accept(Request $request, int $row)
    {
        $doctor = User::query()->findOrFail(BusinessContext::id($request));
        $this->service->accept(HospitalDoctor::query()->where('user_id', (int) $doctor->id)->findOrFail($row), $doctor);

        return response()->json(['success' => true, 'message' => __('ظهرت الآن ضمن أطباء المستشفى.'), 'data' => $this->service->invitationsFor($doctor)]);
    }

    /** DELETE /api/v2/business/hospital-invitations/{id} — decline an invitation, or leave the department. */
    public function leave(Request $request, int $row)
    {
        $doctor = User::query()->findOrFail(BusinessContext::id($request));
        $this->service->remove(HospitalDoctor::query()->where('user_id', (int) $doctor->id)->findOrFail($row), $doctor);

        return response()->json(['success' => true, 'message' => __('تم.'), 'data' => $this->service->invitationsFor($doctor)]);
    }
}
