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
 * «اطلب عرض سعر» — a job for a WORKSHOP or a FACTORY to make or mend to order: a kitchen in aluminium, a hundred
 * packaging boxes, an iron gate, a marble stair. The customer names the trade, says what he wants (and how many, by
 * when, for how much at most), and the workshops or factories of that trade answer with their price and how many days
 * they need. The one he picks becomes an appointment at the workshop (to see the place, measure, agree the details) at
 * the quoted price.
 *
 * Unlike a home-service request, the maker does not have to be in the customer's city: a factory delivers. The customer
 * says whether it must be a workshop of his own governorate or any in Egypt ("nationwide"); the nearest are told first.
 */
final class WorkQuoteRequestKind implements RequestKind
{
    /** The trades that make or mend to order, by name — the ids are looked up, never hard-coded. */
    private const TRADES = [
        // workshops
        'ورشة أثاث ونجارة', 'ورشة حدادة وخراطة', 'ورشة باب وشباك', 'ورشة صيانة أجهزة', 'ورشة سيارات', 'تبريد وتكييف', 'مركز خدمة متكامل',
        // factories that work to order
        'ألمونتال', 'باب وشباك', 'آثاث', 'مفروشات', 'رخام وجرانيت', 'زجاج', 'طباعة مواد تعبئة وتغليف', 'مواد تعبئة وتغليف',
    ];

    /** A quote is asked for a week. */
    private const TTL_DAYS = 7;

    public function key(): string
    {
        return ServiceRequest::KIND_WORK_QUOTE;
    }

    public function maxPrice(): float
    {
        return 100000000;
    }

    /** @return list<int> */
    public function tradeIds(): array
    {
        return DB::table('category_children_master')->whereIn('name_ar', self::TRADES)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The trades the customer picks from, each with the kind of maker it is.
     *
     * @return list<array{id:int,name:string}>
     */
    public function formOptions(): array
    {
        $english = app()->getLocale() === 'en';

        // a child name can sit under a workshop root AND a factory root: list the row once
        return DB::table('category_children_master')->whereIn('name_ar', self::TRADES)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) ($english ? ($c->name_en ?: $c->name_ar) : ($c->name_ar ?: $c->name_en))])
            ->values()->all();
    }

    public function answerTradesFor(User $business): array
    {
        return in_array((int) $business->category_child_id, $this->tradeIds(), true) ? [(int) $business->category_child_id] : [];
    }

    public function build(User $requester, array $input): array
    {
        $data = Validator::make($input, [
            'trade_child_id' => ['required', 'integer'],
            'what' => ['required', 'string', 'min:3', 'max:120'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'deadline' => ['nullable', 'date'],
            'max_price' => ['nullable', 'numeric', 'min:1', 'max:100000000'],
            'nationwide' => ['nullable', 'boolean'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ])->validate();

        if (! in_array((int) $data['trade_child_id'], $this->tradeIds(), true)) {
            throw ValidationException::withMessages(['trade_child_id' => __('اختر نوع الورشة أو المصنع.')]);
        }

        if (! empty($data['city_id']) && (int) DB::table('cities')->where('id', (int) $data['city_id'])->value('governorate_id') !== (int) $data['governorate_id']) {
            throw ValidationException::withMessages(['city_id' => __('المدينة ليست من هذه المحافظة.')]);
        }

        $deadline = ! empty($data['deadline']) ? Carbon::parse($data['deadline'])->startOfDay() : null;

        if ($deadline !== null && ($deadline->lt(now()->startOfDay()) || $deadline->gt(now()->addDays(365)))) {
            throw ValidationException::withMessages(['deadline' => __('اختر موعد تسليم قادمًا خلال سنة.')]);
        }

        $trade = (string) DB::table('category_children_master')->where('id', (int) $data['trade_child_id'])->value('name_ar');
        $what = trim((string) $data['what']);

        return [
            'category_child_id' => (int) $data['trade_child_id'],
            'governorate_id' => (int) $data['governorate_id'],
            'city_id' => ! empty($data['city_id']) ? (int) $data['city_id'] : null,
            // stored once, in Arabic, like every request's title
            'title' => 'عرض سعر: ' . $what . ' · ' . $trade,
            'details' => trim((string) $data['details']),
            'params' => array_filter([
                'trade' => ['id' => (int) $data['trade_child_id'], 'name' => $trade],
                'what' => $what,
                'quantity' => $data['quantity'] ?? null,
                'unit' => isset($data['unit']) ? (trim((string) $data['unit']) ?: null) : null,
                'deadline' => $deadline?->toDateString(),
                'nationwide' => (bool) ($data['nationwide'] ?? false),
            ], fn ($v) => $v !== null),
            'starts_at' => null,
            'budget' => isset($data['max_price']) ? round((float) $data['max_price'], 2) : null,
            'suggested_price' => null,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ];
    }

    /** The makers of the request's trade: his own governorate (the city first), or every governorate when he asked for it. */
    public function eligible(ServiceRequest $request, int $limit = 500)
    {
        $nationwide = (bool) data_get($request->params, 'nationwide', false);

        return User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', (int) $request->category_child_id)
            ->when(! $nationwide, fn ($q) => $q->where('governorate_id', (int) $request->governorate_id))
            ->where('id', '!=', (int) $request->requester_id)
            ->live()->whereNull('anonymized_at')->whereNull('requests_paused_at')
            ->orderByRaw('CASE WHEN city_id = ? THEN 0 WHEN governorate_id = ? THEN 1 ELSE 2 END', [(int) $request->city_id, (int) $request->governorate_id])
            ->limit($limit)
            ->get(['id', 'name']);
    }

    public function mayAnswer(ServiceRequest $request, User $business): bool
    {
        $nationwide = (bool) data_get($request->params, 'nationwide', false);

        return $business->type === User::TYPE_BUSINESS
            && (int) $business->category_child_id === (int) $request->category_child_id
            && in_array((int) $business->category_child_id, $this->tradeIds(), true)
            && ($nationwide || (int) $business->governorate_id === (int) $request->governorate_id)
            && (int) $business->id !== (int) $request->requester_id
            && $business->banned_at === null && ! $business->isFrozen()
            && $business->anonymized_at === null
            && $business->acceptsRequests();
    }

    /**
     * The first meeting: an appointment at the maker's (tomorrow at eleven) to see the place, measure and agree what
     * the quote covers, at the quoted price. The days the maker said he needs travel in the booking's meta.
     */
    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer, array $choice = []): Model
    {
        $start = now()->addDay()->setTime(11, 0);
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
                'work_quote' => $request->params + ['days' => $offer->eta_minutes !== null ? (int) ceil($offer->eta_minutes / 1440) : null],
                'created_by_user_id' => (int) $request->requester_id,
            ],
        ]);
    }
}
