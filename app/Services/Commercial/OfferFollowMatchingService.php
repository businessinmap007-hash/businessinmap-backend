<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\OfferFollow;
use App\Models\OfferFollowNotification;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Database\Eloquent\Builder;

final class OfferFollowMatchingService
{
    public function matchOffer(CommercialOffer $offer): int
    {
        if ((string) $offer->status !== CommercialOffer::STATUS_ACTIVE) {
            return 0;
        }

        $seller = $offer->sellerBusiness()->first(['id', 'type', 'category_id', 'category_child_id']);
        $created = 0;
        $notificationService = app(InAppNotificationService::class);

        OfferFollow::query()
            ->active()
            ->with('user:id,type,category_id,category_child_id')
            ->where(function (Builder $q) use ($offer, $seller) {
                $this->candidateFilters($q, $offer, $seller);
            })
            ->chunkById(100, function ($follows) use ($offer, &$created, $notificationService) {
                foreach ($follows as $follow) {
                    if (! $this->audienceMatches($offer, $follow->user)) {
                        continue;
                    }

                    $score = $this->score($offer, $follow);

                    if ($score <= 0) {
                        continue;
                    }

                    $exists = OfferFollowNotification::query()
                        ->where('follow_id', (int) $follow->id)
                        ->where('offer_id', (int) $offer->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $notification = OfferFollowNotification::query()->create([
                        'user_id' => (int) $follow->user_id,
                        'follow_id' => (int) $follow->id,
                        'offer_id' => (int) $offer->id,
                        'match_type' => (string) $follow->followable_type,
                        'match_score' => $score,
                        'status' => OfferFollowNotification::STATUS_UNREAD,
                        'meta' => [
                            'source' => 'offer_follow_matching',
                            'offerable_type' => $offer->offerable_type,
                            'offerable_id' => (int) $offer->offerable_id,
                            'audience_type' => $offer->audience_type,
                        ],
                    ]);

                    $notificationService->createFromOfferFollowNotification($notification);

                    $follow->forceFill(['last_matched_at' => now()])->save();
                    $created++;
                }
            });

        return $created;
    }

    private function candidateFilters(Builder $query, CommercialOffer $offer, ?User $seller): void
    {
        $query->where(function (Builder $w) use ($offer, $seller) {
            $w->where(function (Builder $x) use ($offer) {
                $x->where('followable_type', (string) $offer->offerable_type)
                    ->where('followable_id', (int) $offer->offerable_id);
            });

            if ((int) $offer->seller_business_id > 0) {
                $w->orWhere(function (Builder $x) use ($offer) {
                    $x->where('followable_type', OfferFollow::FOLLOW_BUSINESS)
                        ->where('followable_id', (int) $offer->seller_business_id);
                });
            }

            if ($seller && (int) $seller->category_child_id > 0) {
                $w->orWhere(function (Builder $x) use ($seller) {
                    $x->where('followable_type', OfferFollow::FOLLOW_CATEGORY_CHILD)
                        ->where('category_child_id', (int) $seller->category_child_id);
                });
            }

            $title = mb_strtolower(trim((string) ($offer->title_ar ?: $offer->title_en)));

            if ($title !== '') {
                $w->orWhere(function (Builder $x) use ($title) {
                    $x->where('followable_type', OfferFollow::FOLLOW_KEYWORD)
                        ->whereNotNull('keyword')
                        ->whereRaw("? LIKE CONCAT('%', LOWER(keyword), '%')", [$title]);
                });
            }
        });
    }

    /**
     * The same test discovery uses, applied one follower at a time.
     *
     * A push that says «٣٠٪ خصم» to somebody the offer was never addressed to
     * has already told him the offer exists — so this cannot be a looser rule
     * than the wall. It is the same object, deliberately: a direction enforced
     * in search and forgotten in notifications is not enforced.
     */
    private function audienceMatches(CommercialOffer $offer, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return app(OfferAudience::class)->canSee($offer, $user);
    }

    private function score(CommercialOffer $offer, OfferFollow $follow): float
    {
        $score = 0.0;

        if ((string) $follow->followable_type === (string) $offer->offerable_type
            && (int) $follow->followable_id === (int) $offer->offerable_id) {
            $score += 1.0;
        }

        if ((string) $follow->followable_type === OfferFollow::FOLLOW_BUSINESS
            && (int) $follow->followable_id === (int) $offer->seller_business_id) {
            $score += 0.8;
        }

        if ((string) $follow->followable_type === OfferFollow::FOLLOW_CATEGORY_CHILD) {
            $seller = $offer->sellerBusiness()->first(['category_child_id']);
            if ($seller && (int) $seller->category_child_id === (int) $follow->category_child_id) {
                $score += 0.6;
            }
        }

        if ((string) $follow->followable_type === OfferFollow::FOLLOW_KEYWORD && $follow->keyword) {
            $needle = mb_strtolower((string) $follow->keyword);
            $title = mb_strtolower((string) ($offer->title_ar ?: $offer->title_en));
            if ($needle !== '' && str_contains($title, $needle)) {
                $score += 0.5;
            }
        }

        if ($follow->min_price !== null && (float) $offer->final_price < (float) $follow->min_price) {
            return 0.0;
        }

        if ($follow->max_price !== null && (float) $offer->final_price > (float) $follow->max_price) {
            return 0.0;
        }

        return min($score, 1.0);
    }
}
