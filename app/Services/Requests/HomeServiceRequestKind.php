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
 * «أحتاج فنيًا» — a job at the customer's home (a leak, a socket, a wall to paint): he names the trade and says what is
 * wrong, the craftsmen of THAT trade in his governorate answer with what they would charge, and the one he picks becomes
 * an appointment with him. A quote, not a fixed price: the customer cannot know what the job costs, the craftsman can.
 *
 * Unlike a ride, whose drivers are all one trade, the trade is part of the request: a plumber never sees an
 * electrician's job. The customer's address is not in the request — only his city; the exact place is agreed once he
 * has chosen (the booking's chat), so it is never shown to the businesses that did not win.
 */
final class HomeServiceRequestKind implements RequestKind
{
    /** The trades that come to the customer's home, by name — the ids are looked up, never hard-coded. */
    private const TRADES = [
        'كهربائي', 'سباك', 'نقاش', 'نجار تنده', 'فني تبريد وتكييف', 'فني صيانة أجهزة منزلية', 'فني الوميتال', 'فني ستائر و تنجيد',
    ];

    /** A quote is asked for only so long. */
    private const TTL_DAYS = 3;

    public function key(): string
    {
        return ServiceRequest::KIND_HOME_SERVICE;
    }

    public function maxPrice(): float
    {
        return 1000000;
    }

    /** @return list<int> */
    public function tradeIds(): array
    {
        return DB::table('category_children_master')->whereIn('name_ar', self::TRADES)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The trades the customer picks from, in the platform's own words.
     *
     * @return list<array{id:int,name:string}>
     */
    public function formOptions(): array
    {
        $english = app()->getLocale() === 'en';

        return DB::table('category_children_master')->whereIn('name_ar', self::TRADES)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) ($english ? ($c->name_en ?: $c->name_ar) : ($c->name_ar ?: $c->name_en))])
            ->values()->all();
    }

    /** A business answers only the requests of its own trade. @return list<int> */
    public function answerTradesFor(User $business): array
    {
        return in_array((int) $business->category_child_id, $this->tradeIds(), true) ? [(int) $business->category_child_id] : [];
    }

    public function build(User $requester, array $input): array
    {
        $data = Validator::make($input, [
            'trade_child_id' => ['required', 'integer'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'details' => ['required', 'string', 'min:5', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'max_price' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ])->validate();

        if (! in_array((int) $data['trade_child_id'], $this->tradeIds(), true)) {
            throw ValidationException::withMessages(['trade_child_id' => __('اختر نوع الفني.')]);
        }

        if ((int) DB::table('cities')->where('id', (int) $data['city_id'])->value('governorate_id') !== (int) $data['governorate_id']) {
            throw ValidationException::withMessages(['city_id' => __('المدينة ليست من هذه المحافظة.')]);
        }

        $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;

        if ($startsAt !== null && ($startsAt->lt(now()->subMinutes(5)) || $startsAt->gt(now()->addDays(30)))) {
            throw ValidationException::withMessages(['starts_at' => __('اختر موعدًا خلال الثلاثين يومًا القادمة.')]);
        }

        $trade = (string) DB::table('category_children_master')->where('id', (int) $data['trade_child_id'])->value('name_ar');
        $city = (string) DB::table('cities')->where('id', (int) $data['city_id'])->value('name_ar');
        $job = trim((string) $data['details']);

        return [
            'category_child_id' => (int) $data['trade_child_id'],
            'governorate_id' => (int) $data['governorate_id'],
            'city_id' => (int) $data['city_id'],
            // stored once, in Arabic, like every request's title
            'title' => 'أحتاج ' . $trade . ' · ' . $city,
            'details' => $job,
            'params' => ['trade' => ['id' => (int) $data['trade_child_id'], 'name' => $trade], 'city' => $city],
            'starts_at' => $startsAt,
            // what he would like to pay at most; null = he asks for quotes
            'budget' => isset($data['max_price']) ? round((float) $data['max_price'], 2) : null,
            'suggested_price' => null,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ];
    }

    /** The craftsmen of the request's trade in its governorate, the ones in the customer's own city first. */
    public function eligible(ServiceRequest $request, int $limit = 500)
    {
        return User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', (int) $request->category_child_id)
            ->where('governorate_id', (int) $request->governorate_id)
            ->where('id', '!=', (int) $request->requester_id)
            ->live()->whereNull('anonymized_at')->whereNull('requests_paused_at')
            ->orderByRaw('CASE WHEN city_id = ? THEN 0 ELSE 1 END', [(int) $request->city_id])
            ->limit($limit)
            ->get(['id', 'name']);
    }

    public function mayAnswer(ServiceRequest $request, User $business): bool
    {
        return $business->type === User::TYPE_BUSINESS
            && (int) $business->category_child_id === (int) $request->category_child_id
            && in_array((int) $business->category_child_id, $this->tradeIds(), true)
            && (int) $business->governorate_id === (int) $request->governorate_id
            && (int) $business->id !== (int) $request->requester_id
            && $business->banned_at === null && ! $business->isFrozen()
            && $business->anonymized_at === null
            && $business->acceptsRequests();
    }

    /** The visit: an appointment with the craftsman at the time asked (tomorrow at ten when none), at the quoted price. */
    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer): Model
    {
        $start = $request->starts_at ?? now()->addDay()->setTime(10, 0);
        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        return Booking::create([
            'user_id' => (int) $request->requester_id,
            'business_id' => (int) $offer->business_id,
            'service_id' => $serviceId,
            'status' => Booking::STATUS_ACCEPTED,
            'price' => (float) $offer->price,
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
                'display_title' => (string) $request->title,
                'home_service' => $request->params,
                'created_by_user_id' => (int) $request->requester_id,
            ],
        ]);
    }
}
