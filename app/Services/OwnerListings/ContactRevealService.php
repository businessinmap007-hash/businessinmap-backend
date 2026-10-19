<?php

namespace App\Services\OwnerListings;

use App\Models\OwnerListingInquiry;
use App\Models\Thread;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\ThreadService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «اتفقنا»: the buyer and the seller of an owner listing have talked in the app and agree. Either asks to swap phone
 * numbers; the other accepts; the platform's fee is taken from the wallets (all from one side, or half each — see
 * `bim.owner_listings.reveal_fee`) and each side then sees the other's number. Until then no number appears anywhere, and the
 * chat refuses one ({@see PhoneNumberGuard}).
 *
 * The fee is a percent of the price between a floor and a ceiling. It is taken only when BOTH sides have said yes, never
 * for a request alone, and never twice (the wallet movement is keyed by the inquiry).
 */
final class ContactRevealService
{
    public const PAYERS = ['seller', 'buyer', 'split'];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformTreasuryService $treasury,
        private readonly ThreadService $threads,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /**
     * What the swap costs and who pays what, for this inquiry.
     *
     * @return array{total:float,split:array<int,float>,payer:string}
     */
    public function quote(OwnerListingInquiry $inquiry): array
    {
        $listing = $inquiry->listing;
        $cfg = (array) config('bim.owner_listings.reveal_fee');
        $price = (float) ($listing->price ?? 0);

        $total = round(max((float) $cfg['min'], min((float) $cfg['max'], $price * (float) $cfg['percent'] / 100)), 2);
        $payer = in_array($cfg['payer'] ?? 'split', self::PAYERS, true) ? (string) $cfg['payer'] : 'split';

        $split = match ($payer) {
            'seller' => [(int) $inquiry->seller_id => $total],
            'buyer' => [(int) $inquiry->buyer_id => $total],
            default => [
                (int) $inquiry->buyer_id => round($total / 2, 2),
                (int) $inquiry->seller_id => round($total - round($total / 2, 2), 2),
            ],
        };

        return ['total' => $total, 'split' => $split, 'payer' => $payer];
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

        $quote = $this->quote($inquiry);

        // a request that cannot be paid would only waste the other side's time: the asker's own share must be there
        $this->assertCanPay($user, $quote['split'][(int) $user->id] ?? 0.0, true);

        $inquiry->update([
            'status' => OwnerListingInquiry::STATUS_REVEAL_REQUESTED,
            'reveal_requested_by' => (int) $user->id,
            'reveal_requested_at' => now(),
        ]);

        $this->say($inquiry, 'طلب ' . $user->name . ' الاتفاق وتبادل أرقام الهاتف. رسوم المنصة ' . $this->feeWords($quote) . ' وتُحصَّل عند موافقة الطرف الآخر.');
        $this->notify('owner_listing_reveal_requested', $this->other($inquiry, $user), (int) $user->id, $inquiry);

        return $inquiry->refresh();
    }

    /** The other party accepts: the fee is taken, the numbers are shown. */
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

        foreach ($quote['split'] as $userId => $share) {
            $this->assertCanPay(User::query()->findOrFail($userId), $share, (int) $userId === (int) $user->id);
        }

        DB::transaction(function () use ($inquiry, $quote) {
            foreach ($quote['split'] as $userId => $share) {
                if ($share <= 0) {
                    continue;
                }

                $key = 'owner_listing_reveal:' . $inquiry->id . ':' . $userId;

                $this->wallets->withdraw(
                    userId: (int) $userId,
                    amount: $share,
                    note: 'رسوم المنصة على إعلان من المالك: تبادل أرقام الهاتف',
                    referenceType: 'owner_listing_reveal',
                    referenceId: (string) $inquiry->id,
                    idempotencyKey: $key,
                    meta: ['context' => 'owner_listing_reveal', 'inquiry_id' => (int) $inquiry->id, 'listing_id' => (int) $inquiry->owner_listing_id]
                );

                // the other half of the movement: without it the fee would leave the payer and reach nobody
                $this->treasury->credit(
                    amount: $share,
                    purpose: PlatformTreasuryService::PURPOSE_FEE,
                    referenceId: (string) $inquiry->id,
                    idempotencyKey: $key . ':treasury',
                    meta: ['inquiry_id' => (int) $inquiry->id, 'context' => 'owner_listing_reveal']
                );
            }

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
            'seller' => $total . ' جنيه على البائع',
            'buyer' => $total . ' جنيه على المشتري',
            default => $total . ' جنيه، نصفها على كل طرف',
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

    private function assertCanPay(User $user, float $share, bool $isActor): void
    {
        if ($share <= 0) {
            return;
        }

        $wallet = $this->wallets->getOrCreateWallet((int) $user->id);

        if ((float) $wallet->balance < $share) {
            throw ValidationException::withMessages(['balance' => $isActor
                ? __('رصيدك لا يكفي لرسوم المنصة (:fee جنيه). اشحن محفظتك أولًا.', ['fee' => rtrim(rtrim(number_format($share, 2, '.', ''), '0'), '.')])
                : __('رصيد الطرف الآخر لا يكفي لرسوم المنصة الآن.'), ]);
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
