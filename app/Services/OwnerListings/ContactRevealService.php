<?php

namespace App\Services\OwnerListings;

use App\Models\OwnerListingInquiry;
use App\Models\Thread;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\ThreadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «اتفقنا»: the buyer and the seller of an owner listing have talked in the app and agree. Either asks to swap phone
 * numbers; the other accepts; the platform's fee — priced for each side in the control panel, event `owner_listing_contact` of
 * {@see PlatformEventFeeService} — is taken from the wallets of the sides that owe one, and each side then sees the
 * other's number. Until then no number appears anywhere, and the chat refuses one ({@see PhoneNumberGuard}).
 *
 * The fee is taken only when BOTH sides have said yes and every side that owes one has the balance, never for a request
 * alone, and never twice (the core keys each charge by the inquiry).
 */
final class ContactRevealService
{
    public function __construct(
        private readonly PlatformEventFeeService $fees,
        private readonly ThreadService $threads,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /**
     * What the swap costs and who pays what, for this inquiry: the control panel's price for the buyer and for the seller (a
     * fixed sum, or a percent of the listing's price kept between a floor and a ceiling).
     *
     * @return array{total:float,split:array<int,float>,payer:string}
     */
    public function quote(OwnerListingInquiry $inquiry): array
    {
        $q = $this->feeQuote($inquiry);
        $buyer = $q['lines']['buyer']['amount'];
        $seller = $q['lines']['seller']['amount'];

        return [
            'total' => $q['total'],
            'split' => [(int) $inquiry->buyer_id => $buyer, (int) $inquiry->seller_id => $seller],
            'payer' => $buyer > 0 && $seller > 0 ? 'split' : ($buyer > 0 ? 'buyer' : ($seller > 0 ? 'seller' : 'none')),
        ];
    }

    /** @return array{total:float,lines:array<string,array{user_id:int,amount:float}>} */
    private function feeQuote(OwnerListingInquiry $inquiry): array
    {
        return $this->fees->quote(PlatformEventFeeService::OWNER_LISTING_CONTACT, $this->parties($inquiry), (float) ($inquiry->listing->price ?? 0));
    }

    /** @return array{buyer:int,seller:int} */
    private function parties(OwnerListingInquiry $inquiry): array
    {
        return ['buyer' => (int) $inquiry->buyer_id, 'seller' => (int) $inquiry->seller_id];
    }

    /** One party says «we agree, let us swap numbers». */
    public function request(OwnerListingInquiry $inquiry, User $user): OwnerListingInquiry
    {
        $this->assertParty($inquiry, $user);
        $this->assertLive($inquiry);

        if ($inquiry->status === OwnerListingInquiry::STATUS_REVEALED) {
            return $inquiry;
        }

        if ($inquiry->status === OwnerListingInquiry::STATUS_REVEAL_REQUESTED) {
            throw ValidationException::withMessages(['inquiry' => __('طلب تبادل الأرقام قائم بالفعل.')]);
        }

        // a request that cannot be paid would only waste the other side's time: the asker's own share must be there
        $this->fees->assertCanPay($this->feeQuote($inquiry), (int) $user->id, onlyActing: true);

        $inquiry->update([
            'status' => OwnerListingInquiry::STATUS_REVEAL_REQUESTED,
            'reveal_requested_by' => (int) $user->id,
            'reveal_requested_at' => now(),
        ]);

        $this->say($inquiry, 'طلب ' . $user->name . ' الاتفاق وتبادل أرقام الهاتف. رسوم المنصة ' . $this->feeWords($this->quote($inquiry)) . '، وتُحصَّل عند موافقة الطرف الآخر.');
        $this->notify('owner_listing_reveal_requested', $this->other($inquiry, $user), (int) $user->id, $inquiry);

        return $inquiry->refresh();
    }

    /** The other party accepts: the fees are taken, the numbers are shown. */
    public function accept(OwnerListingInquiry $inquiry, User $user): OwnerListingInquiry
    {
        $this->assertParty($inquiry, $user);

        if ($inquiry->status === OwnerListingInquiry::STATUS_REVEALED) {
            return $inquiry;
        }

        if ($inquiry->status !== OwnerListingInquiry::STATUS_REVEAL_REQUESTED || (int) $inquiry->reveal_requested_by === (int) $user->id) {
            throw ValidationException::withMessages(['inquiry' => __('لا يوجد طلب تبادل أرقام للموافقة عليه.')]);
        }

        $this->assertLive($inquiry);
        $quote = $this->quote($inquiry);

        // every side that owes a fee must have it before anything is taken
        $this->fees->assertCanPay($this->feeQuote($inquiry), (int) $user->id);

        DB::transaction(function () use ($inquiry, $quote) {
            $this->fees->charge(
                PlatformEventFeeService::OWNER_LISTING_CONTACT,
                $this->parties($inquiry),
                (float) ($inquiry->listing->price ?? 0),
                'owner_listing_inquiry',
                (int) $inquiry->id,
                '1',
                'رسوم المنصة على إعلان من المالك: تبادل أرقام الهاتف'
            );

            $inquiry->update([
                'status' => OwnerListingInquiry::STATUS_REVEALED,
                'revealed_at' => now(),
                'fee_total' => $quote['total'],
                'fee_split' => $quote['split'],
            ]);
        });

        $this->say($inquiry, 'تم الاتفاق وتبادل الأرقام. يظهر رقم كل طرف عند الآخر الآن.');
        $this->notify('owner_listing_revealed', (int) $inquiry->reveal_requested_by, (int) $user->id, $inquiry);

        return $inquiry->refresh();
    }

    /** The other party says not yet, or the asker takes his request back. */
    public function cancel(OwnerListingInquiry $inquiry, User $user): OwnerListingInquiry
    {
        $this->assertParty($inquiry, $user);

        if ($inquiry->status !== OwnerListingInquiry::STATUS_REVEAL_REQUESTED) {
            return $inquiry;
        }

        $asker = (int) $inquiry->reveal_requested_by;
        $inquiry->update(['status' => OwnerListingInquiry::STATUS_OPEN, 'reveal_requested_by' => null, 'reveal_requested_at' => null]);

        $this->say($inquiry, $asker === (int) $user->id ? 'سحب ' . $user->name . ' طلب تبادل الأرقام.' : 'لم يوافق ' . $user->name . ' على تبادل الأرقام بعد. يمكنكما الاستمرار في المحادثة.');

        return $inquiry->refresh();
    }

    /** The number of the OTHER side, once the swap is paid — never before. */
    public function phoneOfOther(OwnerListingInquiry $inquiry, User $viewer): ?string
    {
        if ($inquiry->status !== OwnerListingInquiry::STATUS_REVEALED) {
            return null;
        }

        $otherId = $this->other($inquiry, $viewer);

        return $otherId > 0 ? (User::query()->whereKey($otherId)->value('phone') ?: null) : null;
    }

    /** @param array{total:float,split:array<int,float>,payer:string} $quote */
    private function feeWords(array $quote): string
    {
        $total = rtrim(rtrim(number_format($quote['total'], 2, '.', ''), '0'), '.');

        return match ($quote['payer']) {
            'none' => 'لا رسوم',
            'seller' => $total . ' جنيه على البائع',
            'buyer' => $total . ' جنيه على المشتري',
            default => $total . ' جنيه على الطرفين',
        };
    }

    private function assertParty(OwnerListingInquiry $inquiry, User $user): void
    {
        // 404, not 403: a stranger must not learn the conversation exists
        abort_if(! in_array((int) $user->id, $inquiry->parties(), true), 404);
    }

    private function assertLive(OwnerListingInquiry $inquiry): void
    {
        $listing = $inquiry->listing;

        if (! $listing || $listing->status === 'done' || $listing->status === 'expired') {
            throw ValidationException::withMessages(['inquiry' => __('انتهى هذا الإعلان.')]);
        }
    }

    private function other(OwnerListingInquiry $inquiry, User $user): int
    {
        return (int) $inquiry->buyer_id === (int) $user->id ? (int) $inquiry->seller_id : (int) $inquiry->buyer_id;
    }

    private function say(OwnerListingInquiry $inquiry, string $text): void
    {
        $thread = Thread::query()->where('subject_type', $inquiry->getMorphClass())->where('subject_id', $inquiry->id)->first();

        if ($thread) {
            $this->threads->system($thread, $text);
        }
    }

    private function notify(string $eventKey, int $recipientId, int $actorId, OwnerListingInquiry $inquiry): void
    {
        if ($recipientId <= 0) {
            return;
        }

        try {
            $title = (string) ($inquiry->listing?->title ?? '');
            $this->notifications->dispatch($eventKey, $recipientId, [
                'actor_id' => $actorId,
                'notifiable_type' => OwnerListingInquiry::class,
                'notifiable_id' => (int) $inquiry->id,
                'source_id' => (int) $inquiry->id,
                'service_type' => 'booking',
                'action_type' => 'open_owner_listing_chat',
                'action_url' => '/owner-listing-inquiries/' . $inquiry->id,
                'body_ar' => $title,
                'body_en' => $title,
                'meta' => ['inquiry_id' => (int) $inquiry->id, 'title' => $title],
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
