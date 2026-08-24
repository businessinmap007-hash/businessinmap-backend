<?php

namespace App\Services\Retail;

use App\Models\BusinessCatalogListing;
use App\Models\CatalogListingAudience;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one place that says who a listing is for, and what it was bought from.
 *
 * «المصنع يحدد الشركات … ويمكنه تحديد محلات بعينها. الشركة تشتري بسعر الجملة
 *  ثم تعيد البيع للمحلات بسعر تحدده هي» — المالك، 2026-08-23.
 *
 * Two doors write a retail listing — the owner panel and the v2 API — and this
 * is the third rule they must agree on after price and stock. Kept in one
 * place for the same reason `ChildServiceWriter` is: the half a screen forgets
 * is invisible until it matters.
 *
 * ── You may only resell what you can see ────────────────────────────────────
 *
 * A company listing a factory's product names the listing it bought from. That
 * is checked, not trusted: the source must exist, be for the SAME catalog
 * product, belong to somebody else, and be visible to this buyer right now.
 * Without the last one, «reselling» would be a way to read a wholesale price —
 * point at it, be refused nothing, and read the number back off your own row.
 */
class ListingAudienceWriter
{
    public function __construct(private readonly RetailListingVisibility $visibility)
    {
    }

    /**
     * Fold the owner panel's comma-separated business ids into the array the
     * rules expect.
     *
     * The panel cannot offer a name picker yet — 1,748 businesses will not go
     * into a `<select>`, and the business panel has no lookup endpoint of its
     * own the way the admin does. A list of ids works today and does not lie
     * about what it is; when the picker exists it posts the same field and this
     * stops firing.
     */
    public function normalise(Request $request): void
    {
        if (! $request->has('audience_business_ids_csv')) {
            return;
        }

        $ids = collect(explode(',', (string) $request->input('audience_business_ids_csv')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $request->merge(['audience_business_ids' => $ids]);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return [
            'visibility' => ['nullable', Rule::in(RetailListingVisibility::MODES)],

            // Who may see it. Empty on a `restricted` listing is refused below
            // — a restriction addressed to nobody hides the row from its own
            // author's customers and reads as a bug.
            'audience_business_ids' => ['nullable', 'array', 'max:500'],
            'audience_business_ids.*' => ['integer', 'exists:users,id'],

            'audience_child_ids' => ['nullable', 'array', 'max:100'],
            'audience_child_ids.*' => ['integer', 'exists:category_children_master,id'],

            'audience_category_ids' => ['nullable', 'array', 'max:50'],
            'audience_category_ids.*' => ['integer', 'exists:categories,id'],

            'source_listing_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * The listing columns this service owns.
     *
     * @param  int  $businessId  the seller
     * @param  int  $productId   the catalog master this listing is for
     * @return array<string,mixed>
     */
    public function columns(Request $request, int $businessId, int $productId): array
    {
        $mode = (string) $request->input('visibility', RetailListingVisibility::PUBLIC);
        $mode = in_array($mode, RetailListingVisibility::MODES, true) ? $mode : RetailListingVisibility::PUBLIC;

        if ($mode === RetailListingVisibility::RESTRICTED && $this->named($request) === []) {
            throw ValidationException::withMessages([
                'visibility' => __('اختر من يرى هذا المنتج — شركة، أو تصنيفًا، أو قسمًا رئيسيًا.'),
            ]);
        }

        return [
            'visibility' => $mode,
            'source_listing_id' => $this->resolveSource($request, $businessId, $productId),
        ];
    }

    /**
     * Replace the audience rows.
     *
     * Delete-and-write rather than a diff: the request states the whole
     * audience, and a merchant who removes a company from the list means it.
     * Called INSIDE the caller's transaction.
     */
    public function sync(BusinessCatalogListing $listing, Request $request): void
    {
        if (! $request->has(['visibility'])
            && ! $request->hasAny(['audience_business_ids', 'audience_child_ids', 'audience_category_ids'])) {
            // A PATCH that says nothing about the audience leaves it alone.
            return;
        }

        CatalogListingAudience::query()
            ->where('business_catalog_listing_id', $listing->id)
            ->delete();

        if (! $listing->isRestricted()) {
            // A public listing keeps no audience: leaving rows behind would
            // make «who is this for» answer two different things.
            return;
        }

        foreach ($this->named($request) as $row) {
            CatalogListingAudience::query()->updateOrCreate(
                [
                    'business_catalog_listing_id' => $listing->id,
                    'audience_type' => $row['type'],
                    'audience_id' => $row['id'],
                ],
                []
            );
        }
    }

    /**
     * Every named audience in the request, flattened.
     *
     * @return array<int,array{type:string,id:int}>
     */
    private function named(Request $request): array
    {
        $map = [
            'audience_business_ids' => CatalogListingAudience::TYPE_BUSINESS,
            'audience_child_ids' => CatalogListingAudience::TYPE_CATEGORY_CHILD,
            'audience_category_ids' => CatalogListingAudience::TYPE_CATEGORY,
        ];

        $out = [];

        foreach ($map as $field => $type) {
            foreach ((array) $request->input($field, []) as $id) {
                $id = (int) $id;

                if ($id > 0) {
                    $out[$type . ':' . $id] = ['type' => $type, 'id' => $id];
                }
            }
        }

        return array_values($out);
    }

    /** The listing this one was bought from, checked rather than trusted. */
    private function resolveSource(Request $request, int $businessId, int $productId): ?int
    {
        $sourceId = (int) $request->input('source_listing_id', 0);

        if ($sourceId <= 0) {
            return null;
        }

        $source = BusinessCatalogListing::query()->find($sourceId);

        if (! $source) {
            throw ValidationException::withMessages([
                'source_listing_id' => __('المنتج المصدر غير موجود.'),
            ]);
        }

        if ((int) $source->business_id === $businessId) {
            throw ValidationException::withMessages([
                'source_listing_id' => __('لا يُعاد بيع منتجك من نفسك.'),
            ]);
        }

        if ((int) $source->catalog_product_id !== $productId) {
            throw ValidationException::withMessages([
                'source_listing_id' => __('المنتج المصدر لمنتج آخر — إعادة البيع تكون لنفس المنتج.'),
            ]);
        }

        $buyer = User::query()->find($businessId);

        if (! $this->visibility->canSee($source, $buyer)) {
            /*
             * The same message as «not found». Telling a stranger «you may not
             * see this» confirms both that the id is a real listing and that it
             * is restricted, which is enough to map a factory's catalogue by
             * counting upwards.
             */
            throw ValidationException::withMessages([
                'source_listing_id' => __('المنتج المصدر غير موجود.'),
            ]);
        }

        return $sourceId;
    }
}
