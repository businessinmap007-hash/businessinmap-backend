<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use App\Services\AllocationConsumptionService;
use App\Services\BookingReminderService;
use App\Services\BookingShapeResolver;
use App\Services\FinancialLedgerService;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Services\ServiceEventDispatcher;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use App\Support\BusinessContext;
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
        protected BookingShapeResolver $bookingShapes,
        protected FinancialLedgerService $ledger,
        protected AllocationConsumptionService $allocationConsumption,
        protected WalletService $wallet,
        protected \App\Services\BookingDepositService $bookingDepositService,
        protected \App\Services\Business\StaffActivityLogger $activity
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

        // what this customer and this business have been through together, told before he books
        $relationship = \App\Support\ClientHistory::relationship((int) $target->id, (int) ($request->user()?->id ?? 0));

        if (! $shape) {
            return response()->json([
                'success' => true,
                'data' => [
                    'business_id' => (int) $target->id,
                    'relationship' => $relationship,
                    'shape' => null,
                    'arrival_notice' => \App\Services\ArrivalNotice::forBusiness((int) $target->id),
                ],
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
            'relationship' => $relationship,
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
            // «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — the business's own words, shown before the customer confirms
            'arrival_notice' => \App\Services\ArrivalNotice::forBusiness((int) $target->id),
            // الوحدات لا تُعرض إلا حين يكون لها معنى فى هذا الشكل.
            'units' => $shape['unit'] === \App\Enums\BookingPattern::UNIT_NEVER ? [] : $this->unitsOf($target),
            // وما يزيده مُوصِّفٌ على السعر: «شاشة كبيرة +٢٠». يصل مسعَّرًا حتى
            // يعرض التطبيقُ الزيادةَ قبل الحجز لا بعده.
            'modifiers' => $this->modifiersOf($target),
            ],
        ]);
    }

    /**
     * سعرٌ حيٌّ قبل الحجز — نفس حساب `store()` بالضبط («خط الأساس + مُوصِّفاته
     * لكل فترة، مضروبًا فى عدد الفترات») دون إنشاء أى صفٍّ، حتى لا يرى العميلُ
     * رقمًا يخصّه وحده ثم رقمًا آخر عند التأكيد.
     *
     * لا يشترط شيئًا لم يُختَر بعد: التاريخ أو الوحدة الغائبة تعنى معاينةً
     * جزئيةً (سعرَ الليلة الواحدة مثلًا) لا خطأً — الشاشة تستدعيها بعد كل
     * تغييرٍ بصرف النظر عمّا اكتمل من الحقول.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
            'bookable_id' => ['nullable', 'integer', 'min:1'],
            'offering_id' => ['nullable', 'integer', 'min:1'],
            'offering_type' => ['nullable', Rule::in(['service_price', 'menu_item'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'party_size' => ['nullable', 'integer', 'min:1'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            'day_use' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
        ]);

        $business = User::query()
            ->where('id', (int) $data['business_id'])
            ->where('type', User::TYPE_BUSINESS)
            ->first();

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => __('البزنس غير موجود أو غير صحيح.'),
            ]);
        }

        // «Day use»: the guest names a date, the room type names the window.
        if (! empty($data['day_use'])) {
            $data = $this->applyDayUse($data);
        }

        $offering = $this->resolveOffering($data, (int) $data['business_id']);

        $calc = $this->serviceExecutionEngine->preview(
            businessId: (int) $data['business_id'],
            serviceId: (int) $data['service_id'],
            bookableId: ! empty($data['bookable_id']) ? (int) $data['bookable_id'] : null,
            quantity: max((int) ($data['quantity'] ?? 1), 1),
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
            optionIds: $data['option_ids'] ?? [],
            partySize: max((int) ($data['party_size'] ?? 1), 1),
            offeringId: $offering instanceof \App\Models\BusinessServicePrice ? (int) $offering->id : null,
            dayUse: ! empty($data['day_use']),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'price' => $calc['price'],
                'price_breakdown' => $calc['price_breakdown'],
                'availability' => $calc['availability'],
            ],
        ]);
    }

    /**
     * المُوصِّفات المسعَّرة عند هذا النشاط، مجموعةً تحت سطرها ومجموعةِ
     * تصنيفها.
     *
     * تُقرأ من مفردات صفوف السعر لا من مفردات التصنيف: التصنيف يقول ما **يجوز**
     * وصفُه، وهذه تقول ما وضع صاحبُ المحل عليه سعرًا فعلًا. وما قيمتُه صفرٌ
     * لا يُرسَل: مُوصِّفٌ بلا سعرٍ لا شأن لشاشة الدفع به.
     *
     * من مصدرين: صفوفُ سعرٍ بعينها («إطلالة بحرية» على نوع غرفةٍ)، ونشاطٌ
     * كامل («نظام الوجبات» — راجع `BookingAddOnController`). الثانى كان
     * يُكتب ولا يُعرض قطّ للعميل قبل هذا التعديل، رغم أن محرّك التسعير كان
     * يقرأه ويُسعِّره — عيبٌ سابقٌ لا علاقة له بفردي/متعدد بذاته، انكشف أثناء
     * توحيد هذه الشاشة مع نظام `selection_type`.
     *
     * `group_id`/`group_name`/`selection_type` تصف كيف تُعرض المجموعة: راديو
     * (فردي) أو تشيك بوكس (متعدد) — من `OfferingOptionGroupSetting`، بنطاق
     * النشاط كلِّه، وهو النطاق الوحيد الذى تكتبه شاشة الإضافات اليوم.
     *
     * @return array<int, array<string, mixed>>
     */
    private function modifiersOf(User $business): array
    {
        $lineScoped = \App\Models\OfferingOption::query()
            ->join('business_service_prices as p', function ($join) {
                $join->on('p.id', '=', 'offering_options.offering_id')
                    ->where('offering_options.offering_type', '=', (new \App\Models\BusinessServicePrice)->getMorphClass());
            })
            ->leftJoin('options as o', 'o.id', '=', 'offering_options.option_id')
            ->leftJoin('options as l', 'l.id', '=', 'p.line_option_id')
            ->where('p.business_id', $business->id)
            ->where('p.is_active', 1)
            ->where('offering_options.role', \App\Models\OfferingOption::ROLE_MODIFIER)
            ->where('offering_options.adjust_value', '!=', 0)
            ->orderBy('p.id')
            ->orderBy('offering_options.sort_order')
            ->get([
                'p.id as price_id',
                'l.name_ar as line_name',
                'offering_options.option_id',
                'o.name_ar as name',
                'o.group_id as group_id',
                'offering_options.adjust_type',
                'offering_options.adjust_value',
            ]);

        $businessWide = \App\Models\OfferingOption::query()
            ->where('offering_options.offering_type', '=', (new User)->getMorphClass())
            ->where('offering_options.offering_id', '=', $business->id)
            ->leftJoin('options as o', 'o.id', '=', 'offering_options.option_id')
            ->where('offering_options.role', \App\Models\OfferingOption::ROLE_MODIFIER)
            ->where('offering_options.adjust_value', '!=', 0)
            ->orderBy('offering_options.sort_order')
            ->get([
                'offering_options.option_id',
                'o.name_ar as name',
                'o.group_id as group_id',
                'offering_options.adjust_type',
                'offering_options.adjust_value',
            ])
            // سطرٌ بعينه أخصّ من النشاط كلِّه — ما ذُكر هناك يغلب هنا.
            ->reject(fn ($r) => $lineScoped->contains('option_id', (int) $r->option_id));

        $rows = $lineScoped->concat($businessWide);

        // a hotel that lists its numbered rooms sells a view as a CARD of the room type (غرفة فردية · إطلالة بحر — 1300), never as a tick on
        // the form: what stays here is the meal plan and the extras
        if (\App\Models\BookableItemRoom::query()->where('business_id', $business->id)->exists()) {
            $featureIds = app(\App\Services\BookingRoomService::class)->featureVocabularyIds((int) $business->id);
            $rows = $rows->reject(fn ($r) => in_array((int) $r->option_id, $featureIds, true))->values();
        }

        $groupIds = $rows->pluck('group_id')->filter()->unique()->values();
        $groupNames = $groupIds->isEmpty()
            ? collect()
            : \App\Models\OptionGroup::query()->whereIn('id', $groupIds)->pluck('name_ar', 'id');

        $selectionTypes = \App\Models\OfferingOptionGroupSetting::forOwnOffering(
            (new User)->getMorphClass(),
            (int) $business->id
        );

        return $rows->map(fn ($r) => [
            'price_id' => isset($r->price_id) ? (int) $r->price_id : null,
            'line' => $r->line_name ?? null,
            'option_id' => (int) $r->option_id,
            'name' => $r->name,
            'group_id' => $r->group_id ? (int) $r->group_id : null,
            'group_name' => $r->group_id ? ($groupNames[$r->group_id] ?? null) : null,
            'applies_to' => $r->group_id && in_array($groupNames[$r->group_id] ?? '', \App\Services\BookingVocabularyRoles::DAY_USE_GROUPS, true)
                ? 'day_use'
                : 'night',
            'selection_type' => $r->group_id
                ? ($selectionTypes[$r->group_id] ?? \App\Models\OfferingOptionGroupSetting::SELECTION_MULTIPLE)
                : \App\Models\OfferingOptionGroupSetting::SELECTION_MULTIPLE,
            'adjust_type' => $r->adjust_type,
            'adjust_value' => (float) $r->adjust_value,
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function unitsOf(User $business): array
    {
        return BookableItem::query()
            ->where('business_id', $business->id)
            ->where('is_active', 1)
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'item_type', 'capacity', 'quantity', 'meta', 'is_active'])
            ->map(fn (BookableItem $item) => [
                'id' => (int) $item->id,
                'title' => $item->title,
                'code' => $item->code,
                'item_type' => $item->item_type,
                'capacity' => $item->capacity,
                'quantity' => $item->quantity,
                'day_use' => app(\App\Services\BookingDayUseService::class)->offer($item),
            ])->all();
    }

    /**
     * Bookings where THIS account (client or business) still hasn't made a
     * deposit-settlement decision on a frozen deposit — the mandatory,
     * un-skippable prompt shown right when the app opens/resumes (the
     * mobile app's HomeShell), so a completed booking never just sits there
     * with its deposit frozen because nobody remembered to go settle it.
     * Excludes anything already decided by this account, and anything with
     * a live dispute already open (that path is arbitration's job now).
     */
    public function pendingSettlements(Request $request)
    {
        $userId = (int) $request->user()->id;

        $bookings = Booking::query()
            ->whereIn('status', [Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhere('business_id', $userId);
            })
            ->with($this->relations(true))
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(function (Booking $booking) use ($userId) {
                $deposit = $booking->latestDeposit;

                if (! $deposit || ! $deposit->isFrozen()) {
                    return false;
                }

                $isClient = (int) $booking->user_id === $userId;
                $releaseAgreed = $isClient ? $deposit->release_agreed_client : $deposit->release_agreed_business;
                $refundAgreed = $isClient ? $deposit->refund_agreed_client : $deposit->refund_agreed_business;

                if ($releaseAgreed || $refundAgreed) {
                    return false;
                }

                return ! $this->bookingDepositService->hasLiveDispute($deposit);
            })
            ->values();

        $payments = app(\App\Services\BookingDepositPaymentService::class);
        $bookings->each(fn (Booking $booking) => $booking->setAttribute('deposit_as_payment', $payments->state($booking)));

        return response()->json([
            'success' => true,
            'data' => ['bookings' => $this->titled($bookings)],
        ]);
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
                'bookings' => $this->titled($query->paginate($perPage)),
            ],
        ]);
    }

    /**
     * GET /api/v2/business/bookings — the business's own incoming-booking
     * queue, reachable by the owner or a delegated staff member with the
     * bookings capability (business.member:bookings resolves BusinessContext
     * the same way it already does for accept/reject/etc.). The plain
     * `bookings?scope=business` above stays owner-only by design — it never
     * grew a BusinessContext-aware branch, which is exactly why a staff
     * member with the bookings capability had no working list endpoint to
     * call even though they could already act on an individual booking.
     */
    public function businessIndex(Request $request)
    {
        $status = trim((string) $request->get('status', ''));
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $query = Booking::query()
            ->with($this->relations())
            ->where('business_id', BusinessContext::id($request))
            // a barber sees the bookings of the chair he sits at, not the whole house
            ->when(($own = \App\Models\ServiceChair::restrictedTo($request)) !== null, fn ($q) => $q->whereIn('chair_id', $own))
            // a provider (a trainer, a barber without a chair…) sees the bookings assigned to him; the owner / reception may
            // ask for one person's list
            ->when(($mine = \App\Support\Providers::actingId($request)) !== null, fn ($q) => $q->assignedTo($mine))
            ->when(($mine ?? null) === null && $request->filled('assignee_id'), fn ($q) => $q->assignedTo((int) $request->get('assignee_id')))
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $this->titled($query->paginate($perPage)),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // «كل من له حساب يمكنه استخدام الخدمات بكل أنواعها» — المالك، 2026-10-15: a doctor, a merchant, any business with
        // an account books at another business as a customer does. The one thing it cannot book is itself.
        if (! $user) {
            return $this->error(__('يجب تسجيل الدخول للحجز.'), 401);
        }

        return $this->createBooking($request, $user, $request->validate($this->storeRules()), null);
    }

    /**
     * POST /bookings/by-business — «جاء عميل بدون حجز»: the business books a customer who is standing there. The same
     * booking as the customer's own, with the same rules; the customer is either
     *  - an ACCOUNT (named by client_id, or found by his phone / e-mail) → the booking is his, with his history; or
     *  - a NAMED GUEST with no account → the business's own account stands in and the name is written on the booking
     *    (never counted, never rated — the same convention as a walk-in turn in the queue).
     */
    public function storeForGuest(Request $request)
    {
        $businessUser = User::query()->findOrFail(\App\Support\BusinessContext::id($request));
        $request->merge(['business_id' => (int) $businessUser->id]);

        $data = $request->validate($this->storeRules() + [
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:190'],
            'client_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['business_id'] = (int) $businessUser->id;

        $customer = null;

        if (! empty($data['client_id'])) {
            $customer = User::query()->whereKey((int) $data['client_id'])->where('id', '!=', $businessUser->id)->first();
            if (! $customer) {
                throw ValidationException::withMessages(['client_id' => __('الحساب غير موجود.')]);
            }
        } else {
            $customer = \App\Support\AccountLookup::find($data['guest_phone'] ?? null, $data['guest_email'] ?? null, (int) $businessUser->id);
        }

        $name = trim((string) ($data['guest_name'] ?? ''));

        if ($customer === null && $name === '') {
            throw ValidationException::withMessages(['guest_name' => __('اكتب اسم الضيف.')]);
        }

        return $this->createBooking($request, $businessUser, $data, [
            'customer' => $customer,
            'walk_in' => $customer ? null : ['name' => $name, 'phone' => $data['guest_phone'] ?? null],
        ]);
    }

    /** GET /bookings/customer-lookup?phone=&email= — does this phone / e-mail belong to an account, and is he a returning customer. */
    public function customerLookup(Request $request)
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:190']]);
        $businessId = \App\Support\BusinessContext::id($request);

        $user = \App\Support\AccountLookup::find($data['phone'] ?? null, $data['email'] ?? null, $businessId);

        return response()->json(['success' => true, 'data' => [
            'found' => $user !== null,
            'customer' => $user ? ['id' => (int) $user->id, 'name' => $user->name] : null,
            'previous_dealings' => $user ? (\App\Support\ClientHistory::counts($businessId, [(int) $user->id])[(int) $user->id] ?? 0) : 0,
        ]]);
    }

    private function createBooking(Request $request, User $user, array $data, ?array $guest)
    {
        $business = User::query()
            ->where('id', (int) $data['business_id'])
            ->where('type', User::TYPE_BUSINESS)
            ->first();

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => __('البزنس غير موجود أو غير صحيح.'),
            ]);
        }

        if ($guest === null && (int) $business->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'business_id' => __('لا يمكنك الحجز لدى نشاطك نفسه.'),
            ]);
        }

        // the booking's customer: the account named, or — for a guest with none — the business's own account standing in
        $customerId = $guest === null ? (int) $user->id : (int) ($guest['customer']?->id ?? $business->id);

        // «اختر مقدّم الخدمة»: must be one of this business's own providers
        $providerId = ! empty($data['provider_id']) ? (int) $data['provider_id'] : null;

        if ($providerId !== null && ! \App\Support\Providers::isProviderOf($providerId, (int) $business->id)) {
            throw ValidationException::withMessages(['provider_id' => __('هذا الشخص ليس من مقدّمي الخدمة في هذا النشاط.')]);
        }

        $dayUse = ! empty($data['day_use']);

        if ($dayUse) {
            $data = $this->applyDayUse($data);
        }

        // «اكتمل العدد اليوم»: the business closed this date for new bookings (its own walk-in is its call)
        if ($guest === null) {
            \App\Support\DayClosure::assertOpen($business, $data['starts_at'] ?? $data['date'] ?? null, 'starts_at');
        }

        // Mutual conflict guard (before any pricing work): a timed booking can't
        // overlap anything already on the customer's agenda — another booking, a
        // clinic appointment, or a personal task.
        if (! empty($data['starts_at']) && ($guest === null || $guest['customer'] !== null)) {
            [$start, $end] = $this->agenda->blockingWindow(
                Carbon::parse($data['starts_at']),
                ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
                (bool) ($data['all_day'] ?? false),
            );
            $this->agenda->assertFree($customerId, $start, $end, null, 'starts_at');
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
            offeringId: $offering instanceof \App\Models\BusinessServicePrice ? (int) $offering->id : null,
            optionIds: $data['option_ids'] ?? [],
            // النافذةُ تُسعَّر ليلةً ليلة. وهى أيضًا التى تحدّد عددَ الليالى،
            // فلا يُضرب `quantity` فيها مرّةً ثانية.
            until: $data['ends_at'] ?? null,
            // وعددُ النزلاء، لأن «إفطار لكل فرد» يُضرب فيه.
            partySize: max((int) ($data['party_size'] ?? 1), 1),
            dayUse: $dayUse
        );

        $bookable = $calc['bookable'] ?? null;

        // A room already taken for these nights must not be sold twice.
        $this->serviceExecutionEngine->assertBookableAvailable(
            $bookable,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            max((int) ($data['quantity'] ?? 1), 1),
            ($calc['allocation'] ?? null) ? (int) $calc['allocation']->partner_business_id : null
        );
        app(\App\Services\BookingRoomService::class)->assertFeaturesAvailable($bookable, $data['starts_at'] ?? null, $data['ends_at'] ?? null, max((int) ($data['quantity'] ?? 1), 1), (array) ($data['option_ids'] ?? []));

        if ($bookable && ! $bookable->allowsOrigin($data['guest_origin'] ?? null)) {
            throw ValidationException::withMessages(['guest_origin' => $bookable->guest_scope === \App\Models\BookableItem::SCOPE_LOCALS
                ? __('هذه الغرفة للمصريين والمقيمين في مصر فقط.')
                : __('هذه الغرفة للأجانب فقط.')]);
        }

        $data = $this->applyKindGranularity($data, $business, $bookable);

        if ($dayUse) {
            // a window of hours inside one day, not a night — whatever the stay kind would have said
            $data['duration_unit'] = 'hour';
            $data['duration_value'] = app(\App\Services\BookingDayUseService::class)
                ->hours(Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']));
            $data['all_day'] = false;
            $data['meta'] = array_merge($data['meta'] ?? [], ['day_use' => true]);
        }

        $payload = [
            'user_id' => $customerId,
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
            'timezone' => $data['timezone'] ?? app(\App\Services\BusinessHoursService::class)->timezoneFor((int) $data['business_id']),
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
            // «بالدور»: the number the business gave it (only through bookings/queue)
            'queue_turn' => $request->attributes->get('queue_turn'),
        ];

        if ($bookable instanceof BookableItem) {
            $payload['bookable_type'] = $bookable->getMorphClass();
            $payload['bookable_id'] = (int) $bookable->id;
        }

        $payload['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: array_merge($data['meta'] ?? [], [
                'source' => 'api_v2',
                'created_by_user_id' => (int) $user->id,
                // what the customer ticked: a reschedule prices the new window with the same choices
                'requested_option_ids' => array_values(array_map('intval', $data['option_ids'] ?? [])),
                // what the guest declared for a scoped room; the hotel asks for the papers on arrival
                'guest_origin' => ($bookable && $bookable->guest_scope !== \App\Models\BookableItem::SCOPE_ALL) ? ($data['guest_origin'] ?? null) : null,
            ]),
            calc: $calc,
            bookable: $bookable
        );

        $allocation = $calc['allocation'] ?? null;

        $courseGroupId = ! empty($data['course_group_id']) ? (int) $data['course_group_id'] : null;

        $booking = DB::transaction(function () use ($payload, $allocation, $quantity, $courseGroupId, $data, $providerId, $user, $calc, $bookable) {
            // A room is judged AGAIN under a lock on it, as the first thing in the transaction: the check above ran before anyone had
            // finished, and two bookings for the last room can both pass it. Whoever takes the lock second reads what the first one
            // wrote, and is refused. The lock must come before any other read here — a read taken earlier would not see the first
            // booking at all.
            if ($bookable && ! empty($payload['bookable_id']) && ! empty($payload['starts_at']) && ! empty($payload['ends_at'])) {
                \App\Models\BookableItem::query()->whereKey((int) $payload['bookable_id'])->lockForUpdate()->first(['id']);

                $this->serviceExecutionEngine->assertBookableAvailable(
                    $bookable,
                    $payload['starts_at'],
                    $payload['ends_at'],
                    $quantity,
                    $allocation ? (int) $allocation->partner_business_id : null
                );
                app(\App\Services\BookingRoomService::class)->assertFeaturesAvailable($bookable, $payload['starts_at'], $payload['ends_at'], $quantity, (array) ($data['option_ids'] ?? []));
            }

            if ($courseGroupId !== null) {
                // the seat is taken under a lock on the group, so the last one is never sold twice
                $group = \App\Models\CourseGroup::query()->whereKey($courseGroupId)->lockForUpdate()->first();

                if (! $group || ! $group->is_active || (int) $group->business_id !== (int) $data['business_id']
                    || (int) $group->offering_id !== (int) ($payload['offering_id'] ?? 0)) {
                    throw ValidationException::withMessages(['course_group_id' => __('هذه المجموعة غير متاحة.')]);
                }

                if ($group->seatsLeft() < 1) {
                    throw ValidationException::withMessages(['course_group_id' => __('اكتملت مقاعد هذه المجموعة.')]);
                }

                $payload['course_group_id'] = (int) $group->id;
            }

            // a business with chairs gives each appointment a chair that is free for it — the one asked for, or the first free
            $chairs = \App\Services\AppointmentQueue::chairsOf((int) $data['business_id']);
            if ($chairs->isNotEmpty() && empty($payload['bookable_id']) && ! empty($payload['starts_at'])) {
                User::query()->whereKey((int) $data['business_id'])->lockForUpdate()->first(['id']);

                $asked = ! empty($data['chair_id']) ? (int) $data['chair_id'] : null;
                $candidates = $asked !== null ? $chairs->where('id', $asked) : $chairs;

                if ($asked !== null && $candidates->isEmpty()) {
                    throw ValidationException::withMessages(['chair_id' => __('هذا الكرسي غير متاح.')]);
                }

                $start = Carbon::parse($payload['starts_at']);
                $end = ! empty($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : $start->copy()->addMinutes(30);

                $free = $candidates->first(function ($chair) use ($data, $start, $end) {
                    return ! Booking::query()
                        ->where('business_id', (int) $data['business_id'])->where('chair_id', $chair->id)
                        ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
                        ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')])
                        ->exists();
                });

                if (! $free) {
                    throw ValidationException::withMessages([
                        'chair_id' => $asked !== null ? __('هذا الكرسي محجوز في هذا الوقت.') : __('لا يوجد كرسي متاح في هذا الوقت.'),
                    ]);
                }

                $payload['chair_id'] = (int) $free->id;
            }

            // A person was chosen: he has one customer at a time (judged under the lock on the business) — and because
            // several people can work at once, this replaces the «business is one line» rule below for this booking.
            if ($providerId !== null && ! empty($payload['starts_at'])) {
                User::query()->whereKey((int) $data['business_id'])->lockForUpdate()->first(['id']);

                $start = Carbon::parse($payload['starts_at']);
                $end = ! empty($payload['ends_at'])
                    ? Carbon::parse($payload['ends_at'])
                    : $start->copy()->addMinutes(\App\Services\AppointmentQueue::serviceMinutes((int) $data['business_id'], (int) ($payload['offering_id'] ?? 0)));

                $busy = Booking::query()
                    ->where('business_id', (int) $data['business_id'])->assignedTo($providerId)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')])
                    ->exists();

                if ($busy) {
                    throw ValidationException::withMessages(['provider_id' => __('هذا المقدّم محجوز في هذا الوقت.')]);
                }
            }

            // An appointment with the business itself that has no chairs and no unit is ONE line: a time can be held
            // once. The grid already hides a held time; this is what stops two customers (or a direct API call) taking
            // it in the same moment — judged under the same lock on the business as the chairs above.
            if ($chairs->isEmpty()
                && $providerId === null
                && empty($payload['bookable_id'])
                && ! empty($payload['starts_at'])
                && $courseGroupId === null
                && optional(app(\App\Services\BookingShapes::class)->forBusiness((int) $data['business_id']))->code === 'appointment') {
                User::query()->whereKey((int) $data['business_id'])->lockForUpdate()->first(['id']);

                $start = Carbon::parse($payload['starts_at']);
                $end = ! empty($payload['ends_at'])
                    ? Carbon::parse($payload['ends_at'])
                    : $start->copy()->addMinutes(\App\Services\AppointmentQueue::serviceMinutes((int) $data['business_id'], (int) ($payload['offering_id'] ?? 0)));

                $held = Booking::query()
                    ->where('business_id', (int) $data['business_id'])
                    ->whereNull('bookable_id')->whereNull('chair_id')
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')])
                    ->exists();

                if ($held) {
                    throw ValidationException::withMessages(['starts_at' => __('هذا الموعد محجوز. اختر وقتًا آخر.')]);
                }
            }

            $booking = Booking::query()->create($payload);

            // the hold this guest had on the room has become the booking
            if (! empty($payload['bookable_id'])) {
                app(\App\Services\BookingHolds::class)->consume((int) $user->id, (int) $payload['bookable_id']);
            }

            // the customer's choice becomes the assignment — he is told, like any hand-over
            if ($providerId !== null) {
                app(\App\Services\Assignments\AssignmentService::class)->assign($booking, $providerId, (int) $user->id);
            }

            if ($allocation) {
                // مقفولٌ داخل هذه المعاملة نفسها — لا يفوز طلبان بآخر وحدة معًا.
                $this->allocationConsumption->reserve(
                    $allocation,
                    $quantity,
                    ! empty($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : null,
                    ! empty($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : null
                );
                $this->allocationConsumption->tagBooking($booking, $allocation, $quantity);
            }

            return $booking;
        });

        // a hotel's offer that priced this booking has been used once more
        app(\App\Services\Promotions\BookingPromotionPricing::class)->commitUsage((array) data_get($calc, 'price_breakdown', []));

        if ($guest !== null) {
            return $this->finishGuestBooking($request, $booking, $user, $guest);
        }

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
                'booking' => $this->titled($booking),
            ],
        ], 201);
    }

    /**
     * POST /api/v2/bookings/queue — «بالدور»: the same booking, but the customer names the DAY and the business gives
     * the turn and the time. The number is taken under a lock on the business, so two customers never get the same one.
     */
    public function queue(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
            'offering_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'chair_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $businessId = (int) $data['business_id'];

        if (\App\Services\AppointmentQueue::flowOf($businessId) !== \App\Services\AppointmentQueue::FLOW_QUEUE) {
            return $this->error(__('هذا النشاط يحجز بالموعد لا بالدور.'), 422);
        }

        $day = Carbon::createFromFormat('Y-m-d', $data['date'], app(\App\Services\BusinessHoursService::class)->timezoneFor($businessId))->startOfDay();
        $today = Carbon::now($day->getTimezone())->startOfDay();

        if ($day->lt($today) || $day->gt($today->copy()->addDays(30))) {
            return $this->error(__('اختر يومًا من الأيام القادمة.'), 422);
        }

        $minutes = \App\Services\AppointmentQueue::serviceMinutes($businessId, (int) $data['offering_id']);

        $chairId = ! empty($data['chair_id']) ? (int) $data['chair_id'] : null;

        return DB::transaction(function () use ($request, $businessId, $day, $minutes, $chairId) {
            // one customer at a time takes a number of this business's day
            User::query()->whereKey($businessId)->lockForUpdate()->first(['id']);

            $next = app(\App\Services\AppointmentQueue::class)->next($businessId, $day, $minutes, $chairId);

            if ($next['full']) {
                return $this->error($next['closed'] ? __('النشاط مغلق في هذا اليوم.') : __('اكتمل دور هذا اليوم.'), 422);
            }

            $request->merge([
                'starts_at' => $next['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $next['ends_at']->format('Y-m-d H:i:s'),
                'all_day' => false,
                'offering_type' => $request->input('offering_type', 'service_price'),
                'chair_id' => $next['chair_id'],
            ]);
            $request->attributes->set('queue_turn', (int) $next['turn']);

            return $this->store($request);
        });
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        $booking->load($this->relations(true));

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => $this->titled($booking),
                'financial_preview' => $this->partyPreview(request(), $booking),
                // «قبول الديبوزت كدفعة»: may it be asked, was it, was it accepted
                'deposit_as_payment' => app(\App\Services\BookingDepositPaymentService::class)->state($booking),
            ],
        ]);
    }

    public function accept(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        $response = $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_ACCEPTED,
            dispatcher: 'bookingAccepted',
            source: 'api_v2.business.accept',
            message: 'Booking accepted successfully.'
        );

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'accepted');

        return $response;
    }

    public function reject(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        if ($this->isWalkIn($booking)) {
            return $this->walkInTransition($booking, Booking::STATUS_REJECTED, 'Booking rejected successfully.');
        }

        $response = $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_REJECTED,
            dispatcher: 'bookingRejected',
            source: 'api_v2.business.reject',
            message: 'Booking rejected successfully.'
        );

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'rejected');

        return $response;
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        if ($this->isWalkIn($booking)) {
            return $this->walkInTransition($booking, Booking::STATUS_CANCELLED, 'Booking cancelled successfully.');
        }

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

        // Confirming is the client's own commitment to let the wallet be used
        // as this booking's deposit — start() (the business's action) is what
        // actually freezes it, so the PIN belongs here, at the client's own
        // step, not there.
        $walletHoldNeeded = round((float) data_get(
            $this->safeFinancialPreview($booking),
            'deposit.client_wallet_required',
            0
        ), 2);

        if ($walletHoldNeeded > 0) {
            $this->wallet->assertPinValid((int) $request->user()->id, $request->input('pin'));
        }

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

        // attendance is by BOTH, the customer first — it is what gives the booking its seriousness
        if (! $this->isWalkIn($booking) && ! $this->attendanceOf($booking)['client']) {
            throw ValidationException::withMessages(['status' => __('بانتظار تأكيد العميل أولًا.')]);
        }

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

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'confirmed');

        return $this->bookingResponse($booking, 'Business confirmation saved successfully.');
    }

    public function start(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        if ($this->isWalkIn($booking)) {
            $state = $this->attendanceOf($booking);

            if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true) || ! $state['client'] || ! $state['business']) {
                throw ValidationException::withMessages(['status' => __('يجب تأكيد الطرفين قبل بدء التنفيذ.')]);
            }

            $booking->forceFill(['status' => Booking::STATUS_IN_PROGRESS])->save();

            return $this->bookingResponse($booking->refresh(), 'Booking execution started successfully.');
        }

        $this->serviceExecutionEngine->moveBookingToInProgress($booking);

        $booking->refresh()->load($this->relations(true));

        $this->serviceEventDispatcher->bookingStarted(
            booking: $booking,
            actorId: (int) $request->user()->id,
            payload: $this->eventPayload($booking, 'api_v2.business.start')
        );

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'started');

        return $this->bookingResponse($booking, 'Booking execution started successfully.');
    }

    public function complete(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        // a guest with no account is never counted: no guarantee credit, no ledger sale — it is simply done
        if ($this->isWalkIn($booking)) {
            return $this->walkInTransition($booking, Booking::STATUS_COMPLETED, 'Booking completed successfully.');
        }

        $response = $this->changeStatus(
            request: $request,
            booking: $booking,
            status: Booking::STATUS_COMPLETED,
            dispatcher: 'bookingCompleted',
            source: 'api_v2.business.complete',
            message: 'Booking completed successfully.'
        );

        $booking->refresh();
        $this->bookingGuaranteeIntegration->recordCompleted($booking);
        $this->bookingReminderService->cancelForBooking($booking);
        $this->ledger->recordSale((int) $booking->business_id, \App\Models\BusinessFinancialLedger::SOURCE_BOOKING, $booking->finalPriceAmount(), 0.0);

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'completed');

        return $response;
    }

    /**
     * Either party (client or business) agreeing the booking's deposit should
     * be RELEASED — i.e. the deal succeeded and money was settled as usual
     * off-app. Once both sides agree, the deposit releases automatically.
     * Self-service counterpart to AdminV2\BookingController's manual
     * Release Deposit action, which stays as an admin fallback only.
     */
    public function agreeReleaseDeposit(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        $this->bookingDepositService->agreeRelease($booking, (int) $request->user()->id);

        return $this->bookingResponse($booking->fresh(), 'Release agreement recorded successfully.');
    }

    /**
     * POST /api/v2/bookings/{booking}/deposit/request-as-payment — the customer asks that the frozen deposit be
     * taken as a payment instead of a transfer outside the platform (only where the business opted in).
     */
    public function requestDepositAsPayment(Request $request, Booking $booking)
    {
        $this->authorizeClientBooking($request, $booking);

        app(\App\Services\BookingDepositPaymentService::class)->request($booking, (int) $request->user()->id);

        return $this->bookingResponse($booking->fresh(), 'Request recorded successfully.');
    }

    /** POST /api/v2/bookings/{booking}/deposit/accept-as-payment — the business takes the customer's frozen deposit as a payment. */
    public function acceptDepositAsPayment(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        app(\App\Services\BookingDepositPaymentService::class)->accept($booking, (int) $booking->business_id);

        return $this->bookingResponse($booking->fresh(), 'Deposit taken as a payment.');
    }

    /**
     * POST /api/v2/bookings/{booking}/reschedule — {starts_at, ends_at?}
     * «تغيير موعد الحجز»: the customer moves his own booking, while it is still `pending` or `accepted`, to a new start
     * (the same length unless ends_at is sent). The new window is judged exactly as a new booking is — his agenda, the
     * business's hours, the unit / chair / held time — and priced again with the choices he made. An accepted booking
     * goes back to `pending`: the business answers the new time.
     */
    public function reschedule(Request $request, Booking $booking)
    {
        $this->authorizeClientBooking($request, $booking);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        if (! in_array((string) $booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تغيير موعد هذا الحجز الآن.')]);
        }

        if (! $booking->starts_at) {
            throw ValidationException::withMessages(['starts_at' => __('هذا الحجز بلا موعد محدد لتغييره.')]);
        }

        $oldStart = Carbon::parse($booking->starts_at);
        $oldEnd = $booking->ends_at ? Carbon::parse($booking->ends_at) : null;
        $start = Carbon::parse($data['starts_at']);
        $end = ! empty($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : ($oldEnd ? $start->copy()->addSeconds($oldStart->diffInSeconds($oldEnd)) : null);

        if ($start->lte(now())) {
            throw ValidationException::withMessages(['starts_at' => __('اختر وقتًا قادمًا.')]);
        }

        $wasAccepted = (string) $booking->status === Booking::STATUS_ACCEPTED;

        DB::transaction(function () use ($booking, $start, $end, $wasAccepted) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            // judged as a NEW booking: this one must not stand in its own way
            $booking->forceFill(['status' => Booking::STATUS_CANCELLED])->saveQuietly();

            $window = $end ?? $start->copy()->addMinutes(30);

            [$from, $to] = $this->agenda->blockingWindow($start, $end, (bool) $booking->all_day);
            $this->agenda->assertFree((int) $booking->user_id, $from, $to, [$booking->getMorphClass(), $booking->getKey()], 'starts_at');

            if (! app(\App\Services\BusinessHoursService::class)->isOpenThroughout((int) $booking->business_id, $start, $window)) {
                throw ValidationException::withMessages(['starts_at' => __('هذا الوقت خارج مواعيد عمل النشاط.')]);
            }

            $bookable = $booking->bookable_id ? BookableItem::query()->find((int) $booking->bookable_id) : null;
            $this->serviceExecutionEngine->assertBookableAvailable($bookable, $start, $end);
            $this->assertTimeHeld($booking, $start, $window);

            $update = [
                'date' => $start->toDateString(),
                'time' => $booking->all_day ? '00:00:00' : $start->format('H:i:s'),
                'starts_at' => $start,
                'ends_at' => $end,
                // an accepted booking goes back to the business, which answers the new time
                'status' => Booking::STATUS_PENDING,
            ];

            // the price of the new window, with the choices the customer made when he booked
            $meta = (array) ($booking->meta ?? []);

            if (array_key_exists('requested_option_ids', $meta) && (int) $booking->service_id > 0) {
                $offering = ($booking->offering_type && $booking->offering_id)
                    ? $booking->offering_type::query()->find((int) $booking->offering_id)
                    : null;

                $calc = $this->serviceExecutionEngine->prepare(
                    businessId: (int) $booking->business_id,
                    serviceId: (int) $booking->service_id,
                    bookableId: $booking->bookable_id ? (int) $booking->bookable_id : null,
                    quantity: max((int) $booking->quantity, 1),
                    pricingDate: $start,
                    offeringId: $offering instanceof \App\Models\BusinessServicePrice ? (int) $offering->id : null,
                    optionIds: (array) $meta['requested_option_ids'],
                    until: $end,
                    partySize: max((int) ($booking->party_size ?: 1), 1),
                    dayUse: (bool) ($meta['day_use'] ?? false),
                );

                $update['price'] = (float) data_get($calc, 'price_breakdown.final_price', $booking->price);
                $update['meta'] = $this->serviceExecutionEngine->buildBookingMeta(existingMeta: $meta, calc: $calc, bookable: $calc['bookable'] ?? $bookable);
            }

            $booking->forceFill($update)->save();
        });

        $booking = $booking->fresh();
        $this->bookingReminderService->scheduleForBooking($booking);

        try {
            app(\App\Services\Notifications\NotificationDispatcherService::class)->dispatch('booking.rescheduled', (int) $booking->business_id, [
                'title_ar' => 'غيّر العميل موعد حجزه', 'title_en' => 'A customer moved a booking',
                'body_ar' => 'غيّر العميل موعد حجزه إلى ' . $start->format('Y-m-d H:i') . ($wasAccepted ? ' — بانتظار ردّك.' : '.'),
                'body_en' => 'A customer moved a booking to ' . $start->format('Y-m-d H:i') . ($wasAccepted ? ' — awaiting your answer.' : '.'),
                'notifiable_type' => Booking::class, 'notifiable_id' => (int) $booking->id,
                'source_type' => Booking::class, 'source_id' => (int) $booking->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->bookingResponse($booking, 'Booking rescheduled.');
    }

    /**
     * The same «a time is held once» rules store() applies to a booking with no unit: a chair has one customer at a time,
     * and a business of the appointment shape with no chairs and no unit is ONE line. [$booking] is already out of the
     * way (judged as if cancelled).
     */
    private function assertTimeHeld(Booking $booking, Carbon $start, Carbon $end): void
    {
        if ($booking->bookable_id || $booking->course_group_id) {
            return;
        }

        $businessId = (int) $booking->business_id;

        // a chair has one customer at a time; with no chairs, an appointment-shape business with no unit is ONE line
        $chaired = $booking->chair_id !== null;
        $oneLine = ! $chaired
            && \App\Services\AppointmentQueue::chairsOf($businessId)->isEmpty()
            && optional(app(\App\Services\BookingShapes::class)->forBusiness($businessId))->code === 'appointment';

        if (! $chaired && ! $oneLine) {
            return;
        }

        $held = Booking::query()
            ->where('business_id', $businessId)
            ->where('id', '!=', (int) $booking->id)
            ->whereNull('bookable_id')
            ->when($chaired, fn ($q) => $q->where('chair_id', (int) $booking->chair_id), fn ($q) => $q->whereNull('chair_id'))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')])
            ->exists();

        if ($held) {
            throw ValidationException::withMessages(['starts_at' => $chaired
                ? __('هذا الكرسي محجوز في هذا الوقت.')
                : __('هذا الموعد محجوز. اختر وقتًا آخر.')]);
        }
    }

    /**
     * POST /api/v2/business/bookings/{booking}/deposit/confirm-received — {method: cash|transfer, reference?}
     * «استلمت العربون»: the deposit taken outside the platform arrived; the customer's account works again.
     */
    public function confirmExternalDeposit(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);
        $data = $request->validate([
            'method' => ['required', 'in:cash,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        app(\App\Services\ExternalDepositService::class)->confirmReceived($booking, (int) $booking->business_id, $data['method'], $data['reference'] ?? null);

        return $this->bookingResponse($booking->fresh(), 'Deposit received.');
    }

    /** POST /api/v2/business/bookings/{booking}/deposit/waive — «قبول العربون»: accepted with no deposit paid outside. */
    public function waiveExternalDeposit(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        app(\App\Services\ExternalDepositService::class)->waive($booking, (int) $booking->business_id);

        return $this->bookingResponse($booking->fresh(), 'Booking accepted without a deposit.');
    }

    /** POST /api/v2/bookings/{booking}/deposit/decline-as-payment — the business declines; the deposit stays frozen. */
    public function declineDepositAsPayment(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        app(\App\Services\BookingDepositPaymentService::class)->decline($booking, (int) $booking->business_id);

        return $this->bookingResponse($booking->fresh(), 'Request declined.');
    }

    /**
     * Same as agreeReleaseDeposit(), for the opposite outcome: both parties
     * agreeing the deal did not go through, so the deposit should be
     * REFUNDED to the client instead.
     */
    public function agreeRefundDeposit(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        $this->bookingDepositService->agreeRefund($booking, (int) $request->user()->id);

        return $this->bookingResponse($booking->fresh(), 'Refund agreement recorded successfully.');
    }

    /**
     * POST /api/v2/bookings/{booking}/confirm-payment - the client says they
     * paid in cash. Also counts as their agreement to release the frozen
     * deposit (see recordCashConfirmation).
     */
    public function confirmPayment(Request $request, Booking $booking)
    {
        $this->authorizeClientBooking($request, $booking);

        return $this->recordCashConfirmation($booking, 'client', (int) $booking->user_id);
    }

    /** POST /api/v2/business/bookings/{booking}/confirm-payment - the business says it received the cash. */
    public function businessConfirmPayment(Request $request, Booking $booking)
    {
        $this->authorizeBusinessBooking($request, $booking);

        return $this->recordCashConfirmation($booking, 'business', (int) $booking->business_id);
    }

    /**
     * One party's cash confirmation. Allowed once the booking is under way
     * (the same point deposit agreements open). It stamps that side's
     * timestamp, and - when a deposit is frozen and no dispute is open -
     * records the same party's release agreement, so the deposit releases as
     * soon as both sides have confirmed. Once both confirmed the booking is
     * marked settled.
     */
    private function recordCashConfirmation(Booking $booking, string $side, int $partyUserId)
    {
        if (! in_array((string) $booking->status, [Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'payment' => __('لا يمكن تأكيد الدفع إلا بعد بدء التنفيذ.'),
            ]);
        }

        $column = $side . '_payment_confirmed_at';
        if ($booking->{$column}) {
            abort(409, __('سبق تأكيد الدفع.'));
        }

        $booking->{$column} = now();
        $booking->save();

        $deposit = $this->bookingDepositService->latestDeposit($booking);
        if ($deposit && $deposit->isFrozen() && ! $this->bookingDepositService->hasLiveDispute($deposit)) {
            $this->bookingDepositService->agreeRelease($booking, $partyUserId);
        }

        $booking = $booking->fresh();
        if ($booking->client_payment_confirmed_at && $booking->business_payment_confirmed_at && ! $booking->payment_settled_at) {
            $booking->payment_settled_at = now();
            $booking->save();
        }

        return $this->bookingResponse($booking->fresh(), 'Payment confirmation recorded successfully.');
    }

    public function financialPreview(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        return response()->json([
            'success' => true,
            'data' => $this->partyPreview($request, $booking),
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
            $this->allocationConsumption->onStatusChanged($booking, $status);

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

    /** The business booked it: accepted at once. A guest with an account is told; a nameless-account guest is not (it is the business itself). */
    private function finishGuestBooking(Request $request, Booking $booking, User $actor, array $guest)
    {
        $standIn = $guest['customer'] === null;

        DB::transaction(function () use ($booking, $actor, $guest, $standIn) {
            $booking->refresh();
            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['source'] = $standIn ? 'walk_in' : 'business';
            $meta['created_by_business'] = (int) $actor->id;

            if ($standIn) {
                $meta['walk_in'] = $guest['walk_in'];
                // he is standing there: his side of the attendance is the fact itself
                $meta['confirmations']['client'] = [
                    'confirmed' => true,
                    'confirmed_at' => now()->toDateTimeString(),
                    'confirmed_by' => (int) $actor->id,
                    'source' => 'walk_in',
                ];
            }

            $booking->update(['meta' => $meta, 'status' => Booking::STATUS_ACCEPTED]);
            $this->allocationConsumption->onStatusChanged($booking, Booking::STATUS_ACCEPTED);
        });

        $booking->refresh();

        if (! $standIn) {
            $this->serviceEventDispatcher->bookingAccepted(
                booking: $booking,
                actorId: (int) $actor->id,
                payload: array_merge($this->eventPayload($booking, 'api_v2.business.guest'), ['old_status' => 'pending', 'new_status' => Booking::STATUS_ACCEPTED])
            );
            $this->bookingReminderService->scheduleForBooking($booking);
        }

        $this->activity->log($request, \App\Support\BusinessCapability::BOOKINGS, $booking, 'guest_booked');

        $booking->load($this->relations());

        return response()->json([
            'success' => true,
            'message' => $standIn ? __('أُضيف الضيف وحُجز له.') : __('حُجز للعميل على حسابه.'),
            'data' => ['booking' => $this->titled($booking)],
        ], 201);
    }

    private function isWalkIn(Booking $booking): bool
    {
        return (bool) data_get($booking->meta, 'walk_in');
    }

    /** @return array{client: bool, business: bool} */
    private function attendanceOf(Booking $booking): array
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $legacy = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        return [
            'client' => ! empty($meta['confirmations']['client']['confirmed']) || ! empty($legacy['client']),
            'business' => ! empty($meta['confirmations']['business']['confirmed']) || ! empty($legacy['business']),
        ];
    }

    /** A walk-in's cancel / reject / finish: only the booking moves — no event, no notice to «the customer» (the business itself). */
    private function walkInTransition(Booking $booking, string $status, string $message)
    {
        if ($booking->isFinalStatus()) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تعديل حجز في حالة نهائية.')]);
        }

        DB::transaction(function () use ($booking, $status) {
            $booking->refresh();
            $booking->update(['status' => $status]);
            $this->allocationConsumption->onStatusChanged($booking, $status);
        });

        $this->bookingReminderService->cancelForBooking($booking->refresh());

        return $this->bookingResponse($booking, $message);
    }

    private function bookingResponse(Booking $booking, string $message)
    {
        $booking->load($this->relations(true));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'booking' => $this->titled($booking),
                'financial_preview' => $this->partyPreview(request(), $booking),
                'deposit_as_payment' => app(\App\Services\BookingDepositPaymentService::class)->state($booking),
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

    /**
     * A staff member acting for the business passes business.member:bookings
     * before this ever runs, so BusinessContext already resolved who they
     * act for — this only has to confirm it's THIS booking's business, not
     * a colleague's. The owner path is unchanged (BusinessContext falls back
     * to the authed user when no delegation middleware ran).
     */
    private function authorizeBusinessBooking(Request $request, Booking $booking): void
    {
        if ((int) $booking->business_id !== \App\Support\BusinessContext::id($request)) {
            abort(403, 'Booking does not belong to this business account.');
        }

        // a provider works from his own list: a colleague's booking is not his to confirm, start or close
        if (($mine = \App\Support\Providers::actingId($request)) !== null
            && app(\App\Services\Assignments\AssignmentService::class)->assigneeOf($booking) !== $mine
            && \App\Models\ServiceChair::restrictedTo($request) === null) {
            abort(403, __('هذا الحجز ليس من أعمالك.'));
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

    /**
     * Give each booking its `title` — «كشف — عظام», not just «حجز» — the way
     * Booking::title() names it. The relations it reads are already loaded by
     * relations(), so this costs no extra queries.
     *
     * @param  \App\Models\Booking|\Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\Paginator  $subject
     */
    private function titled($subject)
    {
        if ($subject instanceof Booking) {
            $notices = \App\Services\ArrivalNotice::forBusinesses([(int) $subject->business_id]);
            $subject->setAttribute('arrival_notice', $notices[(int) $subject->business_id] ?? null);
            // «متابعة التنفيذ» is offered only when the business actually linked a timeline to this booking
            $subject->setAttribute('has_project', \App\Models\Project::query()
                ->where('operation_type', (new Booking())->getMorphClass())->where('operation_id', (int) $subject->id)->exists());
            $subject->setAttribute('queue', app(\App\Services\AppointmentQueue::class)->estimate($subject));
            $subject->setAttribute('chair', $subject->chair_id ? optional(\App\Models\ServiceChair::query()->with('staff:id,name,logo')->find($subject->chair_id))->payload() : null);
            $subject->setAttribute('assignee', $this->assigneesFor((int) $subject->business_id, [$subject])[(int) $subject->id] ?? null);
            // the deposit taken outside the platform: owed, and whether the business has said it arrived
            $subject->setAttribute('external_deposit', app(\App\Services\ExternalDepositService::class)->state($subject));
            $this->decorateParties($subject, \App\Support\ClientHistory::counts((int) $subject->business_id, [(int) $subject->user_id]));
            // the full account of what the two have been through (the one booking in hand left out): for the detail only
            $subject->setAttribute('relationship', $subject->client_history === null
                ? null
                : \App\Support\ClientHistory::relationship((int) $subject->business_id, (int) $subject->user_id, (int) $subject->id));

            return $this->hideRoomInternals($subject->append('title'));
        }

        $items = $subject instanceof \Illuminate\Contracts\Pagination\Paginator ? $subject->getCollection() : $subject;
        $notices = \App\Services\ArrivalNotice::forBusinesses($items->pluck('business_id')->all());
        $chairsById = \App\Models\ServiceChair::query()->with('staff:id,name,logo')->whereIn('id', $items->pluck('chair_id')->filter()->unique()->all())->get()->keyBy('id');
        $withProject = \App\Models\Project::query()
            ->where('operation_type', (new Booking())->getMorphClass())->whereIn('operation_id', $items->pluck('id')->all())
            ->pluck('operation_id')->map(fn ($id) => (int) $id)->flip();
        $assignees = [];
        foreach ($items->groupBy('business_id') as $businessId => $group) {
            $assignees += $this->assigneesFor((int) $businessId, $group->all());
        }
        $history = [];
        foreach ($items->groupBy('business_id') as $businessId => $group) {
            $history[(int) $businessId] = \App\Support\ClientHistory::counts((int) $businessId, $group->pluck('user_id')->all());
        }

        $items->each(function (Booking $booking) use ($notices, $chairsById, $withProject, $assignees, $history) {
            $this->decorateParties($booking, $history[(int) $booking->business_id] ?? []);
            $booking->setAttribute('assignee', $assignees[(int) $booking->id] ?? null);
            $booking->setAttribute('arrival_notice', $notices[(int) $booking->business_id] ?? null);
            $booking->setAttribute('has_project', $withProject->has((int) $booking->id));
            $booking->setAttribute('chair', $booking->chair_id ? optional($chairsById->get($booking->chair_id))->payload() : null);
            $booking->setAttribute('queue', app(\App\Services\AppointmentQueue::class)->estimate($booking));
            $this->hideRoomInternals($booking->append('title'));
        });

        return $subject;
    }

    /**
     * The booking's parties as the app needs them: the walk-in guest's own name (the business's account only stands in
     * for him), who has confirmed attendance, and — for the BUSINESS's own view only — the customer's earlier dealings.
     *
     * @param  array<int,int>  $counts  client id → completed dealings with this business
     */
    private function decorateParties(Booking $booking, array $counts): void
    {
        $walkIn = data_get($booking->meta, 'walk_in');

        $booking->setAttribute('guest', $walkIn ? ['name' => (string) ($walkIn['name'] ?? ''), 'phone' => $walkIn['phone'] ?? null] : null);
        $booking->setAttribute('attendance', $this->attendanceOf($booking));

        // told to BOTH parties of the booking — the business about its customer, the customer about the business
        $viewerId = (int) (request()->user()?->id ?? 0);
        $isParty = \App\Support\BusinessContext::id(request()) === (int) $booking->business_id || $viewerId === (int) $booking->user_id;
        $previous = ! $walkIn && $isParty
            ? max(0, (int) ($counts[(int) $booking->user_id] ?? 0) - ($booking->status === Booking::STATUS_COMPLETED ? 1 : 0))
            : null;

        $booking->setAttribute('client_history', $previous === null ? null : ['previous_dealings' => $previous]);
    }

    /**
     * «نظام التعيين»: who is responsible for each booking — name + his title at that business — in two queries for the
     * whole page. @param  array<int,Booking>  $bookings  of ONE business  @return array<int,?array> booking id → person
     */
    private function assigneesFor(int $businessId, array $bookings): array
    {
        $map = app(\App\Services\Assignments\AssignmentService::class)
            ->assigneesOf((new Booking())->getMorphClass(), array_map(fn ($b) => (int) $b->id, $bookings));

        if ($map === []) {
            return [];
        }

        $parties = \App\Support\Providers::parties($businessId, array_values($map));

        return array_map(fn (int $userId) => $parties[$userId] ?? null, $map);
    }

    /**
     * «العدد والرقم لكل غرفة لدى الفندق فقط ويظهر عند بداية التنفيذ»: the hotel sees its room numbers; the customer
     * sees a number only once the stay has started, and never the unit's internal note or (on a room type with listed
     * rooms) its code.
     */
    /**
     * «Day use»: turns the guest's `date` into the window the room type named. Needs the room type (`bookable_id`) —
     * the window and the price are its, never the guest's.
     */
    private function applyDayUse(array $data): array
    {
        $date = $data['date'] ?? (! empty($data['starts_at']) ? Carbon::parse($data['starts_at'])->toDateString() : null);

        if (empty($data['bookable_id']) || ! $date) {
            throw ValidationException::withMessages(['date' => __('اختر الغرفة وتاريخ Day use.')]);
        }

        $unit = BookableItem::query()
            ->where('business_id', (int) $data['business_id'])
            ->where('is_active', 1)
            ->find((int) $data['bookable_id']);

        if (! $unit) {
            throw ValidationException::withMessages(['bookable_id' => __('هذه الغرفة لا تُحجز Day use.')]);
        }

        [$start, $end] = app(\App\Services\BookingDayUseService::class)->window($unit, (string) $date);

        return array_merge($data, [
            'date' => $start->toDateString(),
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end->toDateTimeString(),
            'all_day' => false,
            'day_use' => true,
        ]);
    }

    private function hideRoomInternals(Booking $booking): Booking
    {
        $viewer = request()->user();
        $businessSide = $viewer && (int) \App\Support\BusinessContext::id(request()) === (int) $booking->business_id;

        if ($businessSide) {
            return $booking;
        }

        if (! in_array((string) $booking->status, [Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED], true)) {
            $booking->unsetRelation('room');
            $booking->setAttribute('room_id', null);
        }

        $unit = $booking->relationLoaded('bookable') ? $booking->bookable : null;

        if ($unit instanceof \App\Models\BookableItem) {
            $unit->makeHidden('notes');

            if ($unit->item_type === 'booking_stay' && $unit->rooms()->exists()) {
                $unit->makeHidden('code');
            }
        }

        return $booking;
    }

    private function relations(bool $details = false): array
    {
        $relations = [
            // Both parties get only what they need to fulfil a booking they
            // are actually a party to (name, phone, location, photo) — never
            // email on either side. This is the one place that contact data
            // is exposed at all; nowhere in the API can either party browse
            // the other's profile otherwise, and a business's email is not
            // otherwise public (the public business page never sends it).
            'user:id,name,phone,image,latitude,longitude',
            'business:id,name,type,phone,logo,image,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit',
            'bookable',
            // the hotel's own room number, once the stay has one (hidden from the customer until the stay starts)
            'room',
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
            // «اختر مقدّم الخدمة»: the person the customer wants (a barber, a trainer, a technician); none = the business decides
            'provider_id' => ['nullable', 'integer', 'min:1'],
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
            // the guest's own declaration (an Egyptian or a resident / a foreigner) for a room type sold to one of them only
            'guest_origin' => ['nullable', Rule::in(['local', 'foreign'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
            // المُوصِّفات التى اختارها العميل — «شاشة كبيرة»، «بلايستيشن ٥».
            // ما لا يعرفه سطرُ السعر يسقط صامتًا عند الحساب.
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            // «Day use»: a hotel room for the day — send `date`, the room type's own window follows.
            'day_use' => ['nullable', 'boolean'],
            // «كورس»: the group joined — the booking is the enrolment, and it takes one of the group's seats.
            'course_group_id' => ['nullable', 'integer', 'min:1'],
            // «كرسي وعليه الحلاق»: the chair the customer chose; none = whichever is free.
            'chair_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The financial preview as the CALLER may see it: their own side of the
     * money only (see BookingFinancialView) — never the counterparty's wallet
     * balance or guarantee checks. Null when the preview cannot be computed.
     */
    private function partyPreview(Request $request, Booking $booking): ?array
    {
        $preview = $this->safeFinancialPreview($booking);

        if ($preview === null) {
            return null;
        }

        $side = (int) $request->user()->id === (int) $booking->business_id
            ? \App\Support\BookingFinancialView::BUSINESS
            : \App\Support\BookingFinancialView::CLIENT;

        return \App\Support\BookingFinancialView::forParty($preview, $side);
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
