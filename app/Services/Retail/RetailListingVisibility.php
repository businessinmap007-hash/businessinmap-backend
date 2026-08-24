<?php

namespace App\Services\Retail;

use App\Models\BusinessCatalogListing;
use App\Models\CatalogListingAudience;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Who may see a retail listing, and its price.
 *
 * «لا يستطيع رؤية هذه المنتجات وأسعارها إلا الشركات التي حددها المصنع» —
 * المالك، 2026-08-23.
 *
 * ── Invisible, not price-hidden ─────────────────────────────────────────────
 *
 * The obvious build is to show the listing and hide the number. That is the
 * wrong shape and it is worth saying why: a factory's product LIST is itself
 * commercial information — who it makes for, what lines it runs, what it has
 * stopped making — and a competitor who can read the catalogue has most of
 * what he wanted before he ever sees a price. So a listing a viewer may not
 * see does not exist for that viewer, in discovery, in filters, in facet
 * counts, and in the cart.
 *
 * ── Applied to the query, not to the results ────────────────────────────────
 *
 * `RetailDiscoveryController` builds six raw queries and groups them for
 * facets. Filtering rows after the fact would leave the COUNTS right and the
 * list wrong, which is worse than either — «١٢ منتجًا» over a page of four.
 * So this composes onto the builder, raw or Eloquent, and the counts come out
 * of the same clause the rows do.
 *
 * ── A guest sees only the shelf ─────────────────────────────────────────────
 *
 * `$viewer = null` means public listings alone. There is no «signed-in
 * businesses see more by default»: a restriction that leaks to everyone with
 * an account is not a restriction.
 */
class RetailListingVisibility
{
    public const PUBLIC = 'public';

    public const RESTRICTED = 'restricted';

    public const MODES = [self::PUBLIC, self::RESTRICTED];

    /**
     * Narrow a query over `business_catalog_listings` to what this viewer may
     * see.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     * @param  string  $table  the alias the listings table carries in THIS query
     */
    public function apply($query, ?User $viewer, string $table = 'business_catalog_listings')
    {
        return $query->where(function ($outer) use ($viewer, $table) {
            $outer->where("{$table}.visibility", self::PUBLIC);

            if (! $viewer) {
                return;
            }

            // Your own listing is always yours to see, whatever it says.
            $outer->orWhere("{$table}.business_id", (int) $viewer->id);

            $outer->orWhereExists(function ($sub) use ($viewer, $table) {
                $sub->selectRaw('1')
                    ->from('catalog_listing_audiences as cla')
                    ->whereColumn('cla.business_catalog_listing_id', "{$table}.id")
                    ->where(function ($match) use ($viewer) {
                        $this->matchAudience($match, $viewer);
                    });
            });
        });
    }

    /** True when this viewer may see this one listing. */
    public function canSee(BusinessCatalogListing $listing, ?User $viewer): bool
    {
        if ((string) $listing->visibility !== self::RESTRICTED) {
            return true;
        }

        if (! $viewer) {
            return false;
        }

        if ((int) $listing->business_id === (int) $viewer->id) {
            return true;
        }

        return CatalogListingAudience::query()
            ->where('business_catalog_listing_id', $listing->id)
            ->where(fn ($match) => $this->matchAudience($match, $viewer))
            ->exists();
    }

    /**
     * The three ways a viewer can be named.
     *
     * `category_child_id` and `category_id` are read off the viewer once —
     * they are what the platform already knows about a business, so a
     * classification audience needs nothing new stored per viewer.
     */
    private function matchAudience($query, User $viewer): void
    {
        $query->where(function ($q) use ($viewer) {
            $q->where('audience_type', CatalogListingAudience::TYPE_BUSINESS)
                ->where('audience_id', (int) $viewer->id);
        });

        if ($viewer->category_child_id) {
            $query->orWhere(function ($q) use ($viewer) {
                $q->where('audience_type', CatalogListingAudience::TYPE_CATEGORY_CHILD)
                    ->where('audience_id', (int) $viewer->category_child_id);
            });
        }

        if ($viewer->category_id) {
            $query->orWhere(function ($q) use ($viewer) {
                $q->where('audience_type', CatalogListingAudience::TYPE_CATEGORY)
                    ->where('audience_id', (int) $viewer->category_id);
            });
        }
    }
}
