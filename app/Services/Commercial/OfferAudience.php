<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferTarget;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Who an offer is addressed to, and who may therefore see it.
 *
 * «العروض التجارية … موجهة» — `commercial_offer_targets` has been written from
 * the admin since July and read by nothing: an offer directed at «شركات مواد
 * البناء» was shown to every passer-by, and the direction was decoration.
 *
 * ── An untargeted offer is open ─────────────────────────────────────────────
 *
 * There is no `visibility` column here and there should not be one: the
 * targets themselves say whether an offer is directed. No audience row means
 * «للجميع», which is what every existing offer is, so switching this on moves
 * nothing that is already published.
 *
 * ── A keyword is not an audience ────────────────────────────────────────────
 *
 * `target_type` carries five values and only four of them NAME a viewer.
 * `keyword` is a search hint — it says what the offer is about, not who it is
 * for — so counting it as an audience would silently hide every offer that
 * merely tagged itself well, from everybody.
 *
 * ── The requested audience narrows, it never widens ─────────────────────────
 *
 * `?audience_type=b2b` used to be honoured as written, so a customer app could
 * ask for the wholesale side and be handed it. The filter is now intersected
 * with what the viewer may see: asking for what is not yours returns nothing
 * rather than everything.
 *
 * ── «خاص» means named-only ──────────────────────────────────────────────────
 *
 * `AUDIENCE_PRIVATE` was visible to nobody at all, which made the fourth
 * audience a dead value. It now means the strict form of this rule: shown only
 * to a viewer it names, never open by default — so an offer marked private
 * with no targets is still an offer addressed to nobody.
 */
class OfferAudience
{
    /**
     * The target kinds that name a viewer.
     *
     * Read twice, and it must be the SAME list both times: once to ask «is
     * this offer directed at all», once to ask «does it name me». A kind
     * counted in the first and missing from the second hides an offer from
     * everyone including the people it was written for.
     */
    public const AUDIENCE_TARGETS = [
        CommercialOfferTarget::TARGET_CATEGORY,
        CommercialOfferTarget::TARGET_CATEGORY_CHILD,
        CommercialOfferTarget::TARGET_BUSINESS,
        CommercialOfferTarget::TARGET_USER_TYPE,
    ];

    /**
     * Who is asking, on a route that does not require a token.
     *
     * Offer discovery is public — a guest browses offers before signing up —
     * so the group carries `api` alone and the default guard is `web`.
     * `$request->user()` therefore returns null for a perfectly valid bearer
     * token, and every signed-in business would be read as a walk-in customer:
     * the direction would work against the only people it was written for.
     */
    public function viewer(Request $request): ?User
    {
        $user = method_exists($request, 'user') ? $request->user() : null;

        return $user ?: auth('sanctum')->user();
    }

    /**
     * The audience values this viewer may be shown.
     *
     * `private` is never in this list — it travels its own branch in `apply()`
     * because it is not open to a class of viewer, only to named ones.
     *
     * @return string[]
     */
    public function visibleAudiences(?User $viewer, ?string $requested = null): array
    {
        $allowed = ($viewer && (string) $viewer->type === 'business')
            ? [CommercialOffer::AUDIENCE_B2B, CommercialOffer::AUDIENCE_BOTH]
            : [CommercialOffer::AUDIENCE_B2C, CommercialOffer::AUDIENCE_BOTH];

        $requested = trim((string) $requested);

        if ($requested === '') {
            return $allowed;
        }

        // A filter narrows. An empty intersection means «none of those are
        // yours», and returns no rows rather than all of them.
        return array_values(array_intersect($allowed, [$requested]));
    }

    /**
     * Narrow a query over `commercial_offers` to what this viewer may see.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string|null  $requested  the caller's `audience_type` filter, if any
     * @param  string  $table  the alias the offers table carries in THIS query
     */
    public function apply($query, ?User $viewer, ?string $requested = null, string $table = 'commercial_offers')
    {
        $allowed = $this->visibleAudiences($viewer, $requested);
        $requested = trim((string) $requested);
        $wantsPrivate = $requested === '' || $requested === CommercialOffer::AUDIENCE_PRIVATE;

        return $query->where(function ($outer) use ($viewer, $allowed, $wantsPrivate, $table) {
            if ($allowed === [] && ! $wantsPrivate) {
                /*
                 * Nothing may match — and this has to be said out loud. An
                 * empty nested closure compiles to no SQL at all, so a
                 * `where(fn () => null)` that meant «show nothing» would show
                 * EVERYTHING, which is the one failure this class exists to
                 * prevent.
                 */
                $outer->whereRaw('1 = 0');

                return;
            }

            if ($allowed !== []) {
                $outer->orWhere(function ($q) use ($allowed, $viewer, $table) {
                    $q->whereIn("{$table}.audience_type", $allowed);
                    $this->targeting($q, $viewer, $table, openWhenUntargeted: true);
                });
            }

            if ($wantsPrivate) {
                $outer->orWhere(function ($q) use ($viewer, $table) {
                    $q->where("{$table}.audience_type", CommercialOffer::AUDIENCE_PRIVATE);
                    $this->targeting($q, $viewer, $table, openWhenUntargeted: false);
                });
            }
        });
    }

    /** True when this viewer may see this one offer. */
    public function canSee(CommercialOffer $offer, ?User $viewer): bool
    {
        $audience = (string) ($offer->audience_type ?: CommercialOffer::AUDIENCE_BOTH);
        $isMine = $viewer && (
            (int) $offer->seller_business_id === (int) $viewer->id
            || (int) $offer->owner_business_id === (int) $viewer->id
        );

        if ($audience === CommercialOffer::AUDIENCE_PRIVATE) {
            return $isMine || $this->namesViewer($offer, $viewer);
        }

        if (! in_array($audience, $this->visibleAudiences($viewer), true)) {
            return false;
        }

        if ($isMine || ! $this->isDirected($offer)) {
            return true;
        }

        return $this->namesViewer($offer, $viewer);
    }

    /** Does this offer name anybody at all? */
    public function isDirected(CommercialOffer $offer): bool
    {
        return CommercialOfferTarget::query()
            ->where('offer_id', (int) $offer->id)
            ->whereIn('target_type', self::AUDIENCE_TARGETS)
            ->exists();
    }

    /** Does this offer name THIS viewer? */
    public function namesViewer(CommercialOffer $offer, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        return CommercialOfferTarget::query()
            ->where('offer_id', (int) $offer->id)
            ->where(fn ($match) => $this->matchTarget($match, $viewer))
            ->exists();
    }

    /**
     * The targeting half of the clause.
     *
     * @param  bool  $openWhenUntargeted  true for the ordinary audiences («no
     *                                    targets means everyone»), false for
     *                                    `private` («no targets means nobody»).
     */
    private function targeting($query, ?User $viewer, string $table, bool $openWhenUntargeted): void
    {
        $query->where(function ($w) use ($viewer, $table, $openWhenUntargeted) {
            if ($openWhenUntargeted) {
                $w->whereNotExists(function ($sub) use ($table) {
                    $this->directedTargets($sub, $table);
                });
            }

            if (! $viewer) {
                if (! $openWhenUntargeted) {
                    // A guest is named by nothing. Say so explicitly, for the
                    // same reason as above: an empty closure means «all».
                    $w->whereRaw('1 = 0');
                }

                return;
            }

            // Your own offer is yours to see, however it is addressed —
            // otherwise a merchant loses sight of what he just published.
            $w->orWhere(function ($mine) use ($viewer, $table) {
                $mine->where("{$table}.seller_business_id", (int) $viewer->id)
                    ->orWhere("{$table}.owner_business_id", (int) $viewer->id);
            });

            $w->orWhereExists(function ($sub) use ($viewer, $table) {
                $this->directedTargets($sub, $table)
                    ->where(function ($match) use ($viewer) {
                        $this->matchTarget($match, $viewer);
                    });
            });
        });
    }

    /** The audience rows of the offer row this query is standing on. */
    private function directedTargets($sub, string $table)
    {
        return $sub->selectRaw('1')
            ->from('commercial_offer_targets as cot')
            ->whereColumn('cot.offer_id', "{$table}.id")
            ->whereIn('cot.target_type', self::AUDIENCE_TARGETS);
    }

    /**
     * The four ways an offer can name a viewer.
     *
     * A business carries one classification — `category_id` and
     * `category_child_id` — so naming a trade needs nothing stored per viewer.
     * `user_type` is the only kind that reads `keyword` rather than
     * `target_id`: it names a KIND of account («كل الشركات»), which has no id.
     */
    private function matchTarget($query, User $viewer): void
    {
        $query->where(function ($q) use ($viewer) {
            $q->where('target_type', CommercialOfferTarget::TARGET_BUSINESS)
                ->where('target_id', (int) $viewer->id);
        });

        if ($viewer->category_child_id) {
            $query->orWhere(function ($q) use ($viewer) {
                $q->where('target_type', CommercialOfferTarget::TARGET_CATEGORY_CHILD)
                    ->where('target_id', (int) $viewer->category_child_id);
            });
        }

        if ($viewer->category_id) {
            $query->orWhere(function ($q) use ($viewer) {
                $q->where('target_type', CommercialOfferTarget::TARGET_CATEGORY)
                    ->where('target_id', (int) $viewer->category_id);
            });
        }

        $query->orWhere(function ($q) use ($viewer) {
            $q->where('target_type', CommercialOfferTarget::TARGET_USER_TYPE)
                ->where('keyword', (string) $viewer->type);
        });
    }
}
