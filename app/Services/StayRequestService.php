<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\StayRequest;
use App\Models\StayServiceOption;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Validation\ValidationException;

/**
 * «زر ابلاغ عن مشكلة بالغرفة وزر طلب خدمة» — what a hotel guest asks while the stay is running, and the hotel's answer.
 *
 * A request exists only inside a started stay (`in_progress`, a `booking_stay` booking) and only for the guest who
 * holds it. It reaches the hotel with the room number the hotel itself gave the stay — never a booking id.
 */
final class StayRequestService
{
    /** An issue's category → what the hotel reads. Stored in Arabic (the hotel's screen); shown translated to the guest. */
    public const ISSUES = [
        'ac' => 'تكييف',
        'electricity' => 'كهرباء',
        'plumbing' => 'سباكة ومياه',
        'tv_internet' => 'تلفزيون وإنترنت',
        'cleanliness' => 'نظافة',
        'noise' => 'إزعاج',
        'other' => 'أخرى',
    ];

    /** What a guest may order from a hotel that has not written its own list yet. */
    public const DEFAULT_SERVICES = [
        'قهوة', 'شاي', 'فطار', 'تنظيف الغرفة', 'مناشف إضافية', 'وسائد وبطاطين إضافية', 'غسيل وكي', 'خدمة غرف',
    ];

    /** A guest cannot bury the front desk: this many requests may wait at once on one stay. */
    private const MAX_OPEN_PER_BOOKING = 15;

    /** Who may move a request to what. */
    private const TRANSITIONS = [
        StayRequest::STATUS_NEW => [StayRequest::STATUS_IN_PROGRESS, StayRequest::STATUS_DONE, StayRequest::STATUS_CANCELLED],
        StayRequest::STATUS_IN_PROGRESS => [StayRequest::STATUS_DONE, StayRequest::STATUS_CANCELLED],
    ];

    /** @return list<string> the titles the hotel's guests may order */
    public function serviceTitles(int $businessId): array
    {
        $rows = StayServiceOption::query()->where('business_id', $businessId)->orderBy('sort_order')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return self::DEFAULT_SERVICES;
        }

        return $rows->where('is_active', true)->pluck('title')->values()->all();
    }

    /** What the two buttons offer this guest. */
    public function options(Booking $booking): array
    {
        return [
            'can_request' => $this->refusal($booking) === null,
            'issues' => collect(self::ISSUES)->map(fn ($ar, $key) => ['key' => $key, 'label' => __($ar)])->values()->all(),
            'services' => collect($this->serviceTitles((int) $booking->business_id))
                ->map(fn ($title) => ['title' => $title, 'label' => __($title)])->values()->all(),
        ];
    }

    private function refusal(Booking $booking): ?string
    {
        $booking->loadMissing('bookable');
        $type = $booking->bookable?->item_type;

        if ($type !== 'booking_stay') {
            return __('طلبات الإقامة للفنادق فقط.');
        }

        if ($booking->status !== Booking::STATUS_IN_PROGRESS) {
            return __('يمكنك إرسال طلب أثناء الإقامة فقط، بعد أن يبدأها الفندق.');
        }

        return null;
    }

    public function create(Booking $booking, int $guestId, string $kind, ?string $category, ?string $title, ?string $note): StayRequest
    {
        if ((int) $booking->user_id !== $guestId) {
            abort(403, 'Booking does not belong to this client account.');
        }

        if ($message = $this->refusal($booking)) {
            throw ValidationException::withMessages(['booking' => $message]);
        }

        $note = $note !== null ? trim($note) : null;

        if ($kind === StayRequest::KIND_ISSUE) {
            if (! $category || ! isset(self::ISSUES[$category])) {
                throw ValidationException::withMessages(['category' => __('اختر نوع المشكلة.')]);
            }

            if ($category === 'other' && ($note === null || $note === '')) {
                $message = __('صِف المشكلة بكلماتك.');

                throw ValidationException::withMessages(['note' => $message]);
            }

            $title = self::ISSUES[$category];
        } elseif ($kind === StayRequest::KIND_SERVICE) {
            $category = null;
            $title = trim((string) $title);

            if (! in_array($title, $this->serviceTitles((int) $booking->business_id), true)) {
                throw ValidationException::withMessages(['title' => __('هذه الخدمة غير متاحة في هذا الفندق.')]);
            }
        } else {
            throw ValidationException::withMessages(['kind' => __('نوع الطلب غير معروف.')]);
        }

        $open = StayRequest::query()->where('booking_id', $booking->id)->whereIn('status', StayRequest::OPEN)->count();

        if ($open >= self::MAX_OPEN_PER_BOOKING) {
            throw ValidationException::withMessages(['booking' => __('لديك طلبات كثيرة قيد التنفيذ، انتظر حتى ينتهي بعضها.')]);
        }

        $booking->loadMissing('room');

        $request = StayRequest::create([
            'booking_id' => $booking->id,
            'business_id' => (int) $booking->business_id,
            'user_id' => $guestId,
            'room_id' => $booking->room_id,
            'room_number' => $booking->room?->number,
            'kind' => $kind,
            'category' => $category,
            'title' => $title,
            'note' => $note !== '' ? $note : null,
            'status' => StayRequest::STATUS_NEW,
        ]);

        $where = $request->room_number ? 'غرفة '.$request->room_number : 'حجز #'.$booking->id;
        $whereEn = $request->room_number ? 'Room '.$request->room_number : 'Booking #'.$booking->id;
        $isIssue = $kind === StayRequest::KIND_ISSUE;

        $this->notify(
            $booking,
            (int) $booking->business_id,
            'booking.stay_request_created',
            ($isIssue ? 'بلاغ من ' : 'طلب من ').$where,
            ($isIssue ? 'Issue from ' : 'Request from ').$whereEn,
            $title.($request->note ? ' — '.$request->note : ''),
            $title.($request->note ? ' — '.$request->note : ''),
        );

        return $request;
    }

    /** The guest withdraws a request nobody has started yet. */
    public function cancelByGuest(StayRequest $request, int $guestId): StayRequest
    {
        if ((int) $request->user_id !== $guestId) {
            abort(403, 'Request does not belong to this client account.');
        }

        if ($request->status !== StayRequest::STATUS_NEW) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إلغاء طلب بدأ الفندق تنفيذه.')]);
        }

        $request->forceFill(['status' => StayRequest::STATUS_CANCELLED, 'handled_at' => now()])->save();

        return $request;
    }

    /** The hotel moves a request along — and the guest hears about it. */
    public function setStatus(StayRequest $request, int $businessId, string $status): StayRequest
    {
        if ((int) $request->business_id !== $businessId) {
            abort(403, 'Request does not belong to this business account.');
        }

        if (! in_array($status, self::TRANSITIONS[$request->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تغيير حالة هذا الطلب.')]);
        }

        $request->forceFill([
            'status' => $status,
            'handled_at' => in_array($status, [StayRequest::STATUS_DONE, StayRequest::STATUS_CANCELLED], true) ? now() : $request->handled_at,
        ])->save();

        $request->loadMissing('booking');
        [$ar, $en] = match ($status) {
            StayRequest::STATUS_IN_PROGRESS => ['الفندق يعمل على طلبك', 'The hotel is on your request'],
            StayRequest::STATUS_DONE => ['تم تنفيذ طلبك', 'Your request is done'],
            default => ['تعذّر على الفندق تنفيذ طلبك', 'The hotel could not do your request'],
        };

        if ($request->booking) {
            $this->notify($request->booking, (int) $request->user_id, 'booking.stay_request_updated', $ar, $en, $request->title, $request->title);
        }

        return $request;
    }

    /** The shape both the guest and the hotel read. */
    public function payload(StayRequest $request, bool $forHotel = false): array
    {
        $data = [
            'id' => (int) $request->id,
            'booking_id' => (int) $request->booking_id,
            'kind' => $request->kind,
            'category' => $request->category,
            'title' => $request->title,
            'label' => __($request->title),
            'note' => $request->note,
            'status' => $request->status,
            'created_at' => optional($request->created_at)->toIso8601String(),
            'handled_at' => optional($request->handled_at)->toIso8601String(),
        ];

        if ($forHotel) {
            $request->loadMissing('booking.bookable', 'guest');
            $data['room_number'] = $request->room_number;
            $data['unit_title'] = $request->booking?->bookable?->title;
            $data['guest_name'] = $request->guest?->name;
        }

        return $data;
    }

    private function notify(Booking $booking, int $userId, string $event, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            app(NotificationDispatcherService::class)->dispatch($event, $userId, [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => Booking::class,
                'notifiable_id' => (int) $booking->id,
                'source_id' => (int) $booking->id,
                'skip_realtime' => true,
            ]);
        } catch (\Throwable $e) {
            report($e); // a missed notice must never undo the request itself
        }
    }
}
