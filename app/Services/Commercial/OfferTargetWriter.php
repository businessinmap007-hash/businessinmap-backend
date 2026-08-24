<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferTarget;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The one place that says who an offer is addressed to.
 *
 * Three doors write an offer — the merchant's API, the admin screen, and
 * whatever comes next — and targeting is the third thing they must agree on
 * after price and dates. Kept in one place for the same reason
 * `ListingAudienceWriter` is: the half a screen forgets is invisible until it
 * matters, and here what it forgets is who is allowed to see a price.
 *
 * Read by `OfferAudience`, which must recognise every kind written here.
 */
class OfferTargetWriter
{
    /** The request fields, in the order they are written. */
    private const FIELDS = [
        'target_categories' => CommercialOfferTarget::TARGET_CATEGORY,
        'target_children' => CommercialOfferTarget::TARGET_CATEGORY_CHILD,
        'target_businesses' => CommercialOfferTarget::TARGET_BUSINESS,
    ];

    /**
     * The comma-separated form the panels post.
     *
     * Same shape as the retail audience field, and for the same reason: 1,748
     * businesses do not go into a `<select>`, and the merchant panel has no
     * name lookup of its own yet. A list of ids works today and does not
     * pretend to be a picker; when the picker exists it posts
     * `target_businesses[]` and this stops firing.
     */
    public function normalise(Request $request): void
    {
        if (! $request->has('target_businesses_csv')) {
            return;
        }

        $request->merge([
            'target_businesses' => collect(explode(',', (string) $request->input('target_businesses_csv')))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Normalise, then validate, in that order.
     *
     * The order is the whole point: the panels post a comma-separated string
     * and the rules describe an array, so validating first would reject the
     * only shape a form can send.
     */
    public function vet(Request $request): void
    {
        $this->normalise($request);
        $request->validate($this->rules());
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return [
            'target_categories' => ['nullable', 'array', 'max:50'],
            'target_categories.*' => ['integer', 'exists:categories,id'],

            'target_children' => ['nullable', 'array', 'max:100'],
            'target_children.*' => ['integer', 'exists:category_children_master,id'],

            'target_businesses' => ['nullable', 'array', 'max:500'],
            'target_businesses.*' => ['integer', 'exists:users,id'],

            // «كل الشركات» / «كل العملاء» — a kind of account, which has no id.
            'target_user_types' => ['nullable', 'array', 'max:3'],
            'target_user_types.*' => [Rule::in(['client', 'business'])],
        ];
    }

    /**
     * Replace an offer's audience rows.
     *
     * Delete-and-write rather than a diff: the request states the whole
     * audience, and a merchant who drops a company from the list means it.
     * Call INSIDE the caller's transaction.
     *
     * `keyword` targets are left alone — they are a search hint, not an
     * audience, and this writer does not own them.
     */
    public function sync(CommercialOffer $offer, Request $request): void
    {
        if (! $this->speaks($request)) {
            // A PATCH that says nothing about the audience leaves it alone.
            return;
        }

        CommercialOfferTarget::query()
            ->where('offer_id', (int) $offer->id)
            ->whereIn('target_type', OfferAudience::AUDIENCE_TARGETS)
            ->delete();

        foreach (self::FIELDS as $field => $type) {
            foreach ($this->ids($request, $field) as $id) {
                CommercialOfferTarget::query()->create([
                    'offer_id' => (int) $offer->id,
                    'target_type' => $type,
                    'target_id' => $id,
                ]);
            }
        }

        foreach (array_unique((array) $request->input('target_user_types', [])) as $kind) {
            $kind = trim((string) $kind);

            if ($kind === '') {
                continue;
            }

            CommercialOfferTarget::query()->create([
                'offer_id' => (int) $offer->id,
                'target_type' => CommercialOfferTarget::TARGET_USER_TYPE,
                'target_id' => null,
                'keyword' => $kind,
            ]);
        }
    }

    /** What this offer currently says, in the shape the forms post back. */
    public function current(CommercialOffer $offer): array
    {
        $rows = CommercialOfferTarget::query()->where('offer_id', (int) $offer->id)->get();

        $of = fn (string $type) => $rows->where('target_type', $type)
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'categories' => $of(CommercialOfferTarget::TARGET_CATEGORY),
            'children' => $of(CommercialOfferTarget::TARGET_CATEGORY_CHILD),
            'businesses' => $of(CommercialOfferTarget::TARGET_BUSINESS),
            'user_types' => $rows->where('target_type', CommercialOfferTarget::TARGET_USER_TYPE)
                ->pluck('keyword')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Did this request say anything about the audience?
     *
     * `targets_declared` is the reason a form can CLEAR a direction. An
     * emptied multi-select posts nothing at all, so a screen relying on the
     * fields alone could add companies and never remove the last one — the
     * offer would stay directed at whoever it named first, for good.
     */
    private function speaks(Request $request): bool
    {
        return $request->hasAny(array_merge(
            array_keys(self::FIELDS),
            ['target_user_types', 'targets_declared']
        ));
    }

    /** @return int[] */
    private function ids(Request $request, string $field): array
    {
        return collect((array) $request->input($field, []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
