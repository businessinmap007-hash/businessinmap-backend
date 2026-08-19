<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\BookingReminderService;
use App\Services\BookingShapeResolver;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Services\ServiceEventDispatcher;
use App\Services\ServiceExecutionEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BookingController extends Controller
{
    public function __construct(
        protected ServiceExecutionEngine $serviceExecutionEngine,
        protected ServiceEventDispatcher $serviceEventDispatcher,
        protected BookingReminderService $bookingReminderService,
        protected BookingGuaranteeIntegration $bookingGuaranteeIntegration,
        protected AgendaService $agenda,
        protected BookingShapeResolver $bookingShapes
    ) {
    }

    /**
     * شكل شاشة الحجز عند هذا النشاط — ما يُعرض، وما يُشترط، وبأى اسم.
     *
     * التطبيق لا يعرف شيئًا عن الفنادق ولا عن البلايستيشن ولا عن العيادات:
     * يسأل هنا فيُقال له أىُّ حقلٍ يظهر وأيُّه لا يُقبل الحجز بدونه وأىُّ
     * وحداتٍ تُعرض. ولهذا لا يحتاج نشاطٌ جديد إصدارَ تطبيق.
     *
     * والأسماء تصل مترجَمةً بلغة الطالب: «كم نزيلًا» عند الفندق و«كم فردًا»
     * عند المطعم — وهما فى قاعدة البيانات عمودٌ واحد.
     *
     * وتصنيفٌ بلا نمط يردّ `shape = null` بدل خطأ: الغياب سكوتٌ لا حكم،
     * والتطبيق يرسم عندئذٍ شاشته العامّة كما كان يفعل قبل الأنماط.
     */
    public function form(Request $request, int $business)
    {
        $target = User::query()
            ->where('id', $business)
            ->where('type', User::TYPE_BUSINESS)
            ->first(['id', 'name', 'category_id', 'category_child_id']);

        if (! $target) {
            return $this->error(__('البزنس غير موجود أو غير صحيح.'), 404);
        }

        $shape = $this->bookingShapes->forBusiness((int) $target->id);

        if (! $shape) {
            return response()->json([
                'success' => true,
                'data' => ['business_id' => (int) $target->id, 'shape' => null],
            ]);
        }

        $required = $shape['requires'];

        $fields = array_map(fn (string $field) => [
            'key' => $field,
            'label' => __('booking.field.' . $field),
            'required' => in_array($field, $required, true),
        ], $shape['asks']);

        return response()->json([
            'success' => true,
            'data' => [
            'business_id' => (int) $target->id,
            'shape' => [
                'pattern' => $shape['pattern'],
                'pattern_label' => $shape['label'],
                'available_patterns' => $shape['available_patterns'],
                'unit' => $shape['unit'],
                'fields' => $fields,
                'slot_minutes' => $shape['slot_minutes'],
                'min_nights' => $shape['min_nights'],
                'lead_time_minutes' => $shape['lead_time_minutes'],
                'visit_mode' => $shape['visit_mode'],
                'channels' => $shape['channels'],
                'notes_label' => $shape['notes_label'] ?: __('booking.field.notes'),
            ],
            // الوحدات لا تُعرض إلا حين يكون لها معنى فى هذا الشكل.
            'units' => $shape['unit'] === \App\Enums\BookingPattern::UNIT_NEVER ? [] : $this->unitsOf($target),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function unitsOf(User $business): array
    {
        return BookableItem::query()
            ->where('business_id', $business->id)
            ->where('is_active', 1)
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'item_type', 'capacity', 'quantity'])
            ->map(fn (BookableItem $item) => [
                'id' => (int) $item->id,
                'title' => $item->title,
                'code' => $item->code,
                'item_type' => $item->item_type,
                'capacity' => $item->capacity,
                'quantity' => $item->quantity,
            ])->all();
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
                'business_id' => __('البزنس غير موجود أو غير صحيح.'),
            ]);
        }

        // Mutual conflict guard (before any pricing work): a timed booking can't
        // overlap anything already on the customer's agenda — another booking, a
        // clinic appointment, or a personal task.
        if (! empty($data['starts_at'])) {
            [$start, $end] = $this->agenda->blockingWindow(
                Carbon::parse($data['starts_at']),
                ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
                (bool) ($data['all_day'] ?? false),
            );
            $this->agenda->assertFree((int) $user->id, $start, $end, null, 'starts_at');
        }

        // ما يشترطه شكلُ هذا النشاط تحديدًا — قبل أىِّ عملِ تسعير.
        $this->serviceExecutionEngine->assertShapeSatisfied((int) $data['business_id'], $data);

        $quantity = max((int) ($data['quantity'] ?? 1), 1);
        $bookableId = ! empty($data['bookable_id']) ? (int) $data['bookable_id'] : null;
        $offering = $this->resolveOffering($data, (int) $data['business_id']);

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: (int) $data['business_id'],
            serviceId: (int) $data['service_id'],
            bookableId: $bookableId,
            quantity: $quantity,
            pricingDate: $data['starts_at'] ?? $data['date'] ?? now(),
            // Only a price row can decide an amount. A listing says what the
            // booking is about — a flat's asking price is not what a viewing
            // costs — so it never reaches the pricing ladder.
            offeringId: $offering instanceof \App\Models\BusinessServicePrice ? (int) $offering->id : null
        );

        $bookable = $calc['bookable'] ?? null;

        // A room already taken for these nights must not be sold twice.
        $this->serviceExecutionEngine->assertBookableAvailable(
            $bookable,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null
        );

        $data = $this->applyKindGranularity($data, $business, $bookable);

        $payload = [
            'user_id' => (int) $user->id,
            'business_id' => (int) $data['business_id'],
            'service_id' => (int) $data['service_id'],
            /*
             * `date` and `time` are NOT NULL and predate the window columns, so
             * a caller that books a RANGE — which is the only way to book a
             * stay, a rented flat or a rented car — sent no `date` and got a
             * 500 out of the driver. They are the start of the window when the
             * caller did not name them separately; a whole-day booking has no
             * meaningful clock time, so it takes midnight.
             */
            'date' => $data['date'] ?? (! empty($data['starts_at'])
                ? Carbon::parse($data['starts_at'])->toDateString()
                : now()->toDateString()),
            'time' => $data['time'] ?? (! empty($data['starts_at']) && empty($data['all_day'])
                ? Carbon::parse($data['starts_at'])->format('H:i:s')
                : '00:00:00'),
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
            // keep what the booking is about: it is what lets it call itself
            // «كشف — عظام» rather than «حجز #4127»
            'offering_type' => $offering
                ? $offering->getMorphClass()
                : optional($calc['business_price'] ?? null)?->getMorphClass(),
            'offering_id' => $offering
                ? (int) $offering->id
                : optional($calc['business_price'] ?? null)?->id,
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
                'status' => __('لا يمكن تعديل حجز في حالة نهائية.'),
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

    /**
     * What the customer was looking at when they booked, checked against the
     * business that is being booked.
     *
     * A listing may only be booked when the business actually offers booking —
     * a menu item is otherwise something you ORDER, and letting it be booked
     * would create appointments no merchant expects.
     */
    /**
     * The kind decides the unit, not the caller.
     *
     * «يكون البوكينج باليوم والعيادات بالساعة». `duration_unit` used to arrive
     * from the app and be checked against the enum alone, so «day» on a كشف was
     * accepted and three live bookings were written with no unit at all. A
     * booking kind knows what it is measured in — see
     * PlatformServiceItemType::granularity() — so a request that contradicts it
     * is refused and a request that omits it is completed.
     *
     * The kind is known when the customer names a unit (rooms and rental cars
     * carry their own `item_type`), and otherwise only when the child offers
     * exactly one kind — with several on offer and nothing named, there is
     * genuinely nothing to derive from, and the caller keeps its old freedom.
     */
    private function applyKindGranularity(array $data, User $business, $bookable): array
    {
        $kindKey = $bookable
            ? trim((string) ($bookable->item_type ?? ''))
            : $this->soleKindOf($business, (int) $data['service_id']);

        if ($kindKey === '' || $kindKey === null) {
            return $data;
        }

        $kind = PlatformServiceItemType::query()
            ->where('platform_service_id', (int) $data['service_id'])
            ->where('key', $kindKey)
            ->first();

        $granularity = $kind?->granularity();

        if (! $granularity) {
            return $data;
        }

        $sent = trim((string) ($data['duration_unit'] ?? ''));

        if ($sent !== '' && $sent !== $granularity['unit']) {
            throw ValidationException::withMessages([
                'duration_unit' => __('«:kind» يُحجز بوحدة :unit.', [
                    'kind' => $kind->name_ar ?: $kind->key,
                    'unit' => __($granularity['unit']),
                ]),
            ]);
        }

        $data['duration_unit'] = $granularity['unit'];

        if (empty($data['duration_value'])) {
            // One slot of this kind, expressed in its own unit.
            $data['duration_value'] = $granularity['unit'] === 'minute'
                ? $granularity['slot_minutes']
                : ($granularity['unit'] === 'hour'
                    ? max((int) round($granularity['slot_minutes'] / 60), 1)
                    : 1);
        }

        // A stay occupies whole days; a clinic slot never does.
        if (! array_key_exists('all_day', $data) || $data['all_day'] === null) {
            $data['all_day'] = $granularity['all_day'];
        }

        return $data;
    }

    /**
     * The one kind this business's child offers, or null when it offers several
     * — in which case an unnamed unit tells us nothing about which was meant.
     */
    private function soleKindOf(User $business, int $serviceId): ?string
    {
        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', (int) $business->category_id)
            ->where('child_id', (int) $business->category_child_id)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->value('config'), true) ?: [];

        $kinds = collect($config['allowed_item_types'] ?? [])
            ->map(fn ($kind) => trim((string) $kind))
            ->filter()
            ->unique()
            ->values();

        return $kinds->count() === 1 ? $kinds->first() : null;
    }

    private function resolveOffering(array $data, int $businessId)
    {
        $id = (int) ($data['offering_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        $type = (string) ($data['offering_type'] ?? 'service_price');

        if ($type === 'menu_item') {
            $item = \App\Models\MenuItem::query()
                ->where('id', $id)
                ->where('business_id', $businessId)
                ->where('is_active', 1)
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'offering_id' => __('هذا العرض غير موجود لدى هذا النشاط.'),
                ]);
            }

            return $item;
        }

        return \App\Models\BusinessServicePrice::query()
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->where('is_active', 1)
            ->first();
    }

    private function relations(bool $details = false): array
    {
        $relations = [
            'user:id,name,type,phone,email,logo,image',
            'business:id,name,type,phone,email,logo,image,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit',
            'bookable',
            // the booking names itself from these; loading them here keeps
            // Booking::title() from costing a query per row
            'offering',
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
            // What the customer was looking at. A price row names «كشف عظام»
            // rather than «كشف»; a listing is what a showroom or an estate
            // agent is booked ON. Optional either way.
            'offering_id' => ['nullable', 'integer', 'min:1'],
            'offering_type' => ['nullable', Rule::in(['service_price', 'menu_item'])],
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
