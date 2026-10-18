<?php

namespace App\Services\Requests;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestOffer;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The requests-and-offers core: a customer posts a need, the businesses that may answer it are told, each answers with
 * a price, the customer picks one and it becomes the platform's ordinary operation. What a kind of request MEANS lives
 * in its {@see RequestKind}; this class is the same for all of them.
 */
final class ServiceRequestService
{
    public function __construct(private readonly NotificationDispatcherService $notifications) {}

    public function kind(string $key): RequestKind
    {
        return match ($key) {
            ServiceRequest::KIND_RIDE => app(RideRequestKind::class),
            ServiceRequest::KIND_CAR_WANTED => app(CarWantedRequestKind::class),
            default => throw ValidationException::withMessages(['kind' => __('نوع الطلب غير معروف.')]),
        };
    }

    /** The kinds of request this business may answer — by its trade. @return list<string> */
    public function kindsFor(User $business): array
    {
        return collect([ServiceRequest::KIND_RIDE, ServiceRequest::KIND_CAR_WANTED])
            ->filter(fn (string $key) => in_array((int) $business->category_child_id, $this->kind($key)->tradeIds(), true))
            ->values()->all();
    }

    /** @param array<string,mixed> $input */
    public function create(User $requester, string $kind, array $input): ServiceRequest
    {
        $handler = $this->kind($kind);
        $fields = $handler->build($requester, $input);

        $request = ServiceRequest::create($fields + [
            'kind' => $handler->key(),
            'requester_id' => (int) $requester->id,
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        // the businesses that may answer are told, nearest first, up to the limit
        foreach ($handler->eligible($request, (int) config('bim.rides.notify_limit', 50)) as $business) {
            $this->notify('service_request_new', (int) $business->id, (int) $requester->id, $request, 'open_incoming_requests');
        }

        return $request;
    }

    /**
     * A business answers. One live offer per business: answering again replaces its price (the customer is told only
     * when something changed). `accepts_budget` takes the customer's own price as it stands.
     *
     * @param  array{price?:?float, accepts_budget?:?bool, message?:?string, eta_minutes?:?int, menu_item_id?:?int}  $data
     */
    public function offer(ServiceRequest $request, User $business, array $data): ServiceRequestOffer
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['request' => __('هذا الطلب لم يعد يستقبل عروضًا.')]);
        }

        if (! $this->kind($request->kind)->mayAnswer($request, $business)) {
            throw ValidationException::withMessages(['request' => __('هذا الطلب ليس من نشاطك أو نطاقك.')]);
        }

        $acceptsBudget = (bool) ($data['accepts_budget'] ?? false);
        $price = $acceptsBudget ? (float) $request->budget : (float) ($data['price'] ?? 0);

        if ($price < 1 || $price > $this->kind($request->kind)->maxPrice()) {
            throw ValidationException::withMessages(['price' => __('اكتب سعرًا صالحًا.')]);
        }

        // the listing the offer is about must be the offerer's own (a dealer answering with the car he has)
        $itemId = ! empty($data['menu_item_id']) ? (int) $data['menu_item_id'] : null;

        if ($itemId !== null && ! DB::table('menu_items')->where('id', $itemId)->where('business_id', $business->id)->exists()) {
            throw ValidationException::withMessages(['menu_item_id' => __('هذا المنتج ليس من منتجاتك.')]);
        }

        $offer = ServiceRequestOffer::query()
            ->where('service_request_id', $request->id)->where('business_id', $business->id)->first();

        $changed = ! $offer || $offer->status !== ServiceRequestOffer::STATUS_PENDING || (float) $offer->price !== round($price, 2);

        $offer = ServiceRequestOffer::updateOrCreate(
            ['service_request_id' => (int) $request->id, 'business_id' => (int) $business->id],
            [
                'price' => round($price, 2),
                'accepts_budget' => $acceptsBudget,
                'message' => isset($data['message']) ? trim((string) $data['message']) ?: null : null,
                'eta_minutes' => $data['eta_minutes'] ?? null,
                'menu_item_id' => $itemId,
                'status' => ServiceRequestOffer::STATUS_PENDING,
            ]
        );

        if ($changed) {
            $this->notify('service_request_offer', (int) $request->requester_id, (int) $business->id, $request, 'open_service_request');
        }

        return $offer;
    }

    public function withdraw(ServiceRequest $request, User $business): void
    {
        ServiceRequestOffer::query()
            ->where('service_request_id', $request->id)->where('business_id', $business->id)
            ->where('status', ServiceRequestOffer::STATUS_PENDING)
            ->update(['status' => ServiceRequestOffer::STATUS_WITHDRAWN]);
    }

    /** The customer changes the price he asks for; drivers still answer with theirs. */
    public function setBudget(ServiceRequest $request, float $price): ServiceRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['request' => __('هذا الطلب لم يعد مفتوحًا.')]);
        }

        if ($price < 1 || $price > $this->kind($request->kind)->maxPrice()) {
            throw ValidationException::withMessages(['price' => __('اكتب سعرًا صالحًا.')]);
        }

        $request->update(['budget' => round($price, 2)]);

        return $request->refresh();
    }

    /**
     * The customer picks an offer: it becomes the operation, the others are turned down, everyone is told.
     *
     * @return array{request: ServiceRequest, operation: Model}
     */
    public function accept(ServiceRequest $request, ServiceRequestOffer $offer): array
    {
        $handler = $this->kind($request->kind);

        $operation = DB::transaction(function () use ($request, $offer, $handler) {
            $locked = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['request' => __('هذا الطلب لم يعد مفتوحًا.')]);
            }

            $chosen = ServiceRequestOffer::query()
                ->whereKey($offer->id)->where('service_request_id', $locked->id)
                ->where('status', ServiceRequestOffer::STATUS_PENDING)->lockForUpdate()->first();

            if (! $chosen) {
                throw ValidationException::withMessages(['offer' => __('هذا العرض لم يعد متاحًا.')]);
            }

            $operation = $handler->fulfil($locked, $chosen);

            $chosen->update(['status' => ServiceRequestOffer::STATUS_ACCEPTED]);
            ServiceRequestOffer::query()
                ->where('service_request_id', $locked->id)->where('id', '!=', $chosen->id)
                ->where('status', ServiceRequestOffer::STATUS_PENDING)
                ->update(['status' => ServiceRequestOffer::STATUS_REJECTED]);

            $locked->update([
                'status' => ServiceRequest::STATUS_ACCEPTED,
                'accepted_offer_id' => (int) $chosen->id,
                'operation_type' => $operation->getMorphClass(),
                'operation_id' => (int) $operation->getKey(),
            ]);

            return $operation;
        });

        $request->refresh();

        $this->notify('service_request_accepted', (int) $offer->business_id, (int) $request->requester_id, $request, 'open_ride_booking', ['booking_id' => (int) $operation->getKey()]);

        ServiceRequestOffer::query()
            ->where('service_request_id', $request->id)->where('status', ServiceRequestOffer::STATUS_REJECTED)
            ->pluck('business_id')
            ->each(fn ($id) => $this->notify('service_request_closed', (int) $id, (int) $request->requester_id, $request, 'open_incoming_requests'));

        return ['request' => $request, 'operation' => $operation];
    }

    public function cancel(ServiceRequest $request): ServiceRequest
    {
        if ($request->status !== ServiceRequest::STATUS_OPEN) {
            throw ValidationException::withMessages(['request' => __('لا يمكن إلغاء هذا الطلب.')]);
        }

        $request->update(['status' => ServiceRequest::STATUS_CANCELLED]);

        $offerers = ServiceRequestOffer::query()
            ->where('service_request_id', $request->id)->where('status', ServiceRequestOffer::STATUS_PENDING)
            ->pluck('business_id');

        ServiceRequestOffer::query()
            ->where('service_request_id', $request->id)->where('status', ServiceRequestOffer::STATUS_PENDING)
            ->update(['status' => ServiceRequestOffer::STATUS_REJECTED]);

        $offerers->each(fn ($id) => $this->notify('service_request_closed', (int) $id, (int) $request->requester_id, $request, 'open_incoming_requests'));

        return $request->refresh();
    }

    /** @param array<string,mixed> $extra */
    private function notify(string $eventKey, int $recipientId, int $actorId, ServiceRequest $request, string $actionType, array $extra = []): void
    {
        if ($recipientId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, $recipientId, [
                'actor_id' => $actorId > 0 ? $actorId : null,
                'notifiable_type' => ServiceRequest::class,
                'notifiable_id' => (int) $request->id,
                'source_id' => (int) $request->id,
                'service_type' => 'booking',
                'action_type' => $actionType,
                'action_url' => '/service-requests/' . $request->id,
                'body_ar' => $request->title,
                'body_en' => $request->title,
                'meta' => ['kind' => $request->kind, 'title' => $request->title] + $extra,
            ]);
        } catch (\Throwable $e) {
            // best-effort, like every other notification of the platform
        }
    }
}
