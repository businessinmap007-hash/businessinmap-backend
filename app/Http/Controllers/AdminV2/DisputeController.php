<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function __construct(
        protected DisputeService $disputeService,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $sort = (string) $request->get('sort', 'id');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id',
            'status',
            'opened_at',
            'resolved_at',
            'closed_at',
            'created_at',
            'updated_at',
            'opened_by_user_id',
            'against_user_id',
            'platform_service_id',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $q                 = trim((string) $request->get('q', ''));
        $status            = trim((string) $request->get('status', ''));
        $platformServiceId = $request->filled('platform_service_id') ? (int) $request->get('platform_service_id') : null;
        $openedByUserId    = $request->filled('opened_by_user_id') ? (int) $request->get('opened_by_user_id') : null;
        $againstUserId     = $request->filled('against_user_id') ? (int) $request->get('against_user_id') : null;

        $disputes = Dispute::query()
            ->with([
                'platformService',
                'openedBy:id,name,email',
                'againstUser:id,name,email',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('id', $q)
                        ->orWhere('reason_code', 'like', "%{$q}%")
                        ->orWhere('reason_text', 'like', "%{$q}%")
                        ->orWhere('resolution_type', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($platformServiceId, fn ($query) => $query->where('platform_service_id', $platformServiceId))
            ->when($openedByUserId, fn ($query) => $query->where('opened_by_user_id', $openedByUserId))
            ->when($againstUserId, fn ($query) => $query->where('against_user_id', $againstUserId))
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $platformServices = PlatformService::query()
            ->orderBy('name_en')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        $users = User::query()
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'email']);

        $statuses = $this->statusOptions();

        return view('admin-v2.disputes.index', compact(
            'disputes',
            'platformServices',
            'users',
            'statuses',
            'q',
            'status',
            'platformServiceId',
            'openedByUserId',
            'againstUserId',
            'perPage',
            'sort',
            'dir'
        ));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load([
            'platformService',
            'openedBy',
            'againstUser',
        ]);

        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            $disputeable->loadMissing([
                'user',
                'business',
                'service',
                'latestDeposit',
            ]);
        }

        return view('admin-v2.disputes.show', compact('dispute', 'disputeable'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform_service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'disputeable_type'    => ['required', 'string', 'max:255'],
            'disputeable_id'      => ['required', 'integer'],
            'opened_by_user_id'   => ['required', 'integer', 'exists:users,id'],
            'against_user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'reason_code'         => ['nullable', 'string', 'max:100'],
            'reason_text'         => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = DB::transaction(function () use ($data) {
                return $this->disputeService->open(
                    platformServiceId: (int) $data['platform_service_id'],
                    disputeableType: $data['disputeable_type'],
                    disputeableId: (int) $data['disputeable_id'],
                    openedByUserId: (int) $data['opened_by_user_id'],
                    againstUserId: isset($data['against_user_id']) ? (int) $data['against_user_id'] : null,
                    actorId: auth()->id(),
                    payload: [
                        'reason_code' => $data['reason_code'] ?? null,
                        'reason_text' => $data['reason_text'] ?? null,
                    ]
                );
            });

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', 'تم فتح النزاع بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'تعذر فتح النزاع: ' . $e->getMessage());
        }
    }

    public function openForBooking(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:100'],
            'reason_text' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = DB::transaction(function () use ($booking, $data) {
                return $this->disputeService->openForBooking(
                    booking: $booking,
                    openedByUserId: auth()->id() ?: (int) $booking->user_id,
                    actorId: auth()->id(),
                    payload: [
                        'reason_code' => $data['reason_code'] ?? null,
                        'reason_text' => $data['reason_text'] ?? null,
                    ]
                );
            });

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', 'تم فتح نزاع على الحجز بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر فتح نزاع على الحجز: ' . $e->getMessage());
        }
    }

    public function setUnderReview(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open']);

        $dispute->status = 'under_review';
        if (Schema::hasColumn('disputes', 'opened_at') && empty($dispute->opened_at)) {
            $dispute->opened_at = now();
        }
        $dispute->save();

        return back()->with('success', 'تم تحويل النزاع إلى قيد المراجعة.');
    }

    public function cancel(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        $dispute->status = 'cancelled';
        if (Schema::hasColumn('disputes', 'closed_at')) {
            $dispute->closed_at = now();
        }
        $dispute->save();

        return back()->with('success', 'تم إلغاء النزاع.');
    }

    public function close(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['resolved']);

        $dispute->status = 'closed';
        if (Schema::hasColumn('disputes', 'closed_at')) {
            $dispute->closed_at = now();
        }
        $dispute->save();

        return back()->with('success', 'تم إغلاق النزاع.');
    }

    public function resolveReleaseBusiness(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        try {
            DB::transaction(function () use ($dispute) {
                $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'release_business',
                    resolutionPayload: [],
                    actorId: auth()->id()
                );
            });

            return back()->with('success', 'تم حل النزاع وتحويل القرار إلى Release Business.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر حل النزاع: ' . $e->getMessage());
        }
    }

    public function resolveRefundClient(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        try {
            DB::transaction(function () use ($dispute) {
                $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'refund_client',
                    resolutionPayload: [],
                    actorId: auth()->id()
                );
            });

            return back()->with('success', 'تم حل النزاع وتحويل القرار إلى Refund Client.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر حل النزاع: ' . $e->getMessage());
        }
    }

    public function resolveSplit(Request $request, Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        $data = $request->validate([
            'client_percent'   => ['required', 'numeric', 'min:0', 'max:100'],
            'business_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $clientPercent   = (float) $data['client_percent'];
        $businessPercent = (float) $data['business_percent'];

        if (round($clientPercent + $businessPercent, 2) !== 100.00) {
            throw ValidationException::withMessages([
                'client_percent' => 'مجموع النسب يجب أن يساوي 100%.',
            ]);
        }

        try {
            DB::transaction(function () use ($dispute, $clientPercent, $businessPercent, $data) {
                $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'split',
                    resolutionPayload: [
                        'client_percent'   => $clientPercent,
                        'business_percent' => $businessPercent,
                        'notes'            => $data['notes'] ?? null,
                    ],
                    actorId: auth()->id()
                );
            });

            return back()->with('success', 'تم حل النزاع بنسبة توزيع بين الطرفين.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر تنفيذ split: ' . $e->getMessage());
        }
    }

    public function resolveNoAction(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        try {
            DB::transaction(function () use ($dispute) {
                $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'no_action',
                    resolutionPayload: [],
                    actorId: auth()->id()
                );
            });

            return back()->with('success', 'تم حل النزاع بدون إجراء مالي.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر إنهاء النزاع: ' . $e->getMessage());
        }
    }

    protected function resolveDisputeable(Dispute $dispute): mixed
    {
        if (
            isset($dispute->disputeable_type, $dispute->disputeable_id) &&
            $dispute->disputeable_type &&
            $dispute->disputeable_id &&
            class_exists($dispute->disputeable_type)
        ) {
            try {
                return $dispute->disputeable_type::find($dispute->disputeable_id);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected function statusOptions(): array
    {
        return [
            'open'         => 'Open',
            'under_review' => 'Under Review',
            'resolved'     => 'Resolved',
            'closed'       => 'Closed',
            'cancelled'    => 'Cancelled',
        ];
    }

    protected function ensureDisputeStatus(Dispute $dispute, array $allowed): void
    {
        if (! in_array($dispute->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'الحالة الحالية للنزاع لا تسمح بهذه العملية.',
            ]);
        }
    }
}