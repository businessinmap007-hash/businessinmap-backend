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
            // a guest who has said who they are sees only the room types sold to them
            'guest_origin' => ['nullable', 'in:local,foreign'],
        ]);

        $biz = User::query()->live()->where('type', 'business')
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
            ->withCount('rooms')
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when(! empty($data['item_type']), fn ($query) => $query->where('item_type', $data['item_type']))
            ->orderBy('item_type')
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->filter(fn (BookableItem $u) => empty($data['guest_origin']) || $u->allowsOrigin($data['guest_origin']))
            ->values();

        // A window is optional: without it the answer is «what exists and what
        // it costs», with it «what is still free». Asking for a price is the
        // common first screen and must not require picking dates first.
        $window = (! empty($data['starts_at']) && ! empty($data['ends_at']))
            ? [$data['starts_at'], $data['ends_at']]
            : null;

        // everything the availability answer needs for ALL the types is read here, once, not per type
        if ($window) {
            $this->availability->preload($units, $window[0], $window[1], (int) optional($request->user('sanctum'))->id);
        }

        $groups = [];
        // the holds this guest still has: they come back to the screen and find the room as they left it
        $mine = app(\App\Services\BookingHolds::class)->mine((int) optional($request->user('sanctum'))->id, $units->pluck('id')->all());

        foreach ($units->groupBy(fn (BookableItem $unit) => (int) ($unit->line_option_id ?? 0)) as $kindId => $inKind) {
            /** @var \App\Models\BookableItem $first */
            $first = $inKind->first();
            $price = $this->prices->resolveForBookableItem($first);

            // a room type whose numbered rooms differ («إطلالة بحر»، «إطلالة مسبح») is shown as one card per kind of room, each at its own
            // all-in price: the view is part of what the room IS, not something the guest ticks
            $rows = $inKind->flatMap(fn (BookableItem $unit) => $this->variantsOf($unit, $price, $window, $this->unitPayload($unit, $window, $price) + ['my_hold' => $mine[$unit->id] ?? null]));

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
                // «عروض الحجز»: the hotel's offer on this kind of room — the night's price after it, or the words of the rule
                'offer' => $price ? app(\App\Services\Promotions\BookingPromotionPricing::class)->badge($first, $price, (float) $price->baseUnitPrice(), (int) optional($request->user('sanctum'))->id) : null,
                // ما يُعرض على النزيل ليقرّره: «إفطار +٥٠»، «إقامة كاملة
                // +١٥٠». يُرسَل مع النوع لا مع الوحدة، لأنه سعرُ النوع.
                'choices' => $this->choicesOf($price, $inKind, true),
                'units' => $rows->values(),
            ];
        }

        // Priced kinds first, then by price: an unpriced kind cannot be sold and
        // is the one the business still has to finish, not the one to lead with.
        usort($groups, function (array $a, array $b) {
            return [$a['price'] === null, $a['price'] ?? 0] <=> [$b['price'] === null, $b['price'] ?? 0];
        });

        $this->availability->forget();

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
                // «غرف الشركاء»: rooms another business (a hotel) gave this one to sell — at THIS business's own price. The contract
                // price, the quantities and the terms are the two parties' alone and never appear here.
                'partner_rooms' => $this->partnerRooms((int) $biz->id, $window),
                // «سياسة الأطفال» of this hotel: the app asks each child's age and says what it pays
                'child_policy' => \App\Models\ChildPolicy::forBusiness((int) $biz->id)?->payload(),
                // «بالموعد» or «بالدور»: how this business takes its appointments
                'flow' => \App\Services\AppointmentQueue::flowOf((int) $biz->id),
                // «أشكال الحجز»: how this trade page is drawn (sections or a flat list, what is shown, in what order).
                // null for a trade nobody has put on a shape — the client draws what it always drew.
                'shape' => app(\App\Services\BookingShapes::class)->payload(app(\App\Services\BookingShapes::class)->forBusiness((int) $biz->id)),
            ],
        ]);
    }

    /**
     * The rooms a business sells on behalf of another through an active allocation: the room card (what it is, photos, features),
     * the price THIS business set, and what is left. `bookable_id` is the owner's unit, booked through this business.
     *
     * @param  array{0:string,1:string}|null  $window
     * @return array<int,array<string,mixed>>
     */
    private function partnerRooms(int $businessId, ?array $window): array
    {
        $allocations = \App\Models\BookableAllocation::query()->active()->where('partner_business_id', $businessId)
            ->whereHas('partnership', fn ($q) => $q->where('status', \App\Models\BusinessPartnership::STATUS_ACTIVE))
            ->with(['bookableItem' => fn ($q) => $q->withCount('rooms'), 'bookableItem.lineOption:id,name_ar,name_en', 'bookableItem.images', 'bookableItem.offeringOptions.option:id,name_ar,name_en', 'bookableItem.service', 'ownerBusiness:id,name'])->orderBy('id')->get();

        $cards = app(\App\Services\Partnerships\AllocationService::class);
        $nights = $window ? max((int) \Illuminate\Support\Carbon::parse($window[0])->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($window[1])->startOfDay()), 1) : null;

        $allocations = $allocations->filter(fn ($a) => $a->bookableItem && $a->bookableItem->is_active);
        // every card's nights are counted in two queries, not two per card
        $lefts = $window ? \App\Models\BookableAllocation::availableForMany($allocations, \Illuminate\Support\Carbon::parse($window[0]), \Illuminate\Support\Carbon::parse($window[1])) : [];

        // the free-or-not answer of every room, read once: it also knows the rooms the hotel keeps back for itself in peak
        if ($window) {
            $this->availability->preload($allocations->pluck('bookableItem'), $window[0], $window[1], (int) optional(request()->user('sanctum'))->id);
        }

        $holds = app(\App\Services\BookingHolds::class)->mine((int) optional(request()->user('sanctum'))->id, $allocations->pluck('bookable_item_id')->map(fn ($id) => (int) $id)->unique()->all());

        $rooms = $allocations->map(function ($a) use ($cards, $window, $nights, $lefts, $holds) {
            $left = $window ? ($lefts[(int) $a->id] ?? 0) : $a->availableQuantity();
            $reason = null;

            if ($left <= 0) {
                $reason = __('نفدت الكمية.');
            } elseif ($nights !== null && (($a->min_nights && $nights < (int) $a->min_nights) || ($a->max_nights && $nights > (int) $a->max_nights))) {
                $reason = __('مدة الإقامة خارج ما تسمح به هذه الغرفة.');
            } elseif ($window && $a->isReleasedForDate($window[0])) {
                $reason = __('أُغلق الحجز على هذه الغرفة لقرب الموعد.');
            }

            if ($reason === null && $window) {
                $answer = $this->availability->check($a->bookableItem, $window[0], $window[1], 1, (int) $a->partner_business_id, true);

                if (! ($answer['available'] ?? true)) {
                    $reason = $answer['reason'];
                }
            }

            $public = $cards->publicRoom($a, $window, $window ? ($lefts[(int) $a->id] ?? 0) : null, $holds, true);

            $room = $cards->card($a->bookableItem) + [
                // the hotel's «سياسة الأطفال» goes with its room, whoever sells it
                'child_policy' => \App\Models\ChildPolicy::forBusiness((int) $a->owner_business_id)?->payload(),
                'choices' => $public['choices'],
                'my_hold' => $public['my_hold'],
                'offer' => $public['offer'],
                'guest_scope' => $public['guest_scope'],
                'allocation_id' => (int) $a->id,
                'bookable_id' => (int) $a->bookable_item_id,
                'service_id' => (int) $a->bookableItem->service_id,
                'item_type' => (string) $a->bookableItem->item_type,
                'price' => round((float) $a->finalPrice(), 2),
                'currency' => (string) ($a->currency ?: 'EGP'),
                'from' => $a->ownerBusiness ? ['id' => (int) $a->ownerBusiness->id, 'name' => (string) $a->ownerBusiness->name] : null,
                'available_quantity' => $left,
                'min_nights' => $a->min_nights,
                'max_nights' => $a->max_nights,
                'available' => $reason === null,
                'reason' => $reason,
            ];

            return $this->partnerKinds($a, $room, $window);
        })->flatten(1)->values()->all();

        $this->availability->forget();

        return $rooms;
    }

    /**
     * A room a partner sells whose numbered rooms differ («إطلالة بحر»، «إطلالة مسبح») is shown as one card per kind, each at the partner's
     * price plus what that kind carries (the hotel's listed price of the view): «غرفة فردية · إطلالة بحرية — 1400». The card names the features
     * it was priced with (`option_ids`) and the booking carries them, so the hotel gives a number that has them when the stay starts.
     *
     * @param  array<string,mixed>  $room
     * @param  array{0:string,1:string}|null  $window
     * @return array<int,array<string,mixed>>
     */
    private function partnerKinds(\App\Models\BookableAllocation $a, array $room, ?array $window): array
    {
        $unit = $a->bookableItem;
        $rooms = app(\App\Services\BookingRoomService::class);
        $kinds = $rooms->roomKinds($unit);

        if (! $unit->hasListedRooms() || collect($kinds)->every(fn ($ids) => $ids === [])) {
            return [$room];
        }

        $hotel = \App\Models\User::query()->find((int) $a->owner_business_id);
        $prices = $hotel ? $hotel->currentOfferingAdjustments() : [];
        $names = \App\Models\Option::query()->whereIn('id', collect($kinds)->flatten()->unique()->all() ?: [0])->get(['id', 'name_ar', 'name_en'])->keyBy('id');
        $out = [];

        foreach ($kinds as $set) {
            $variant = $room;
            $words = array_values(array_filter(array_map(fn ($id) => isset($names[$id]) ? ($names[$id]->name_ar ?: $names[$id]->name_en) : null, $set)));
            $extra = 0.0;

            foreach ($set as $id) {
                $row = $prices[$id] ?? null;
                $extra += $row ? (($row['type'] ?? 'amount') === 'percent' ? $room['price'] * (float) $row['value'] / 100 : (float) $row['value']) : 0.0;
            }

            $variant['label'] = $words === [] ? ($room['label'] ?? '') : (($room['label'] ?? '') . ' · ' . implode('، ', $words));
            $variant['features'] = array_values(array_unique(array_merge($room['features'] ?? [], $words)));
            $variant['price'] = round((float) $room['price'] + $extra, 2);
            $variant['option_ids'] = array_map('intval', $set);

            // a view is sold only while some room that carries it is free on every night of the stay
            if ($window && ($variant['available'] ?? false) && $set !== []) {
                try {
                    $rooms->assertFeaturesAvailable($unit, $window[0], $window[1], 1, $set);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $variant['available'] = false;
                    $variant['reason'] = collect($e->errors())->flatten()->first();
                }
            }

            $out[] = $variant;
        }

        usort($out, fn ($x, $y) => $x['price'] <=> $y['price']);

        return $out;
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

        $biz = User::query()->live()->where('type', 'business')->find($business, ['id']);

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
            'chair_id' => ['nullable', 'integer'],
            'provider_id' => ['nullable', 'integer'],
        ]);

        $biz = User::query()->live()->where('type', 'business')->find($business, ['id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        // «اختر مقدّم الخدمة»: the grid of one person at this business
        $providerId = ! empty($data['provider_id']) ? (int) $data['provider_id'] : null;

        if ($providerId !== null && ! \App\Support\Providers::isProviderOf($providerId, (int) $biz->id)) {
            return response()->json(['success' => false, 'message' => __('هذا الشخص ليس من مقدّمي الخدمة في هذا النشاط.')], 422);
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

        // what the business already holds across this day (a booking without an end holds its own duration) — with
        // chairs, per chair: a time is free when some candidate chair has nothing across it
        $queue = app(\App\Services\AppointmentQueue::class);
        $chairs = \App\Services\AppointmentQueue::chairsOf((int) $biz->id);
        $askedChair = ! empty($data['chair_id']) ? (int) $data['chair_id'] : null;
        // with a person chosen the business is not one line any more: only HIS time counts (and a chair's, if it has chairs)
        $lines = $chairs->isEmpty()
            ? [$providerId !== null ? collect() : $queue->heldOn((int) $biz->id, $day, $until, null)]
            : ($askedChair !== null ? $chairs->where('id', $askedChair) : $chairs)
                ->map(fn ($c) => $queue->heldOn((int) $biz->id, $day, $until, (int) $c->id))->values()->all();
        $personHeld = $providerId !== null ? $queue->heldOn((int) $biz->id, $day, $until, null, $providerId) : collect();

        $slots = [];
        for ($t = $from->copy(); $t->copy()->addMinutes($minutes)->lte($until) && count($slots) < 48; $t->addMinutes($step)) {
            $end = $t->copy()->addMinutes($minutes);
            $free = $t->gt($now->copy()->addMinutes($lead)) && collect($lines)->contains(
                fn ($held) => $held->every(fn ($h) => ! ($h[0]->lt($end) && $h[1]->gt($t)))
            ) && $personHeld->every(fn ($h) => ! ($h[0]->lt($end) && $h[1]->gt($t)));

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
     * GET /api/v2/discovery/appointments/{business}/queue — «بالدور»: the turn a customer would get on a day, and the
     * time it should come to. Public; nothing is taken until the booking is made (`POST /bookings/queue`).
     */
    public function appointmentQueue(Request $request, int $business)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'offering_id' => ['nullable', 'integer'],
            'chair_id' => ['nullable', 'integer'],
        ]);

        abort_unless(User::query()->live()->where('type', 'business')->whereKey($business)->exists(), 404, __('النشاط غير موجود.'));

        $tz = app(\App\Services\BusinessHoursService::class)->timezoneFor($business);
        $day = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay();
        $today = \Illuminate\Support\Carbon::now($tz)->startOfDay();

        if ($day->lt($today) || $day->gt($today->copy()->addDays(30))) {
            return response()->json(['success' => false, 'message' => __('اختر يومًا من الأيام القادمة.')], 422);
        }

        $minutes = \App\Services\AppointmentQueue::serviceMinutes($business, ! empty($data['offering_id']) ? (int) $data['offering_id'] : null);
        $next = app(\App\Services\AppointmentQueue::class)->next($business, $day, $minutes, ! empty($data['chair_id']) ? (int) $data['chair_id'] : null);

        return response()->json(['success' => true, 'data' => [
            'chair_id' => $next['chair_id'],
            'flow' => \App\Services\AppointmentQueue::flowOf($business),
            'date' => $day->toDateString(),
            'duration_minutes' => $minutes,
            'closed' => $next['closed'],
            'full' => $next['full'],
            'turn' => $next['turn'],
            'ahead' => $next['ahead'],
            'expected_at' => $next['starts_at']?->format('Y-m-d H:i:s'),
            'ends_at' => $next['ends_at']?->format('Y-m-d H:i:s'),
        ]]);
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
        $byType = $unit->item_type === 'booking_stay' && $unit->hasListedRooms();

        $payload = [
            'id' => (int) $unit->id,
            'code' => $byType ? '' : (string) ($unit->code ?? ''),
            'title' => $byType ? null : $unit->readableTitle(),
            'label' => $byType ? (string) (optional($unit->lineOption)->name_ar ?: optional($unit->lineOption)->name_en ?: $unit->displayLabel()) : $unit->displayLabel(),
            'description' => $unit->description,
            'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
            'guest_scope' => (string) ($unit->guest_scope ?: 'all'),
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
        $check = $this->availability->check($unit, $window[0], $window[1], 1, null, true);

        $payload['available'] = (bool) $check['available'];
        $payload['reason'] = $check['reason'];
        // «تحت التعامل»: someone else is filling in a booking for it right now — it frees itself if they walk away
        $payload['held'] = ($check['code'] ?? '') === 'held_by_other';
        // promised to a partner of the hotel: the guest may ask for it to be released
        $payload['held_for_partner'] = ($check['code'] ?? '') === 'held_for_partner';

        return $payload;
    }

    /**
     * One card per KIND OF ROOM behind a room type. The numbered rooms of «غرفة فردية» may differ (some face the sea, some the pool); the
     * guest sees «غرفة فردية · إطلالة بحر — 1300 لليلة» and «غرفة فردية · إطلالة مسبح — 1150», each priced as the type's price plus what that
     * kind of room carries, and books the card. The card names the features it was priced with (`option_ids`); when the stay starts the
     * hotel gives a number that carries them.
     *
     * @param  array<string,mixed>  $row  the type's own row
     * @param  array{0:string,1:string}|null  $window
     * @return array<int,array<string,mixed>>
     */
    private function variantsOf(BookableItem $unit, ?BusinessServicePrice $price, ?array $window, array $row): array
    {
        if ($unit->item_type !== 'booking_stay' || ! $unit->hasListedRooms()) {
            return [$row];
        }

        $rooms = app(\App\Services\BookingRoomService::class);
        $sets = collect($rooms->roomKinds($unit));

        if ($sets->every(fn ($ids) => $ids === [])) {
            return [$row];
        }

        $names = \App\Models\Option::query()->whereIn('id', $sets->flatten()->unique()->all() ?: [0])->get(['id', 'name_ar', 'name_en'])->keyBy('id');
        // the card's title is built the way the type's own label is (Arabic first), so one title never mixes two languages
        $say = fn (int $id) => $names[$id]->name_ar ?: $names[$id]->name_en;
        $out = [];

        foreach ($sets as $set) {
            $variant = $row;
            $words = array_values(array_filter(array_map(fn ($id) => isset($names[$id]) ? $say((int) $id) : null, $set)));
            $variant['label'] = $words === [] ? $row['label'] : ($row['label'] . ' · ' . implode('، ', $words));
            $variant['option_ids'] = array_map('intval', $set);
            $variant = array_merge($variant, $this->pricingOf($unit, $price, $window, array_map('intval', $set)));

            // a view is sold only as long as some room carrying it is free on every night of the stay
            if ($window && ($variant['available'] ?? false) && $set !== []) {
                try {
                    $rooms->assertFeaturesAvailable($unit, $window[0], $window[1], 1, $set);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $variant['available'] = false;
                    $variant['reason'] = collect($e->errors())->flatten()->first();
                }
            }

            $out[] = $variant;
        }

        usort($out, fn ($a, $b) => [$a['price'] ?? PHP_INT_MAX] <=> [$b['price'] ?? PHP_INT_MAX]);

        return $out;
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
    private function pricingOf(BookableItem $unit, ?BusinessServicePrice $price, ?array $window = null, array $extraOptionIds = []): array
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
            optionIds: array_values(array_unique(array_merge(array_map('intval', $ownOptions), $extraOptionIds))),
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

    /** The add-ons a guest may take with this kind (see UnitChoices). @return array<int,array<string,mixed>> */
    private function choicesOf(?BusinessServicePrice $price, $inKind, bool $featuresAsVariants = false): array
    {
        return app(\App\Services\Hotels\UnitChoices::class)->for($price, $inKind, $featuresAsVariants);
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
