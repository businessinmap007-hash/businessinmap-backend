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
 * «خدمة ليموزين» — a ride asked for the way inDrive does it, but with no map: the customer names where from and where
 * to (a city each, and a landmark in words), the price he starts from comes from the distance, every limousine driver
 * of the origin's governorate may answer with his own price, and the one the customer picks becomes an ordinary
 * booking — which brings the platform's whole machinery with it (attendance by both, the deposit, the fee, the rating,
 * the dispute).
 */
final class RideRequestKind implements RequestKind
{
    /** The trade whose businesses answer rides. */
    public const TRADE = 'خدمة ليموزين';

    private static ?int $tradeId = null;

    public function key(): string
    {
        return ServiceRequest::KIND_RIDE;
    }

    public function maxPrice(): float
    {
        return 100000;
    }

    public function tradeIds(): array
    {
        return [self::tradeId()];
    }

    public static function tradeId(): int
    {
        return self::$tradeId ??= (int) DB::table('category_children_master')->where('name_ar', self::TRADE)->value('id');
    }

    public function build(User $requester, array $input): array
    {
        $data = Validator::make($input, [
            'origin' => ['required', 'array'],
            'origin.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'origin.city_id' => ['required', 'integer', 'exists:cities,id'],
            'origin.label' => ['nullable', 'string', 'max:190'],
            'destination' => ['required', 'array'],
            'destination.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'destination.city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination.label' => ['nullable', 'string', 'max:190'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:30'],
            'starts_at' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'distance_km' => ['nullable', 'numeric', 'min:0.3', 'max:3000'],
            'details' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        foreach (['origin', 'destination'] as $end) {
            $cityGovernorate = (int) DB::table('cities')->where('id', (int) $data[$end]['city_id'])->value('governorate_id');

            if ($cityGovernorate !== (int) $data[$end]['governorate_id']) {
                throw ValidationException::withMessages(["{$end}.city_id" => __('المدينة ليست من هذه المحافظة.')]);
            }
        }

        $quote = RideQuote::between((int) $data['origin']['city_id'], (int) $data['destination']['city_id'], isset($data['distance_km']) ? (float) $data['distance_km'] : null);

        if ($quote === null) {
            throw ValidationException::withMessages(['distance_km' => __('اكتب المسافة التقريبية بالكيلومتر.')]);
        }

        $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;

        if ($startsAt !== null && ($startsAt->lt(now()->subMinutes(5)) || $startsAt->gt(now()->addDays(30)))) {
            throw ValidationException::withMessages(['starts_at' => __('اختر موعدًا خلال الثلاثين يومًا القادمة.')]);
        }

        $cityNames = DB::table('cities')->whereIn('id', [(int) $data['origin']['city_id'], (int) $data['destination']['city_id']])->pluck('name_ar', 'id');
        $from = (string) ($cityNames[(int) $data['origin']['city_id']] ?? '');
        $to = (string) ($cityNames[(int) $data['destination']['city_id']] ?? '');

        $end = fn (array $e, string $city) => [
            'governorate_id' => (int) $e['governorate_id'],
            'city_id' => (int) $e['city_id'],
            'city' => $city,
            'label' => isset($e['label']) ? trim((string) $e['label']) : null,
        ];

        return [
            'category_child_id' => self::tradeId(),
            'governorate_id' => (int) $data['origin']['governorate_id'],
            'city_id' => (int) $data['origin']['city_id'],
            'title' => 'مشوار: ' . $from . ' ← ' . $to,
            'details' => $data['details'] ?? null,
            'params' => [
                'origin' => $end($data['origin'], $from),
                'destination' => $end($data['destination'], $to),
                'distance_km' => $quote['distance_km'],
                'distance_source' => $quote['source'],
                'passengers' => (int) ($data['passengers'] ?? 1),
            ],
            'starts_at' => $startsAt,
            'budget' => isset($data['price']) ? round((float) $data['price'], 2) : $quote['suggested_price'],
            'suggested_price' => $quote['suggested_price'],
            // an immediate ride is asked for only so long; a scheduled one lives until its time
            'expires_at' => $startsAt ?? now()->addMinutes((int) config('bim.rides.ttl_minutes', 30)),
        ];
    }

    public function eligible(ServiceRequest $request, int $limit = 500)
    {
        $origin = DB::table('cities')->where('id', (int) $request->city_id)->first(['latitude', 'longitude']);

        $drivers = User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', self::tradeId())
            ->where('governorate_id', (int) $request->governorate_id)
            ->where('id', '!=', (int) $request->requester_id)
            ->whereNull('banned_at')->whereNull('anonymized_at')
            ->limit(2000)
            ->get(['id', 'name', 'latitude', 'longitude']);

        // nearest first: a driver with a known position is ranked by his distance to the pick-up city, the rest follow
        return $drivers->sortBy(function (User $d) use ($origin) {
            if (! $origin || $origin->latitude === null || $d->latitude === null || $d->longitude === null) {
                return PHP_INT_MAX;
            }

            return RideQuote::haversineKm((float) $origin->latitude, (float) $origin->longitude, (float) $d->latitude, (float) $d->longitude);
        })->take($limit)->values();
    }

    public function mayAnswer(ServiceRequest $request, User $business): bool
    {
        return $business->type === User::TYPE_BUSINESS
            && (int) $business->category_child_id === self::tradeId()
            && (int) $business->governorate_id === (int) $request->governorate_id
            && (int) $business->id !== (int) $request->requester_id
            && $business->banned_at === null
            && $business->anonymized_at === null;
    }

    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer): Model
    {
        $start = $request->starts_at ?? now();
        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        return Booking::create([
            'user_id' => (int) $request->requester_id,
            'business_id' => (int) $offer->business_id,
            'service_id' => $serviceId,
            'status' => Booking::STATUS_ACCEPTED,
            'price' => (float) $offer->price,
            'quantity' => 1,
            'party_size' => (int) data_get($request->params, 'passengers', 1),
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
                // what the booking calls itself everywhere (the list, the detail, the notifications)
                'display_title' => (string) $request->title,
                'ride' => $request->params,
                'created_by_user_id' => (int) $request->requester_id,
            ],
        ]);
    }
}
