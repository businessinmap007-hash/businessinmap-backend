<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\BusinessServicePriceResolver;
use Illuminate\Http\Request;

/**
 * The rooms a customer may actually book, and what each costs.
 *
 * 21 category children set `requires_bookable_item` — every hotel child, the
 * restaurants, the pitches, the halls, the pools, the coworking spaces. For
 * those the engine refuses a booking without a NAMED unit, and the client must
 * send `bookable_id`. Nothing told it what those ids were: there was no
 * customer-facing endpoint over `bookable_items` at all, so the booking flow
 * for those 21 could not be completed from the app whatever the business owned.
 *
 * Grouped by KIND rather than listed flat, because that is how the price works
 * and how a customer chooses: «جناح — 1000 — 4 متاحة» and then which suite.
 * Naming the kind only became possible once the unit could carry a line option.
 *
 * Public (no auth) — browsing rooms should not require signing in.
 */
final class UnitDiscoveryController extends Controller
{
    public function __construct(
        private readonly BusinessServicePriceResolver $prices,
        private readonly BookableAvailabilityService $availability
    ) {
    }

    /** GET /api/v2/discovery/units/{business} */
    public function show(Request $request, int $business)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $biz = User::query()->where('type', 'business')
            ->find($business, ['id', 'name', 'logo', 'category_id', 'category_child_id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        // Booking is the default because it is the service that demands a named
        // unit; any other is only honoured if the client asks for it.
        $serviceId = (int) ($data['service_id'] ?? 0)
            ?: (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $units = BookableItem::query()
            ->with('lineOption:id,name_ar,name_en')
            ->where('business_id', $biz->id)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when(! empty($data['item_type']), fn ($query) => $query->where('item_type', $data['item_type']))
            ->orderBy('item_type')
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        // A window is optional: without it the answer is «what exists and what
        // it costs», with it «what is still free». Asking for a price is the
        // common first screen and must not require picking dates first.
        $window = (! empty($data['starts_at']) && ! empty($data['ends_at']))
            ? [$data['starts_at'], $data['ends_at']]
            : null;

        $groups = [];

        foreach ($units->groupBy(fn (BookableItem $unit) => (int) ($unit->line_option_id ?? 0)) as $kindId => $inKind) {
            /** @var \App\Models\BookableItem $first */
            $first = $inKind->first();
            $price = $this->prices->resolveForBookableItem($first);

            $rows = $inKind->map(fn (BookableItem $unit) => $this->unitPayload($unit, $window));

            $groups[] = [
                'line_option_id' => $kindId ?: null,
                'name' => $this->kindName($first),
                'item_type' => (string) ($first->item_type ?? ''),
                'units_count' => $rows->count(),
                'available_count' => $window ? $rows->where('available', true)->count() : null,
                'price' => $price ? round((float) $price->baseUnitPrice(), 2) : null,
                'currency' => $price ? (string) ($price->currency ?: 'EGP') : null,
                // The row the price came from, so a client can send it back as
                // `offering_id` and be priced off exactly what it was shown.
                'offering_id' => $price ? (int) $price->id : null,
                'units' => $rows->values(),
            ];
        }

        // Priced kinds first, then by price: an unpriced kind cannot be sold and
        // is the one the business still has to finish, not the one to lead with.
        usort($groups, function (array $a, array $b) {
            return [$a['price'] === null, $a['price'] ?? 0] <=> [$b['price'] === null, $b['price'] ?? 0];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                ],
                'service_id' => $serviceId ?: null,
                'starts_at' => $window[0] ?? null,
                'ends_at' => $window[1] ?? null,
                'kinds' => $groups,
            ],
        ]);
    }

    /** @param  array{0:string,1:string}|null  $window */
    private function unitPayload(BookableItem $unit, ?array $window): array
    {
        $payload = [
            'id' => (int) $unit->id,
            'code' => (string) ($unit->code ?? ''),
            'title' => $unit->title,
            'label' => $unit->displayLabel(),
            'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
        ];

        if (! $window) {
            return $payload;
        }

        // Reuses the service the engine itself checks with, so a unit shown as
        // free here cannot be refused at booking for a reason this never saw.
        $check = $this->availability->check($unit, $window[0], $window[1]);

        $payload['available'] = (bool) $check['available'];
        $payload['reason'] = $check['reason'];

        return $payload;
    }

    private function kindName(BookableItem $unit): ?string
    {
        $option = $unit->lineOption;

        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '')
            ? $primary
            : (($option->name_ar ?: $option->name_en) ?: null);
    }
}
