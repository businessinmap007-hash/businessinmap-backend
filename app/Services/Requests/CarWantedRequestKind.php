<?php

namespace App\Services\Requests;

use App\Models\Booking;
use App\Models\PlatformService;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestOffer;
use App\Models\User;
use App\Services\BusinessHoursService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * «أريد سيارة بهذه المواصفات» — the buyer (or the renter) describes the car he is after, the showrooms and private
 * sellers of his governorate answer with what they have, and the one he picks becomes an appointment to see it.
 *
 * The request is the mirror of a listing: «نوع التعامل» (buy or rent), the kind (سيدان / SUV / بيك أب), the make, the
 * year he wants at least, the most he will pay (a rental: per day, and for how many days). A dealer answers with a
 * price and, ideally, points at the very car in his menu. Accepting makes a viewing booking with that dealer — the
 * first real step of buying or renting a car, and the platform's ordinary booking machinery from there on.
 */
final class CarWantedRequestKind implements RequestKind
{
    /** Who sells or lets a car, by trade name — the trade ids are looked up, never hard-coded. */
    private const TRADES = ['معرض سيارات', 'سيارة من المالك'];

    private const TTL_DAYS = 14;

    public function key(): string
    {
        return ServiceRequest::KIND_CAR_WANTED;
    }

    public function maxPrice(): float
    {
        return 1000000000;
    }

    /**
     * The two lists the buyer picks from, in the platform's own words.
     *
     * @return array{vehicle_types: list<array{id:int,name:string}>, brands: list<array{id:int,name:string}>}
     */
    public function formOptions(): array
    {
        $english = app()->getLocale() === 'en';
        $of = fn (string $group) => DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->orderBy('o.name_ar')->get(['o.id', 'o.name_ar', 'o.name_en'])
            ->map(fn ($o) => ['id' => (int) $o->id, 'name' => (string) ($english ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en))])
            ->values()->all();

        return ['vehicle_types' => $of('نوع المركبة'), 'brands' => $of('ماركات السيارات')];
    }

    /** @return list<int> */
    public function tradeIds(): array
    {
        return DB::table('category_children_master')->whereIn('name_ar', self::TRADES)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function build(User $requester, array $input): array
    {
        $data = Validator::make($input, [
            'deal' => ['required', 'in:buy,rent'],
            'vehicle_type_option_id' => ['nullable', 'integer'],
            'brand_option_id' => ['nullable', 'integer'],
            'year_min' => ['nullable', 'integer', 'min:1980', 'max:' . ((int) now()->year + 1)],
            'max_price' => ['nullable', 'numeric', 'min:1', 'max:1000000000'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'starts_at' => ['nullable', 'date'],
            'details' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $type = $this->option($data['vehicle_type_option_id'] ?? null, 'نوع المركبة', 'vehicle_type_option_id');
        $brand = $this->option($data['brand_option_id'] ?? null, 'ماركات السيارات', 'brand_option_id');

        if (! empty($data['city_id']) && (int) DB::table('cities')->where('id', (int) $data['city_id'])->value('governorate_id') !== (int) $data['governorate_id']) {
            throw ValidationException::withMessages(['city_id' => __('المدينة ليست من هذه المحافظة.')]);
        }

        $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;

        if ($startsAt !== null && ($startsAt->lt(now()->subMinutes(5)) || $startsAt->gt(now()->addDays(30)))) {
            throw ValidationException::withMessages(['starts_at' => __('اختر موعدًا خلال الثلاثين يومًا القادمة.')]);
        }

        $rent = $data['deal'] === 'rent';
        $words = array_values(array_filter([
            // the title is stored once, in Arabic, like a ride's — every reader sees the same words
            $rent ? 'أريد سيارة للإيجار' : 'أريد سيارة',
            $type['name'] ?? null,
            $brand['name'] ?? null,
            ! empty($data['year_min']) ? $data['year_min'] . ' فأحدث' : null,
        ]));

        return [
            'category_child_id' => $this->tradeIds()[0] ?? 0,
            'governorate_id' => (int) $data['governorate_id'],
            'city_id' => ! empty($data['city_id']) ? (int) $data['city_id'] : null,
            'title' => implode(' · ', $words),
            'details' => $data['details'] ?? null,
            'params' => array_filter([
                'deal' => $data['deal'],
                'vehicle_type' => $type,
                'brand' => $brand,
                'year_min' => $data['year_min'] ?? null,
                'days' => $rent ? ($data['days'] ?? null) : null,
            ], fn ($v) => $v !== null),
            'starts_at' => $startsAt,
            // what he will pay at most (a rental: per day) — the number a dealer's offer is read against
            'budget' => isset($data['max_price']) ? round((float) $data['max_price'], 2) : null,
            'suggested_price' => null,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ];
    }

    public function eligible(ServiceRequest $request, int $limit = 500)
    {
        return User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->whereIn('category_child_id', $this->tradeIds())
            ->where('governorate_id', (int) $request->governorate_id)
            ->where('id', '!=', (int) $request->requester_id)
            ->whereNull('banned_at')->whereNull('anonymized_at')->whereNull('requests_paused_at')
            ->limit($limit)
            ->get(['id', 'name']);
    }

    public function mayAnswer(ServiceRequest $request, User $business): bool
    {
        return $business->type === User::TYPE_BUSINESS
            && in_array((int) $business->category_child_id, $this->tradeIds(), true)
            && (int) $business->governorate_id === (int) $request->governorate_id
            && (int) $business->id !== (int) $request->requester_id
            && $business->banned_at === null
            && $business->anonymized_at === null
            && $business->acceptsRequests();
    }

    /** The viewing: an appointment with the dealer, at the time the buyer proposed (tomorrow noon when he named none). */
    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer): Model
    {
        $start = $request->starts_at ?? now()->addDay()->setTime(12, 0);
        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        return Booking::create([
            'user_id' => (int) $request->requester_id,
            'business_id' => (int) $offer->business_id,
            'service_id' => $serviceId,
            'status' => Booking::STATUS_ACCEPTED,
            // a viewing is free: the price of the car is agreed with the dealer, not charged through the booking
            'price' => 0,
            'quantity' => 1,
            'party_size' => 1,
            'date' => $start->toDateString(),
            'time' => $start->format('H:i:s'),
            'starts_at' => $start,
            'all_day' => false,
            'timezone' => app(BusinessHoursService::class)->timezoneFor((int) $offer->business_id),
            'notes' => $request->details,
            'meta' => [
                'source' => 'service_request',
                'service_request_id' => (int) $request->id,
                'offer_id' => (int) $offer->id,
                'menu_item_id' => $offer->menu_item_id ? (int) $offer->menu_item_id : null,
                'offer_price' => (float) $offer->price,
                'display_title' => 'معاينة: ' . $request->title,
                'car_wanted' => $request->params,
                'created_by_user_id' => (int) $request->requester_id,
            ],
        ]);
    }

    /** @return array{id:int,name:string}|null the option, checked to belong to [$group] */
    private function option(mixed $id, string $group, string $field): ?array
    {
        if (empty($id)) {
            return null;
        }

        $row = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.id', (int) $id)->where('g.name_ar', $group)->first(['o.id', 'o.name_ar']);

        if (! $row) {
            throw ValidationException::withMessages([$field => __('الاختيار لا يخص هذه القائمة.')]);
        }

        return ['id' => (int) $row->id, 'name' => (string) $row->name_ar];
    }
}
