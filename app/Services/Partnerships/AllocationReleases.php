<?php

namespace App\Services\Partnerships;

use App\Models\AllocationReleaseRequest;
use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BookingHold;
use App\Models\BusinessPartnership;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\Notifications\NotificationDispatcherService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «طلب تحرير غرفة»: rooms the hotel promised a partner (a guaranteed allocation) are not for sale to anyone else. When a guest of the
 * hotel wants one anyway, the partner is asked; if it agrees the room goes back to the hotel's stock (the allocation gives it up) and is
 * held for that guest for a few minutes, so nobody else slips in between the answer and the booking.
 *
 * Only the partners that actually hold unsold rooms of that type are asked, and only they ever see the request.
 */
final class AllocationReleases
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    public function minutes(): int
    {
        return max((int) config('bim.booking_holds.release_request_minutes', 30), 1);
    }

    /** The active guaranteed allocations on this room that still hold unsold rooms. @return Collection<int,BookableAllocation> */
    public function candidates(BookableItem $unit, ?CarbonInterface $start = null, ?CarbonInterface $end = null): Collection
    {
        return BookableAllocation::query()->active()->where('bookable_item_id', $unit->id)->where('allocation_type', BookableAllocation::TYPE_GUARANTEED)
            ->whereHas('partnership', fn ($q) => $q->where('status', BusinessPartnership::STATUS_ACTIVE))
            ->get()->filter(fn (BookableAllocation $a) => ($start && $end ? $a->availableFor($start, $end) : $a->availableQuantity()) > 0)->values();
    }

    /**
     * The guest asks for a room that only a partner's promise stands in the way of.
     *
     * @throws ValidationException when the room is free, taken for another reason, or no partner holds one
     */
    public function request(User $guest, BookableItem $unit, CarbonInterface $start, CarbonInterface $end, int $quantity = 1): AllocationReleaseRequest
    {
        $answer = app(BookableAvailabilityService::class)->check($unit, $start, $end, $quantity, null, true, (int) $guest->id);

        if (($answer['available'] ?? false)) {
            throw ValidationException::withMessages(['bookable_id' => __('الغرفة متاحة الآن، لا حاجة لطلب تحرير.')]);
        }

        if (($answer['code'] ?? '') !== 'held_for_partner') {
            throw ValidationException::withMessages(['bookable_id' => $answer['reason'] ?: __('هذه الوحدة غير متاحة الآن.')]);
        }

        $partners = $this->candidates($unit, $start, $end);

        if ($partners->isEmpty()) {
            throw ValidationException::withMessages(['bookable_id' => __('لا يوجد شريك يمكنه تحرير هذه الغرفة.')]);
        }

        return DB::transaction(function () use ($guest, $unit, $start, $end, $quantity, $partners) {
            $open = AllocationReleaseRequest::query()->where('requester_id', $guest->id)->where('bookable_item_id', $unit->id)
                ->where('status', AllocationReleaseRequest::PENDING)->where('expires_at', '>', now())->first();

            if ($open) {
                return $open;
            }

            $request = AllocationReleaseRequest::create([
                'bookable_item_id' => (int) $unit->id, 'requester_id' => (int) $guest->id, 'seller_business_id' => (int) $unit->business_id,
                'starts_at' => $start, 'ends_at' => $end, 'quantity' => max($quantity, 1), 'status' => AllocationReleaseRequest::PENDING,
                'expires_at' => now()->addMinutes($this->minutes()),
            ]);

            foreach ($partners->pluck('partner_business_id')->unique() as $partnerId) {
                $this->tell((int) $partnerId, $request, 'partnership_update', 'طلب تحرير غرفة من حصتكم', 'A request to release a room from your allocation',
                    'ضيف يطلب ' . $unit->displayLabel() . ' من ' . $start->toDateString() . ' إلى ' . $end->toDateString() . '. وافق لتعود الغرفة إلى الفندق.',
                    'A guest asks for ' . $unit->displayLabel() . ' from ' . $start->toDateString() . ' to ' . $end->toDateString() . '. Agree and the room goes back to the hotel.');
            }

            return $request;
        });
    }

    /** What a partner is waiting to answer: open requests on rooms it holds unsold. @return Collection<int,AllocationReleaseRequest> */
    public function pendingFor(int $partnerBusinessId): Collection
    {
        $mine = BookableAllocation::query()->active()->where('partner_business_id', $partnerBusinessId)->where('allocation_type', BookableAllocation::TYPE_GUARANTEED)
            ->whereHas('partnership', fn ($q) => $q->where('status', BusinessPartnership::STATUS_ACTIVE))->get();

        return AllocationReleaseRequest::query()->with('item.lineOption:id,name_ar,name_en')->whereIn('bookable_item_id', $mine->pluck('bookable_item_id')->unique()->all() ?: [0])
            ->where('status', AllocationReleaseRequest::PENDING)->where('expires_at', '>', now())->orderBy('id')->get()
            // only the requests whose nights this partner still holds unsold rooms on
            ->filter(fn (AllocationReleaseRequest $r) => $mine->contains(fn ($a) => (int) $a->bookable_item_id === (int) $r->bookable_item_id && $a->availableFor($r->starts_at, $r->ends_at) > 0))
            ->values();
    }

    /** The partner agrees: it gives the room(s) back and the guest is held the room. */
    public function approve(int $requestId, int $partnerBusinessId): AllocationReleaseRequest
    {
        return DB::transaction(function () use ($requestId, $partnerBusinessId) {
            $req = AllocationReleaseRequest::query()->lockForUpdate()->findOrFail($requestId);

            if (! $req->isOpen()) {
                throw ValidationException::withMessages(['request' => __('انتهى هذا الطلب أو أُجيب عليه.')]);
            }

            $allocation = BookableAllocation::query()->active()->lockForUpdate()
                ->where('partner_business_id', $partnerBusinessId)->where('bookable_item_id', $req->bookable_item_id)
                ->where('allocation_type', BookableAllocation::TYPE_GUARANTEED)->first();

            if (! $allocation || $allocation->availableFor($req->starts_at, $req->ends_at) < $req->quantity) {
                throw ValidationException::withMessages(['request' => __('لا تملك غرفة غير مباعة من هذا النوع لتحريرها.')]);
            }

            // the room goes back for THESE nights only: the approved request itself is what takes it off the partner's nights
            $req->update(['status' => AllocationReleaseRequest::APPROVED, 'allocation_id' => $allocation->id, 'decided_by' => $partnerBusinessId, 'decided_at' => now()]);

            // the room is the guest's for a few minutes: nobody slips in between this answer and the booking
            BookingHold::query()->where('bookable_item_id', $req->bookable_item_id)->where('user_id', $req->requester_id)->delete();
            BookingHold::query()->create([
                'bookable_item_id' => (int) $req->bookable_item_id, 'user_id' => (int) $req->requester_id, 'seller_business_id' => (int) $req->seller_business_id,
                'starts_at' => $req->starts_at, 'ends_at' => $req->ends_at, 'quantity' => (int) $req->quantity,
                'expires_at' => now()->addMinutes((int) config('bim.booking_holds.minutes', 10)),
            ]);

            $this->tell((int) $req->requester_id, $req, 'room_release_update', 'أصبحت الغرفة متاحة لك', 'The room is available for you',
                'وافق الشريك. الغرفة محجوزة لك مؤقتًا، أكمل حجزك الآن.', 'The partner agreed. The room is held for you, finish your booking now.');

            return $req->fresh();
        });
    }

    public function decline(int $requestId, int $partnerBusinessId): AllocationReleaseRequest
    {
        $req = AllocationReleaseRequest::query()->findOrFail($requestId);

        if (! $req->isOpen()) {
            throw ValidationException::withMessages(['request' => __('انتهى هذا الطلب أو أُجيب عليه.')]);
        }

        $req->update(['status' => AllocationReleaseRequest::DECLINED, 'decided_by' => $partnerBusinessId, 'decided_at' => now()]);

        $this->tell((int) $req->requester_id, $req, 'room_release_update', 'لم تتح الغرفة', 'The room was not released',
            'اعتذر الشريك عن تحرير الغرفة. اختر غرفة أخرى أو جرّب موعدًا آخر.', 'The partner could not release the room. Pick another room or other dates.');

        return $req->fresh();
    }

    /** The status the guest reads while waiting. @return array<string,mixed> */
    public function present(AllocationReleaseRequest $req): array
    {
        $status = $req->status === AllocationReleaseRequest::PENDING && ! $req->expires_at->isFuture() ? AllocationReleaseRequest::EXPIRED : $req->status;

        return [
            'id' => (int) $req->id,
            'status' => $status,
            'bookable_id' => (int) $req->bookable_item_id,
            'starts_at' => $req->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $req->ends_at->format('Y-m-d H:i:s'),
            'quantity' => (int) $req->quantity,
            'expires_at' => $req->expires_at->toIso8601String(),
        ];
    }

    /** What the partner reads: the room and the dates, never who asked. @return array<string,mixed> */
    public function presentForPartner(AllocationReleaseRequest $req): array
    {
        return $this->present($req) + ['room' => $req->item ? (string) $req->item->displayLabel() : null];
    }

    private function tell(int $userId, AllocationReleaseRequest $req, string $eventKey, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => AllocationReleaseRequest::class, 'notifiable_id' => (int) $req->id,
                'source_type' => AllocationReleaseRequest::class, 'source_id' => (int) $req->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
