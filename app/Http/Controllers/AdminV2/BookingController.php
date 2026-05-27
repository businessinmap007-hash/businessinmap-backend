<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\BusinessServicePrice;
use App\Models\BookableItem;
use App\Services\BookingDepositService;
use App\Services\ServiceExecutionEngine;
use App\Models\CategoryChildServiceFee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const EXECUTION_FEE_CODE = CategoryChildServiceFee::DEFAULT_FEE_CODE;

    public function __construct(
        protected BookingDepositService $bookingDepositService,
        protected ServiceExecutionEngine $serviceExecutionEngine
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $sort = (string) $request->get('sort', 'starts_at');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'starts_at', 'ends_at', 'status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'starts_at';
        }

        $qValue = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $date   = trim((string) $request->get('date', ''));

        $query = Booking::query()
            ->with([
                'user:id,name,phone,email',
                'business:id,name,phone,email,category_id,category_child_id',
                'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
                'bookable',
            ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($date !== '') {
            $query->whereDate('starts_at', $date);
        }

        if ($qValue !== '') {
            $query->where(function ($sub) use ($qValue) {
                if (is_numeric($qValue)) {
                    $numeric = (int) $qValue;

                    $sub->orWhere('id', $numeric)
                        ->orWhere('user_id', $numeric)
                        ->orWhere('business_id', $numeric)
                        ->orWhere('service_id', $numeric);
                }

                $sub->orWhere('notes', 'like', "%{$qValue}%");
            });
        }

        $rows = $query
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.bookings.index', [
            'rows' => $rows,
            'q' => $qValue,
            'status' => $status,
            'date' => $date,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    public function create()
    {
        return view('admin-v2.bookings.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateBooking($request, false);

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: (int) $data['business_id'],
            serviceId: (int) $data['service_id'],
            bookableId: ! empty($data['bookable_id']) ? (int) $data['bookable_id'] : null,
            quantity: (int) ($data['quantity'] ?? 1)
        );

        $bookable = $calc['bookable'] ?? null;

        $data['price'] = (float) data_get($calc, 'price_breakdown.final_price', 0);
        $data = $this->applyBookableToPayload($data, $bookable);

        $data['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: [],
            calc: $calc,
            bookable: $bookable
        );

        $booking = Booking::create($data);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم إنشاء الحجز بنجاح.');
    }

    public function show(Booking $booking)
    {
        $booking = Booking::withTrashed()->findOrFail($booking->id);

        $booking->load([
            'user:id,name,code,type',
            'business:id,name,code,type,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
            'bookable',
            'latestDispute',
        ]);

        $deposit = $this->latestDeposit($booking);
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);
        $depositPolicy = $this->depositPolicy($booking);
        $latestDispute = $booking->latestDispute;

        return view('admin-v2.bookings.show', compact(
            'booking',
            'deposit',
            'clientConfirmed',
            'businessConfirmed',
            'depositPolicy',
            'latestDispute'
        ));
    }

    public function edit(Booking $booking)
    {
        return view('admin-v2.bookings.edit', array_merge(
            $this->formData(),
            ['booking' => $booking]
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $oldStatus = (string) $booking->status;
        $data = $this->validateBooking($request, true);

        $serviceId  = (int) ($data['service_id'] ?? $booking->service_id);
        $businessId = (int) ($data['business_id'] ?? $booking->business_id);
        $quantity   = (int) ($data['quantity'] ?? $booking->quantity ?? 1);

        $bookableId = ! empty($data['bookable_id'] ?? $booking->bookable_id)
            ? (int) ($data['bookable_id'] ?? $booking->bookable_id)
            : null;

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: $businessId,
            serviceId: $serviceId,
            bookableId: $bookableId,
            quantity: $quantity
        );

        $bookable = $calc['bookable'] ?? null;

        $data['price'] = (float) data_get($calc, 'price_breakdown.final_price', 0);
        $data = $this->applyBookableToPayload($data, $bookable);

        $existingMeta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $incomingMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $mergedMeta = array_replace_recursive($existingMeta, $incomingMeta);

        $data['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: $mergedMeta,
            calc: $calc,
            bookable: $bookable
        );

        DB::transaction(function () use ($booking, $data, $oldStatus) {
            $booking->fill($data);
            $booking->save();

            $newStatus = (string) $booking->status;

            if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {
                $this->handleMoveToInProgress($booking);
            }
        });

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم تحديث الحجز بنجاح.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()
            ->route('admin.bookings.index')
            ->with('success', 'تم حذف الحجز.');
    }

    public function serviceLookup(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $services = PlatformService::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('key', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get([
                'id',
                'key',
                'name_ar',
                'name_en',
                'supports_deposit',
                'max_deposit_percent',
                'fee_type',
                'fee_value',
            ]);

        return response()->json([
            'ok' => true,
            'services' => $services,
        ]);
    }

    public function bookableItemsLookup(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId  = (int) $request->get('service_id', 0);
        $term       = trim((string) $request->get('q', ''));

        $query = BookableItem::query()->where('is_active', 1);

        if ($businessId > 0) {
            $query->where('business_id', $businessId);
        }

        if ($serviceId > 0) {
            $query->where('service_id', $serviceId);
        }

        if ($term !== '') {
            $query->where(function ($sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('item_type', 'like', "%{$term}%");
            });
        }

        $rows = $query
            ->orderBy('title')
            ->limit(50)
            ->get([
                'id',
                'business_id',
                'service_id',
                'item_type',
                'title',
                'code',
                'price',
                'capacity',
                'quantity',
                'deposit_enabled',
                'deposit_percent',
            ]);

        return response()->json([
            'ok' => true,
            'items' => $rows,
        ]);
    }

    public function pricingPreview(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId  = (int) $request->get('service_id', 0);
        $bookableId = (int) $request->get('bookable_id', 0);
        $quantity   = max((int) $request->get('quantity', 1), 1);

        $startsAt = $request->get('starts_at');
        $endsAt   = $request->get('ends_at');

        if ($businessId <= 0 || $serviceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'business_id و service_id مطلوبان',
            ], 422);
        }

        try {
            $calc = $this->serviceExecutionEngine->preview(
                businessId: $businessId,
                serviceId: $serviceId,
                bookableId: $bookableId > 0 ? $bookableId : null,
                quantity: $quantity,
                startsAt: $startsAt,
                endsAt: $endsAt
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'تعذر حساب التسعير أو التوفر.',
            ], 500);
        }

        $availability = $calc['availability'] ?? null;

        if ($availability && ! ($availability['available'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $availability['reason'] ?? 'العنصر غير متاح في الفترة المحددة',
                'code' => $availability['code'] ?? 'unavailable',
                'availability' => [
                    'available' => false,
                    'starts_at' => $availability['starts_at'] ?? $startsAt,
                    'ends_at' => $availability['ends_at'] ?? $endsAt,
                    'conflicts_count' => isset($availability['conflicts']) ? $availability['conflicts']->count() : 0,
                    'conflicts' => isset($availability['conflicts'])
                        ? $availability['conflicts']->map(function ($slot) {
                            return [
                                'id' => (int) $slot->id,
                                'block_type' => (string) ($slot->block_type ?? ''),
                                'reason' => (string) ($slot->reason ?? ''),
                                'starts_at' => optional($slot->starts_at)->toDateTimeString(),
                                'ends_at' => optional($slot->ends_at)->toDateTimeString(),
                            ];
                        })->values()
                        : [],
                ],
            ], 422);
        }

        $service = $calc['service'];
        $businessPrice = $calc['business_price'];
        $bookable = $calc['bookable'] ?? null;

        return response()->json([
            'ok' => true,
            'service' => [
                'id' => (int) $service->id,
                'key' => (string) ($service->key ?? ''),
                'name_ar' => (string) ($service->name_ar ?? ''),
                'name_en' => (string) ($service->name_en ?? ''),
                'supports_deposit' => (bool) ($service->supports_deposit ?? false),
                'max_deposit_percent' => (int) ($service->max_deposit_percent ?? 0),
                'fee_type' => (string) ($service->fee_type ?? ''),
                'fee_value' => $service->fee_value !== null ? (float) $service->fee_value : null,
            ],
            'business_price' => [
                'price' => (float) ($businessPrice->price ?? 0),
                'discount_enabled' => (bool) ($businessPrice->discount_enabled ?? false),
                'discount_percent' => (int) ($businessPrice->discount_percent ?? 0),
                'deposit_enabled' => (bool) ($businessPrice->deposit_enabled ?? false),
                'deposit_percent' => (int) ($businessPrice->deposit_percent ?? 0),
                'child_id' => (int) ($businessPrice->child_id ?? 0),
            ],
            'bookable' => $bookable ? [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'price' => (float) $bookable->price,
                'deposit_enabled' => (bool) ($bookable->deposit_enabled ?? false),
                'deposit_percent' => (int) ($bookable->deposit_percent ?? 0),
                'capacity' => $bookable->capacity !== null ? (int) $bookable->capacity : null,
                'quantity' => $bookable->quantity !== null ? (int) $bookable->quantity : null,
            ] : null,
            'fee_snapshot' => $calc['fee_snapshot'] ?? [],
            'availability' => $availability ? [
                'available' => (bool) ($availability['available'] ?? false),
                'code' => (string) ($availability['code'] ?? ''),
                'reason' => $availability['reason'] ?? null,
                'starts_at' => $availability['starts_at'] ?? $startsAt,
                'ends_at' => $availability['ends_at'] ?? $endsAt,
            ] : null,
            'pricing' => $calc['price_breakdown'] ?? [],
            'deposit_policy' => $calc['deposit_policy'] ?? [],
        ]);
    }

    public function startConfirmClient(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->client_confirmed = true;
                $deposit->save();

                return;
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['client'] = 1;
            $meta['_start_confirm']['client_at'] = now()->toDateTimeString();

            $booking->meta = $meta;
            $booking->save();
        });

        return back()->with('success', 'تم تأكيد العميل.');
    }

    public function startConfirmBusiness(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->business_confirmed = true;
                $deposit->save();

                return;
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['business'] = 1;
            $meta['_start_confirm']['business_at'] = now()->toDateTimeString();

            $booking->meta = $meta;
            $booking->save();
        });

        return back()->with('success', 'تم تأكيد البزنس.');
    }

    public function depositConfirmClient(Booking $booking)
    {
        return $this->startConfirmClient($booking);
    }

    public function depositConfirmBusiness(Booking $booking)
    {
        return $this->startConfirmBusiness($booking);
    }

    public function depositFreeze(Booking $booking)
    {
        $depositPolicy = $this->depositPolicy($booking);

        if (! ($depositPolicy['required'] ?? false)) {
            return back()->with('error', 'Deposit غير مفعل لهذا الحجز.');
        }

        try {
            $this->bookingDepositService->freezeForBooking(
                $booking,
                (float) ($depositPolicy['hold'] ?? $depositPolicy['amount'] ?? 0)
            );

            return back()->with('success', 'تم إنشاء Deposit وتجميد المبالغ بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر إنشاء الـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositRelease(Booking $booking)
    {
        try {
            $this->bookingDepositService->releaseForBooking($booking);

            return back()->with('success', 'تم Release للـ Deposit بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر Release للـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositRefund(Booking $booking)
    {
        try {
            $this->bookingDepositService->refundForBooking($booking);

            return back()->with('success', 'تم Refund للـ Deposit بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر Refund للـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositDisputeOpen(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:100'],
            'reason_text' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = $this->bookingDepositService->openDisputeForBooking(
                booking: $booking,
                openedByUserId: auth()->id() ?: (int) $booking->user_id,
                actorId: auth()->id(),
                payload: [
                    'reason_code' => $data['reason_code'] ?? null,
                    'reason_text' => $data['reason_text'] ?? null,
                ]
            );

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', 'تم فتح النزاع بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر فتح النزاع: ' . $e->getMessage());
        }
    }

    public function depositAgreeRelease(Booking $booking)
    {
        try {
            $this->bookingDepositService->releaseForBooking($booking);

            return back()->with('success', 'تمت الموافقة وتنفيذ Release.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر تنفيذ Release: ' . $e->getMessage());
        }
    }

    public function depositAgreeRefund(Booking $booking)
    {
        try {
            $this->bookingDepositService->refundForBooking($booking);

            return back()->with('success', 'تمت الموافقة وتنفيذ Refund.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر تنفيذ Refund: ' . $e->getMessage());
        }
    }

    protected function formData(): array
    {
        $services = PlatformService::query()
            ->select([
                'id',
                'key',
                'name_ar',
                'name_en',
                'is_active',
                'supports_deposit',
                'max_deposit_percent',
                'fee_type',
                'fee_value',
            ])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name', 'category_child_id'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $clients = User::query()
            ->select(['id', 'name'])
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'business');
            })
            ->orderBy('name')
            ->limit(300)
            ->get();

        $businessServicePrices = BusinessServicePrice::query()
            ->select([
                'business_id',
                'child_id',
                'service_id',
                'price',
                'deposit_enabled',
                'deposit_percent',
                'discount_enabled',
                'discount_percent',
            ])
            ->get();

        return [
            'statusOptions' => Booking::statusOptions(),
            'services' => $services,
            'businesses' => $businesses,
            'clients' => $clients,
            'businessServicePrices' => $businessServicePrices,
        ];
    }

    protected function validateBooking(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],

            'bookable_type' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', Rule::in([BookableItem::class, 'bookable_item', 'bookable_items'])],
            'bookable_id' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer'],

            'starts_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date'],
            'ends_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date', 'after:starts_at'],

            'duration_value' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'duration_unit' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', Rule::in(['minute', 'hour', 'day', 'week', 'month', 'year'])],

            'all_day' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'boolean'],
            'timezone' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:64'],

            'quantity' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'party_size' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],

            'status' => ['required', Rule::in(array_keys(Booking::statusOptions()))],
            'notes' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string'],

            'date' => [$isUpdate ? 'sometimes' : 'required', 'nullable', 'date'],
            'time' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable'],
        ];

        $data = $request->validate($rules);

        $data['all_day'] = (int) $request->boolean('all_day');
        $data['quantity'] = max((int) ($data['quantity'] ?? 1), 1);

        if (empty($data['timezone'])) {
            $data['timezone'] = 'Africa/Cairo';
        }

        $data['meta'] = is_array($request->input('meta')) ? $request->input('meta') : [];

        $durationUnit  = (string) ($data['duration_unit'] ?? 'day');
        $durationValue = max((int) ($data['duration_value'] ?? $data['quantity'] ?? 1), 1);

        $date = $data['date'] ?? null;
        $time = $data['time'] ?? null;

        if (! empty($data['starts_at'])) {
            $start = Carbon::parse($data['starts_at'], $data['timezone']);
        } elseif ($date) {
            if ($durationUnit === 'day') {
                $start = Carbon::parse($date . ' 00:00:00', $data['timezone']);
                $data['all_day'] = 1;
            } else {
                $startTime = $time ?: '00:00';
                $start = Carbon::parse($date . ' ' . $startTime, $data['timezone']);
            }
        } else {
            $start = null;
        }

        if ($start) {
            if (! empty($data['ends_at'])) {
                $end = Carbon::parse($data['ends_at'], $data['timezone']);
            } else {
                $end = match ($durationUnit) {
                    'day' => (clone $start)->addDays($durationValue),
                    'hour' => (clone $start)->addHours($durationValue),
                    'minute' => (clone $start)->addMinutes($durationValue),
                    'week' => (clone $start)->addWeeks($durationValue),
                    'month' => (clone $start)->addMonths($durationValue),
                    'year' => (clone $start)->addYears($durationValue),
                    default => null,
                };
            }

            $data['starts_at'] = $start->copy()->utc();
            $data['ends_at'] = $end ? $end->copy()->utc() : null;
            $data['date'] = $start->toDateString();
            $data['time'] = $start->format('H:i:s');

            if ($end) {
                if ($durationUnit === 'day') {
                    $data['duration_value'] = $start->diffInDays($end);
                } elseif ($durationUnit === 'hour') {
                    $data['duration_value'] = max(1, $start->diffInHours($end));
                } elseif ($durationUnit === 'minute') {
                    $data['duration_value'] = max(1, $start->diffInMinutes($end));
                } else {
                    $data['duration_value'] = $durationValue;
                }
            } else {
                $data['duration_value'] = $durationValue;
            }

            $data['duration_unit'] = $durationUnit;
        }

        if ($isUpdate) {
            if (array_key_exists('date', $data) && $data['date'] === null) {
                unset($data['date']);
            }

            if (array_key_exists('time', $data) && $data['time'] === null) {
                unset($data['time']);
            }
        }

        $businessId = (int) ($data['business_id'] ?? 0);
        $serviceId  = (int) ($data['service_id'] ?? 0);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        $businessService = $this->resolveBusinessServicePrice(
            $businessId,
            $serviceId,
            $childId
        );

        if (! $businessService) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير متاحة لهذا البزنس داخل هذا القسم الفرعي.',
            ]);
        }

        if (! empty($data['bookable_id'])) {
            $bookable = BookableItem::query()
                ->where('id', (int) $data['bookable_id'])
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->first();

            if (! $bookable) {
                throw ValidationException::withMessages([
                    'bookable_id' => 'العنصر القابل للحجز غير موجود أو غير تابع لهذا البزنس/الخدمة.',
                ]);
            }

            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

        return $data;
    }

    protected function latestDeposit(Booking $booking): ?Deposit
    {
        return $this->bookingDepositService->latestDeposit($booking);
    }

    protected function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        if ($deposit) {
            return [
                (bool) $deposit->client_confirmed,
                (bool) $deposit->business_confirmed,
            ];
        }

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $sc = $meta['_start_confirm'] ?? [];

        return [
            ! empty($sc['client']),
            ! empty($sc['business']),
        ];
    }

    protected function depositPolicy(Booking $booking): array
    {
        return $this->serviceExecutionEngine->depositPolicy($booking);
    }

    protected function handleMoveToInProgress(Booking $booking): void
    {
        $this->serviceExecutionEngine->moveBookingToInProgress($booking);
    }
////////////////
    protected function chargeExecutionFeeSplitOnce(Booking $booking): void
    {
        $this->serviceExecutionEngine->chargeExecutionFeeOnce($booking);
    }
/////////////////
    protected function resolveSelectedBookable(
        int $businessId,
        int $serviceId,
        ?string $bookableType,
        $bookableId
    ): ?BookableItem {
        if (empty($bookableId)) {
            return null;
        }

        $normalizedType = $bookableType ?: BookableItem::class;
        $accepted = [BookableItem::class, 'bookable_item', 'bookable_items'];

        if (! in_array($normalizedType, $accepted, true)) {
            return null;
        }

        return BookableItem::query()
            ->where('id', (int) $bookableId)
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();
    }

    protected function applyBookableToPayload(array $data, ?BookableItem $bookable): array
    {
        if ($bookable) {
            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

        return $data;
    }

    protected function resolveBusinessContext(int $businessId): array
    {
        if (! $businessId) {
            return [null, 0, 0];
        }

        $business = User::query()
            ->select(['id', 'name', 'type', 'category_id', 'category_child_id'])
            ->where('id', $businessId)
            ->where('type', 'business')
            ->first();

        if (! $business) {
            return [null, 0, 0];
        }

        return [
            $business,
            (int) ($business->category_id ?? 0),
            (int) ($business->category_child_id ?? 0),
        ];
    }

    protected function resolveBusinessServicePrice(int $businessId, int $serviceId, int $childId = 0): ?BusinessServicePrice
    {
        if ($businessId <= 0 || $serviceId <= 0) {
            return null;
        }

        if ($childId > 0) {
            $row = BusinessServicePrice::query()
                ->where('business_id', $businessId)
                ->where('child_id', $childId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return $row;
            }
        }

        return BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
    }
}