<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingReminderService;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Services\ServiceEventDispatcher;
use App\Services\ServiceExecutionEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BookingController extends Controller
{
    public function __construct(
        protected ServiceExecutionEngine $serviceExecutionEngine,
        protected ServiceEventDispatcher $serviceEventDispatcher,
        protected BookingReminderService $bookingReminderService,
        protected BookingGuaranteeIntegration $bookingGuaranteeIntegration
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $scope = trim((string) $request->get('scope', 'my'));
        $status = trim((string) $request->get('status', ''));
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $query = Booking::query()
            ->with($this->relations())
            ->latest('id');

        if ($scope === 'business') {
            if (! $user || ! $user->isBusiness()) {
                return $this->error('Business account is required.', 403);
            }

            $query->where('business_id', (int) $user->id);
        } else {
            $query->where('user_id', (int) $user->id);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $query->paginate($perPage),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->isClient()) {
            return $this->error('Client account is required.', 403);
        }

        $data = $request->validate($this->storeRules());

        $business = User::query()
            ->where('id', (int) $data['business_id'])
            ->where('type', User::TYPE_BUSINESS)
            ->first();

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        $quantity = max((int) ($data['quantity'] ?? 1), 1);
        $bookableId = ! empty($data['bookable_id']) ? (int) $data['bookable_id'] : null;

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: (int) $data['business_id'],
            serviceId: (int) $data['service_id'],
            bookableId: $bookableId,
            quantity: $quantity,
            pricingDate: $data['starts_at'] ?? $data['date'] ?? now()
        );

        $bookable = $calc['bookable'] ?? null;

        $payload = [
            'user_id' => (int) $user->id,
            'business_id' => (int) $data['business_id'],
            'service_id' => (int) $data['service_id'],
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'duration_value' => $data['duration_value'] ?? null,
            'duration_unit' => $data['duration_unit'] ?? null,
            'all_day' => (bool) ($data['all_day'] ?? false),
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'quantity' => $quantity,
            'party_size' => $data['party_size'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => Booking::STATUS_PENDING,
            'price' => (float) data_get($calc, 'price_breakdown.final_price', 0),
        ];

        if ($bookable instanceof BookableItem) {
            $payload['bookable_type'] = $bookable->getMorphClass();
            $payload['bookable_id'] = (int) $bookable->id;
        }

        $payload['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: array_merge($data['meta'] ?? [], [
                'source' => 'api_v2',
                'created_by_user_id' => (int) $user->id,
            ]),
            calc: $calc,
            bookable: $bookable
        );

        $booking = DB::transaction(fn () => Booking::query()->create($payload));
        $booking->refresh()->load($this->relations());

        $this->serviceEventDispatcher->bookingRequested(
            booking: $booking,
            actorId: (int) $user->id,
            payload: $this->eventPayload($booking, 'api_v2.store')
        );

        $this->bookingReminderService->scheduleForBooking($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking request created successfully.',
            'data' => [
                'booking' => $booking,
            ],
        ], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        $booking->load($this->relations(true));

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => $booking,
                'financial_preview' => $this->safeFinancialPreview($booking),
            ],
        ]);
    }

    public function accept(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        return $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_ACCEPTED,
            dispatcher: 'bookingAccepted',
            source: 'api_v2.business.accept',
            message: 'Booking accepted successfully.'
        );
    }

    public function reject(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        return $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_REJECTED,
            dispatcher: 'bookingRejected',
            source: 'api_v2.business.reject',
            message: 'Booking rejected successfully.'
        );
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        return $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_CANCELLED,
            dispatcher: 'bookingCancelled',
            source: 'api_v2.cancel',
            message: 'Booking cancelled successfully.'
        );
    }

    public function clientConfirm(Request $request, Booking $booking)
    {
        $this->authorizeClientBooking($request, $booking);

        $booking = DB::transaction(function () use ($booking, $request) {
            $booking->refresh();
            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['confirmations']['client'] = [
                'confirmed' => true,
                'confirmed_at' => now()->toDateTimeString(),
                'confirmed_by' => (int) $request->user()->id,
                'source' => 'api_v2.client_confirm',
            ];
            $booking->update(['meta' => $meta]);

            return $booking->refresh();
        });

        $this->serviceEventDispatcher->bookingClientConfirmed(
            booking: $booking,
            actorId: (int) $request->user()->id,
            payload: $this->eventPayload($booking, 'api_v2.client_confirm')
        );

        return $this->bookingResponse($booking, 'Client confirmation saved successfully.');
    }

    public function businessConfirm(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        $booking = DB::transaction(function () use ($booking, $request) {
            $booking->refresh();
            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['confirmations']['business'] = [
                'confirmed' => true,
                'confirmed_at' => now()->toDateTimeString(),
                'confirmed_by' => (int) $request->user()->id,
                'source' => 'api_v2.business_confirm',
            ];
            $booking->update(['meta' => $meta]);

            return $booking->refresh();
        });

        $this->serviceEventDispatcher->bookingBusinessConfirmed(
            booking: $booking,
            actorId: (int) $request->user()->id,
            payload: $this->eventPayload($booking, 'api_v2.business_confirm')
        );

        return $this->bookingResponse($booking, 'Business confirmation saved successfully.');
    }

    public function start(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        $this->serviceExecutionEngine->moveBookingToInProgress($booking);

        $booking->refresh()->load($this->relations(true));

        $this->serviceEventDispatcher->bookingStarted(
            booking: $booking,
            actorId: (int) $request->user()->id,
            payload: $this->eventPayload($booking, 'api_v2.business.start')
        );

        return $this->bookingResponse($booking, 'Booking execution started successfully.');
    }

    public function complete(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        $response = $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_COMPLETED,
            dispatcher: 'bookingCompleted',
            source: 'api_v2.business.complete',
            message: 'Booking completed successfully.'
        );

        $this->bookingGuaranteeIntegration->recordCompleted($booking->refresh());
        $this->bookingReminderService->cancelForBooking($booking);

        return $response;
    }

    public function financialPreview(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        return response()->json([
            'success' => true,
            'data' => $this->serviceExecutionEngine->financialPreview($booking),
        ]);
    }

    private function changeStatus(Request $request, Booking $booking, string $status, string $dispatcher, string $source, string $message)
    {
        if ($booking->isFinalStatus()) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعديل حجز في حالة نهائية.',
            ]);
        }

        $oldStatus = (string) $booking->status;

        $booking = DB::transaction(function () use ($booking, $status) {
            $booking->refresh();
            $booking->update(['status' => $status]);

            return $booking->refresh();
        });

        $this->serviceEventDispatcher->{$dispatcher}(
            booking: $booking,
            actorId: (int) $request->user()->id,
            payload: array_merge($this->eventPayload($booking, $source), [
                'old_status' => $oldStatus,
                'new_status' => $status,
            ])
        );

        if ($booking->isFinalStatus()) {
            $this->bookingReminderService->cancelForBooking($booking);
        } else {
            $this->bookingReminderService->scheduleForBooking($booking);
        }

        if (in_array($status, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true)) {
            $this->bookingGuaranteeIntegration->recordCancelled($booking);
        }

        return $this->bookingResponse($booking, $message);
    }

    private function bookingResponse(Booking $booking, string $message)
    {
        $booking->load($this->relations(true));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'booking' => $booking,
                'financial_preview' => $this->safeFinancialPreview($booking),
            ],
        ]);
    }

    private function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if (! $user || ! in_array((int) $user->id, [(int) $booking->user_id, (int) $booking->business_id], true)) {
            abort(403, 'Booking does not belong to this account.');
        }
    }

    private function authorizeClientBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if (! $user || (int) $booking->user_id !== (int) $user->id) {
            abort(403, 'Booking does not belong to this client account.');
        }
    }

    private function authorizeBusinessBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if (! $user || ! $user->isBusiness() || (int) $booking->business_id !== (int) $user->id) {
            abort(403, 'Booking does not belong to this business account.');
        }
    }

    private function eventPayload(Booking $booking, string $source): array
    {
        return [
            'source' => $source,
            'status' => (string) $booking->status,
            'service_id' => (int) $booking->service_id,
            'business_id' => (int) $booking->business_id,
            'client_id' => (int) $booking->user_id,
            'bookable_id' => $booking->bookable_id ? (int) $booking->bookable_id : null,
            'starts_at' => optional($booking->starts_at)->toDateTimeString(),
            'ends_at' => optional($booking->ends_at)->toDateTimeString(),
            'price' => (float) $booking->price,
        ];
    }

    private function relations(bool $details = false): array
    {
        $relations = [
            'user:id,name,type,phone,email,logo,image',
            'business:id,name,type,phone,email,logo,image,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit',
            'bookable',
        ];

        if ($details) {
            $relations[] = 'latestDeposit';
            $relations[] = 'latestDispute';
        }

        return $relations;
    }

    private function storeRules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
            'bookable_id' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration_value' => ['nullable', 'integer', 'min:1'],
            'duration_unit' => ['nullable', Rule::in(['minute', 'hour', 'day', 'night'])],
            'all_day' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'party_size' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
        ];
    }

    private function safeFinancialPreview(Booking $booking): ?array
    {
        try {
            return $this->serviceExecutionEngine->financialPreview($booking);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function error(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
