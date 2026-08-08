<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Image;
use App\Models\MenuItem;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\OfferingDiscovery;
use Illuminate\Http\Request;

/**
 * One shop's window, chosen along its OWN option axes.
 *
 * A hotel already worked: /discovery/units/{business} lists the rooms grouped by
 * kind and the customer taps one. A showroom did not. It sells and it rents, and
 * the two live on different services — selling is a `menu_vehicles` listing,
 * renting is a `booking_stay` row — so «افتح خدمات المعرض» meant meeting the
 * services first and the cars never, when what the customer wants is
 * «SUV — BMW — إيجار» and a price.
 *
 * The answer is not another service, or another layer of item types. The priced
 * row already says what it is: one line option and any number of modifiers, on
 * BOTH surfaces, through `offering_options`. So this reads them as one list and
 * hands back the axes that list actually uses:
 *
 *     نوع المركبة   SUV ٤ · سيدان ٦
 *     ماركات السيارات   BMW ٣ · مرسيدس ٥
 *     نوع التعامل   بيع ٧ · إيجار ٣
 *
 * Nothing here is per-vertical. A hotel gets «نوع الغرفة» and «إطلالة», a clinic
 * «التخصص», a furniture shop «القطعة» and «الطراز» — from the same code, because
 * the axes are read off what the merchant priced rather than declared anywhere.
 *
 * Public: browsing a shop should not require signing in.
 */
final class BusinessOfferingsController extends Controller
{
    public function __construct(private readonly OfferingDiscovery $offerings)
    {
    }

    /** GET /api/v2/discovery/offerings/{business} */
    public function show(Request $request, int $business)
    {
        $data = $request->validate([
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'min:1'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'item_types' => ['nullable', 'array'],
            'item_types.*' => ['string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $biz = User::query()->where('type', User::TYPE_BUSINESS)
            ->find($business, ['id', 'name', 'logo', 'category_id', 'category_child_id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        $optionIds = $this->cleanIds($data['option_ids'] ?? []);
        $itemTypes = array_values(array_filter(
            (array) ($data['item_types'] ?? []),
            fn ($type) => trim((string) $type) !== ''
        ));
        $serviceId = (int) ($data['service_id'] ?? 0);

        $results = $this->offerings->search(
            childId: 0,
            optionIds: $optionIds,
            serviceId: $serviceId,
            itemTypes: $itemTypes,
            perPage: (int) ($data['per_page'] ?? 20),
            businessId: (int) $biz->id
        );

        $rows = collect($results->items());
        $services = $this->servicesOf($rows);
        $units = $this->unitsOf($biz, $rows);
        $galleries = $this->galleriesOf($rows);

        $results->setCollection(
            $rows->map(fn ($row) => $this->payload($row, $services, $units, $galleries))->values()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                    'logo' => $biz->logo,
                    'child_id' => (int) $biz->category_child_id,
                ],
                'query' => [
                    'option_ids' => $optionIds,
                    'service_id' => $serviceId ?: null,
                    'item_types' => $itemTypes,
                ],
                // The axes come back on every call, counted against the CURRENT
                // selection, so the client re-renders its filter rows from one
                // response and never has to know a vertical by name.
                'axes' => $this->offerings->axes(
                    childId: 0,
                    serviceId: $serviceId,
                    itemTypes: $itemTypes,
                    businessId: (int) $biz->id,
                    selected: $optionIds
                ),
                'offerings' => $results,
            ],
        ]);
    }

    /**
     * What the customer does with this row.
     *
     * The deal type is a modifier and modifiers do not route anything — «إيجار»
     * and «بيع» are words on a row, and the SERVICE behind it is what decides
     * whether the next screen picks dates or fills a cart. Saying so on the row
     * is what lets one list carry both without the client guessing.
     *
     * @param  array<int,string>  $services  service id => key
     * @param  array<string,array<int,array<string,mixed>>>  $units
     * @param  array<int,array<int,array<string,mixed>>>  $galleries  menu item id => images
     */
    private function payload($row, array $services, array $units, array $galleries = []): array
    {
        $key = $services[(int) $row->service_id] ?? null;
        $parts = collect([$row->line])->merge($row->modifiers)->filter()
            ->map(fn ($o) => $this->name($o))->filter()->values();

        $payload = [
            'id' => (int) $row->offering_id,
            'source' => $row->source,
            'label' => $parts->implode(' — '),
            'line' => $row->line ? ['id' => (int) $row->line->id, 'name' => $this->name($row->line)] : null,
            'modifiers' => $row->modifiers
                ->map(fn ($m) => ['id' => (int) $m->id, 'name' => $this->name($m)])->values(),
            'price' => (float) $row->price,
            'currency' => $row->currency ?: 'EGP',
            'service_id' => $row->service_id ? (int) $row->service_id : null,
            'service_key' => $key,
            'item_type' => $row->bookable_item_type,
            'image' => $row->image,
            // Only a menu listing owns a gallery; a priced row is a rate, not a
            // thing with photographs.
            'images' => $row->source === 'menu' ? ($galleries[(int) $row->offering_id] ?? []) : [],
            'action' => $key === PlatformService::KEY_BOOKING ? 'book' : 'order',
        ];

        // A rented car is a NAMED car, and the engine refuses the booking
        // without one. The units for exactly this row travel with it, so the
        // customer never has to fetch a second list to find the id.
        if ($payload['action'] === 'book') {
            // Exactly this line first. A business that named its units but not
            // their kind still has to be bookable, so fall back to the kind's
            // whole fleet rather than showing the customer nothing to tap.
            $payload['units'] = $units[$this->unitKey($row->bookable_item_type, $row->line_option_id)]
                ?? $units[$this->unitKey($row->bookable_item_type, 0)]
                ?? [];
        }

        return $payload;
    }

    /**
     * The photos of every menu listing on this page, in one query.
     *
     * @return array<int,array<int,array<string,mixed>>> menu item id => images
     */
    private function galleriesOf($rows): array
    {
        $ids = $rows->where('source', 'menu')->pluck('offering_id')
            ->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];

        $images = Image::query()
            ->where('imageable_type', MenuItem::class)
            ->whereIn('imageable_id', $ids)
            ->orderBy('id')
            ->get(['id', 'image', 'imageable_id']);

        foreach ($images as $image) {
            $out[(int) $image->imageable_id][] = ['id' => (int) $image->id, 'image' => $image->image];
        }

        return $out;
    }

    /** @return array<int,string> service id => key */
    private function servicesOf($rows): array
    {
        $ids = $rows->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return PlatformService::query()->whereIn('id', $ids)
            ->pluck('key', 'id')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    /**
     * The business's bookable units, bucketed by (item type, line option) — the
     * same pair a price row is keyed on, so a row and its units always agree.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function unitsOf(User $biz, $rows): array
    {
        $types = $rows->pluck('bookable_item_type')->filter()->unique()->values();

        if ($types->isEmpty()) {
            return [];
        }

        $units = BookableItem::query()
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->whereIn('item_type', $types)
            ->orderBy('code')
            ->orderBy('id')
            ->get(['id', 'code', 'title', 'capacity', 'item_type', 'line_option_id']);

        $out = [];

        foreach ($units as $unit) {
            $out[$this->unitKey($unit->item_type, $unit->line_option_id)][] = [
                'id' => (int) $unit->id,
                'code' => (string) ($unit->code ?? ''),
                'label' => $unit->displayLabel(),
                'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
            ];
        }

        return $out;
    }

    private function unitKey(?string $itemType, $lineOptionId): string
    {
        return trim((string) $itemType) . ':' . (int) $lineOptionId;
    }

    private function name($option): ?string
    {
        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;
        $name = trim((string) ($primary ?: ($option->name_ar ?: $option->name_en) ?: ''));

        return $name !== '' ? $name : null;
    }

    /** @return array<int,int> */
    private function cleanIds($ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) $ids),
            fn ($id) => $id > 0
        )));
    }
}
