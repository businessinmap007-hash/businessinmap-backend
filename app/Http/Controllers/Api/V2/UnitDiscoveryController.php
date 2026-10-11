<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\BusinessServicePriceResolver;
use App\Services\ServiceExecutionEngine;
use Illuminate\Http\Request;

/**
 * The rooms a customer may actually book, and what each costs.
 *
 * 21 category children set `requires_bookable_item` — every hotel child, the
 * restaurants, the pitches, the halls, the pools, the coworking spaces. For
 * those the engine refuses a booking without a NAMED unit, and the client must
 * send `bookable_id`. Nothing told it what those ids were: there was no
 * customer-facing endpoint over `bookable_items` at all, so the booking flow
 * for those 21 could not be completed from the app whatever the business owned.
 *
 * Grouped by KIND rather than listed flat, because that is how the price works
 * and how a customer chooses: «جناح — 1000 — 4 متاحة» and then which suite.
 * Naming the kind only became possible once the unit could carry a line option.
 *
 * Public (no auth) — browsing rooms should not require signing in.
 */
final class UnitDiscoveryController extends Controller
{
    public function __construct(
        private readonly BusinessServicePriceResolver $prices,
        private readonly BookableAvailabilityService $availability,
        private readonly ServiceExecutionEngine $engine
    ) {
    }

    /** GET /api/v2/discovery/units/{business} */
    public function show(Request $request, int $business)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $biz = User::query()->where('type', 'business')
            ->find($business, ['id', 'name', 'logo', 'category_id', 'category_child_id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        // Booking is the default because it is the service that demands a named
        // unit; any other is only honoured if the client asks for it.
        $serviceId = (int) ($data['service_id'] ?? 0)
            ?: (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $units = BookableItem::query()
            // الصورُ والمُوصِّفات محمَّلةٌ مقدَّمًا: عشرون غرفةً كانت عشرين
            // استعلامًا لكلٍّ منهما.
            ->with(['lineOption:id,name_ar,name_en', 'images', 'offeringOptions', 'service'])
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when(! empty($data['item_type']), fn ($query) => $query->where('item_type', $data['item_type']))
            ->orderBy('item_type')
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        // A window is optional: without it the answer is «what exists and what
        // it costs», with it «what is still free». Asking for a price is the
        // common first screen and must not require picking dates first.
        $window = (! empty($data['starts_at']) && ! empty($data['ends_at']))
            ? [$data['starts_at'], $data['ends_at']]
            : null;

        $groups = [];

        foreach ($units->groupBy(fn (BookableItem $unit) => (int) ($unit->line_option_id ?? 0)) as $kindId => $inKind) {
            /** @var \App\Models\BookableItem $first */
            $first = $inKind->first();
            $price = $this->prices->resolveForBookableItem($first);

            $rows = $inKind->map(fn (BookableItem $unit) => $this->unitPayload($unit, $window, $price));

            $groups[] = [
                'line_option_id' => $kindId ?: null,
                'name' => $this->kindName($first),
                'item_type' => (string) ($first->item_type ?? ''),
                'units_count' => $rows->count(),
                'available_count' => $window ? $rows->where('available', true)->count() : null,
                'price' => $price ? round((float) $price->baseUnitPrice(), 2) : null,
                'currency' => $price ? (string) ($price->currency ?: 'EGP') : null,
                // The row the price came from, so a client can send it back as
                // `offering_id` and be priced off exactly what it was shown.
                'offering_id' => $price ? (int) $price->id : null,
                // ما يُعرض على النزيل ليقرّره: «إفطار +٥٠»، «إقامة كاملة
                // +١٥٠». يُرسَل مع النوع لا مع الوحدة، لأنه سعرُ النوع.
                'choices' => $this->choicesOf($price, $inKind),
                'units' => $rows->values(),
            ];
        }

        // Priced kinds first, then by price: an unpriced kind cannot be sold and
        // is the one the business still has to finish, not the one to lead with.
        usort($groups, function (array $a, array $b) {
            return [$a['price'] === null, $a['price'] ?? 0] <=> [$b['price'] === null, $b['price'] ?? 0];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                ],
                'service_id' => $serviceId ?: null,
                'starts_at' => $window[0] ?? null,
                'ends_at' => $window[1] ?? null,
                'kinds' => $groups,
                // «أشكال الحجز»: how this trade page is drawn (sections or a flat list, what is shown, in what order).
                // null for a trade nobody has put on a shape — the client draws what it always drew.
                'shape' => app(\App\Services\BookingShapes::class)->payload(app(\App\Services\BookingShapes::class)->forBusiness((int) $biz->id)),
            ],
        ]);
    }

    /**
     * GET /api/v2/discovery/units/{business}/day-grid
     *
     * «ملاعب وقاعات — بالساعة» and «طاولة»: one day as a grid of start times, each saying how many of the business's
     * units are still free for `duration_minutes` from that time. The customer picks a day and a time and sees at once
     * which times are taken — instead of choosing a date-time and learning at booking that it was not free.
     *
     * Uses the very service the engine books with (`BookableAvailabilityService::check`: working hours, blocked
     * slots and live bookings), so a free time here cannot be refused for a reason this never saw.
     */
    public function dayGrid(Request $request, int $business)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:720'],
            'step_minutes' => ['nullable', 'integer', 'in:15,30,60'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            'service_id' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'string', 'max:100'],
        ]);

        $biz = User::query()->where('type', 'business')->find($business, ['id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        $hours = app(\App\Services\BusinessHoursService::class);
        $tz = $hours->timezoneFor((int) $biz->id);
        $day = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay();
        $now = \Illuminate\Support\Carbon::now($tz);

        if ($day->lt($now->copy()->startOfDay()) || $day->gt($now->copy()->addDays(120))) {
            return response()->json(['success' => false, 'message' => __('اختر يومًا من الأيام القادمة.')], 422);
        }

        $duration = (int) ($data['duration_minutes'] ?? 60);
        $step = (int) ($data['step_minutes'] ?? 60);
        $serviceId = (int) ($data['service_id'] ?? 0)
            ?: (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $units = BookableItem::query()
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($q) => $q->where('service_id', $serviceId))
            ->when(! empty($data['item_type']), fn ($q) => $q->where('item_type', $data['item_type']))
            ->when(! empty($data['party_size']), fn ($q) => $q->where(fn ($w) => $w->whereNull('capacity')->orWhere('capacity', '>=', (int) $data['party_size'])))
            ->orderBy('code')->orderBy('id')
            ->limit(60)
            ->get();

        // the day's opening window; a business that never described its week is judged open 08:00–23:00
        $row = $hours->hoursFor((int) $biz->id)->get($day->dayOfWeek);
        $known = $hours->hoursFor((int) $biz->id)->isNotEmpty() && $row !== null;
        $closed = $known && ($row->is_closed || ! $row->open_time || ! $row->close_time);

        $open = $known && ! $closed ? (string) $row->open_time : '08:00:00';
        $close = $known && ! $closed ? (string) $row->close_time : '23:00:00';

        $payload = [
            'date' => $day->toDateString(),
            'duration_minutes' => $duration,
            'step_minutes' => $step,
            'hours_known' => $known && ! $closed,
            'closed' => $closed,
            'opens' => substr($open, 0, 5),
            'closes' => substr($close, 0, 5),
            'units_total' => $units->count(),
            'slots' => [],
        ];

        if ($closed || $units->isEmpty()) {
            return response()->json(['success' => true, 'data' => $payload]);
        }

        $from = $day->copy()->setTimeFromTimeString($open);
        $until = $day->copy()->setTimeFromTimeString($close);
        if ($until->lte($from)) {
            $until->addDay();
        }

        // start times on the step's own boundaries (a grid of :00 and :30, never :17)
        $minutes = ($from->hour * 60) + $from->minute;
        $remainder = $minutes % $step;
        if ($remainder !== 0) {
            $from->addMinutes($step - $remainder);
        }

        $slots = [];
        for ($t = $from->copy(); $t->copy()->addMinutes($duration)->lte($until) && count($slots) < 48; $t->addMinutes($step)) {
            $end = $t->copy()->addMinutes($duration);
            $free = [];

            // a time that has passed is shown, but nothing is free in it
            if ($t->gt($now)) {
                foreach ($units as $unit) {
                    $check = $this->availability->check($unit, $t->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                    if ($check['available']) {
                        $free[] = (int) $unit->id;
                    }
                }
            }

            $slots[] = [
                'starts_at' => $t->format('Y-m-d H:i:s'),
                'ends_at' => $end->format('Y-m-d H:i:s'),
                'free' => count($free),
                'unit_ids' => $free,
            ];
        }

        $payload['slots'] = $slots;

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * GET /api/v2/discovery/appointments/{business}/day-grid
     *
     * «موعد»: a salon, a craftsman, a shop that books time with the business itself — no units, no photos, no
     * add-ons. One day as a grid of start times, each as long as the chosen service (`offering_id`, a priced row of
     * this business; its `duration_minutes`), taken when the business already holds a live booking across it.
     *
     * One appointment at a time: the business has not said how many it serves together, and a grid that offers a
     * time the owner cannot keep is worse than one that offers fewer.
     */
    public function appointmentGrid(Request $request, int $business)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'offering_id' => ['nullable', 'integer'],
        ]);

        $biz = User::query()->where('type', 'business')->find($business, ['id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        $hours = app(\App\Services\BusinessHoursService::class);
        $tz = $hours->timezoneFor((int) $biz->id);
        $day = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay();
        $now = \Illuminate\Support\Carbon::now($tz);

        if ($day->lt($now->copy()->startOfDay()) || $day->gt($now->copy()->addDays(120))) {
            return response()->json(['success' => false, 'message' => __('اختر يومًا من الأيام القادمة.')], 422);
        }

        // how long the service takes: its own row, else the business's slot setting, else half an hour
        $settings = \Illuminate\Support\Facades\DB::table('business_booking_settings')->where('business_id', $biz->id)->first();
        $minutes = 0;
        if (! empty($data['offering_id'])) {
            $minutes = (int) BusinessServicePrice::query()
                ->where('business_id', $biz->id)->whereKey((int) $data['offering_id'])->value('duration_minutes');
        }
        if ($minutes <= 0) {
            $minutes = (int) ($settings->slot_minutes ?? 0);
        }
        $minutes = $minutes > 0 ? min(max($minutes, 5), 480) : 30;
        $step = max($minutes, 15);
        $lead = max((int) ($settings->lead_time_minutes ?? 0), 0);

        $row = $hours->hoursFor((int) $biz->id)->get($day->dayOfWeek);
        $known = $hours->hoursFor((int) $biz->id)->isNotEmpty() && $row !== null;
        $closed = $known && ($row->is_closed || ! $row->open_time || ! $row->close_time);
        $open = $known && ! $closed ? (string) $row->open_time : '09:00:00';
        $close = $known && ! $closed ? (string) $row->close_time : '21:00:00';

        $payload = [
            'date' => $day->toDateString(),
            'duration_minutes' => $minutes,
            'step_minutes' => $step,
            'hours_known' => $known && ! $closed,
            'closed' => $closed,
            'opens' => substr($open, 0, 5),
            'closes' => substr($close, 0, 5),
            'units_total' => 0,
            'slots' => [],
        ];

        if ($closed) {
            return response()->json(['success' => true, 'data' => $payload]);
        }

        $from = $day->copy()->setTimeFromTimeString($open);
        $until = $day->copy()->setTimeFromTimeString($close);
        if ($until->lte($from)) {
            $until->addDay();
        }

        // what the business already holds across this day (a booking without an end holds its own duration)
        $held = Booking::query()
            ->where('business_id', $biz->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $until->format('Y-m-d H:i:s'))
            ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$from->format('Y-m-d H:i:s')])
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($b) => [
                \Illuminate\Support\Carbon::parse($b->starts_at),
                $b->ends_at ? \Illuminate\Support\Carbon::parse($b->ends_at) : \Illuminate\Support\Carbon::parse($b->starts_at)->addMinutes(30),
            ]);

        $slots = [];
        for ($t = $from->copy(); $t->copy()->addMinutes($minutes)->lte($until) && count($slots) < 48; $t->addMinutes($step)) {
            $end = $t->copy()->addMinutes($minutes);
            $free = $t->gt($now->copy()->addMinutes($lead))
                && $held->every(fn ($h) => ! ($h[0]->lt($end) && $h[1]->gt($t)));

            $slots[] = [
                'starts_at' => $t->format('Y-m-d H:i:s'),
                'ends_at' => $end->format('Y-m-d H:i:s'),
                'free' => $free ? 1 : 0,
                'unit_ids' => [],
            ];
        }

        $payload['slots'] = $slots;

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * غرفةٌ كما تُعرض فى قائمة: صورةٌ ووصفٌ وسعرُها هى.
     *
     * كانت رقمًا وسعة — لا صورةَ ولا كلمة — فقائمةُ الغرف لا تشبه المنيو فى
     * شىء. والسعرُ هنا سعرُ هذه الغرفة بعينها لا سعرُ نوعها: «إطلالة بحرية»
     * مكتوبةٌ على الغرفة نفسها، وزيادتُها على سطر السعر تُضاف قبل أن تُعرض،
     * فتُقرأ ١٠١ بسبعمئة و١٠٢ بستّمئة من سطرٍ واحد.
     *
     * و`notes` لا يخرج من هنا أبدًا: هو ما يكتبه صاحبُ المحل لموظّفيه.
     *
     * @param  array{0:string,1:string}|null  $window
     */
    private function unitPayload(BookableItem $unit, ?array $window, ?BusinessServicePrice $price = null): array
    {
        // «العدد والرقم لكل غرفة لدى الفندق فقط»: a room TYPE with listed rooms is shown by what it is, never by a number.
        $byType = $unit->item_type === 'booking_stay' && $unit->rooms()->exists();

        $payload = [
            'id' => (int) $unit->id,
            'code' => $byType ? '' : (string) ($unit->code ?? ''),
            'title' => $byType ? null : $unit->title,
            'label' => $byType ? (string) (optional($unit->lineOption)->name_ar ?: optional($unit->lineOption)->name_en ?: $unit->displayLabel()) : $unit->displayLabel(),
            'description' => $unit->description,
            'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
            'images' => $unit->imagePayload(),
            // «Day use»: the room type is also sold through the day — {from, to, price}; null when it is not
            'day_use' => app(\App\Services\BookingDayUseService::class)->offer($unit),
        ];

        $payload += $this->pricingOf($unit, $price, $window);

        if (! $window) {
            return $payload;
        }

        // Reuses the service the engine itself checks with, so a unit shown as
        // free here cannot be refused at booking for a reason this never saw.
        $check = $this->availability->check($unit, $window[0], $window[1]);

        $payload['available'] = (bool) $check['available'];
        $payload['reason'] = $check['reason'];

        return $payload;
    }

    /**
     * ما تعلنه الوحدةُ عن نفسها، وما يكلّفه.
     *
     * المُوصِّفاتُ المسعَّرة تُقرأ بنفس الحساب الذى يحسب به المحرّك الفاتورة
     * — `resolvePriceBreakdown` نفسها — فما يُعرض هنا هو ما سيُحاسَب عليه
     * النزيل، لا تقديرًا موازيًا له.
     *
     * وسطرُ سعرٍ ناقص يعنى نوعًا لم يُسعَّر بعد: تُعرض الوحدةُ بلا سعر بدل
     * أن تُخفى، فصاحبُ المحل يرى ما ينقصه.
     *
     * @return array<string,mixed>
     */
    private function pricingOf(BookableItem $unit, ?BusinessServicePrice $price, ?array $window = null): array
    {
        if (! $price) {
            return ['price' => null, 'total' => null, 'periods' => null, 'period_unit' => null, 'modifiers' => []];
        }

        $ownOptions = $unit->relationLoaded('offeringOptions')
            ? $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')->all()
            : $unit->modifierOptionIds()->all();

        $breakdown = $this->engine->resolvePriceBreakdown(
            service: $unit->service ?: new PlatformService(),
            businessPrice: $price,
            bookable: $unit,
            quantity: 1,
            // بالنافذة حين تُعطى: قاعدةُ «الجمعة أغلى» تُقرأ فى القائمة كما
            // ستُقرأ فى الفاتورة، وليلةً ليلة كما ستُحاسَب.
            pricingDate: $window[0] ?? null,
            optionIds: array_map('intval', $ownOptions),
            until: $window[1] ?? null
        );

        $periods = (int) ($breakdown['periods_count'] ?? 1);

        return [
            // سعرُ الفترة الواحدة — ليلةٍ أو ساعة — وهو ما يُعرض فى القائمة.
            // و`total` مجموعُ النافذة حين تُعطى: «٦٠٠ لليلة · ١٨٠٠ لثلاث
            // ليالٍ»، فلا يُفاجأ النزيلُ بالفرق فى شاشة الدفع.
            'price' => (float) $breakdown['unit_price'],
            'total' => $window ? (float) $breakdown['final_price'] : null,
            'periods' => $window ? $periods : null,
            'period_unit' => $breakdown['period_unit'] ?? null,
            'modifiers' => $breakdown['modifiers'],
        ];
    }

    /**
     * ما يُعرض على النزيل مع هذا النوع، وسعرُ كلٍّ منه.
     *
     * «غرفة فردى ٦٠٠» ثم «إفطار +٥٠» و«إقامة كاملة +١٥٠»، كما تُقرأ فى أىِّ
     * موقع حجز. وهى مُوصِّفاتُ سطر السعر — إلا ما أعلنته الغرفةُ عن نفسها
     * أصلًا: «إطلالة بحرية» محسوبةٌ فى سعرها المعروض، فعرضُها ثانيةً كخيارٍ
     * يُحصِّل ثمنَها مرتين.
     *
     * @param  \Illuminate\Support\Collection<int,BookableItem>  $inKind
     * @return array<int,array<string,mixed>>
     */
    private function choicesOf(?BusinessServicePrice $price, $inKind): array
    {
        if (! $price) {
            return [];
        }

        $declared = $inKind->flatMap(
            fn (BookableItem $unit) => $unit->relationLoaded('offeringOptions')
                ? $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')
                : $unit->modifierOptionIds()
        )->map(fn ($id) => (int) $id)->unique();

        /*
         * من سطر السعر ومن النشاط معًا.
         *
         * نظامُ الوجبات يسكن النشاطَ نفسه — سعرٌ ثابت لا يتغيّر بنوع الغرفة —
         * وما يخصّ نوعًا بعينه يبقى على سطره. والسطرُ يغلب عند التكرار.
         */
        $rows = OfferingOption::query()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            // مُوصِّفٌ بقيمة صفر يوصِّف ولا يُسعِّر، فلا شأن لشاشة الاختيار به.
            ->where('adjust_value', '!=', 0)
            ->whereNotIn('option_id', $declared->all() ?: [0])
            ->where(function ($query) use ($price) {
                $query->where(function ($sub) use ($price) {
                    $sub->where('offering_type', $price->getMorphClass())
                        ->where('offering_id', (int) $price->id);
                })->orWhere(function ($sub) use ($price) {
                    $sub->where('offering_type', (new User)->getMorphClass())
                        ->where('offering_id', (int) $price->business_id);
                });
            })
            ->with('option:id,name_ar,name_en')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('option_id')
            ->map(fn ($group) => $group->firstWhere('offering_type', $price->getMorphClass()) ?: $group->first())
            ->values();

        return $rows->map(fn (OfferingOption $row) => [
            'option_id' => (int) $row->option_id,
            'name' => $this->say($row->option),
            'adjust_type' => (string) $row->adjust_type,
            'adjust_value' => (float) $row->adjust_value,
            // «لكل فرد» تُضرب فى عدد النزلاء وقت الحساب، فالمعروضُ هنا سعرُ
            // الفرد الواحد ومعه العَلَم — والتطبيقُ يضربه فى عدد من يحجز.
            'per_person' => (bool) $row->per_person,
            'amount' => $row->appliedTo(round((float) $price->baseUnitPrice(), 2)),
        ])->values()->all();
    }

    private function say(?object $option): ?string
    {
        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '') ? $primary : (($option->name_ar ?: $option->name_en) ?: null);
    }

    private function kindName(BookableItem $unit): ?string
    {
        $option = $unit->lineOption;

        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '')
            ? $primary
            : (($option->name_ar ?: $option->name_en) ?: null);
    }
}
