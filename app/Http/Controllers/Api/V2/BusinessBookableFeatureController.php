<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\User;
use App\Services\BookingAddOnsService;
use Illuminate\Http\Request;

/**
 * «من المميزات المضافة للغرفة مباشرة إطلالة على المسبح» — which features THIS room (206) carries. The price of a
 * feature is written once for the whole hotel («إضافات الحجز»); here a room only says it has it, and from then on the
 * room's own price shows the feature added to the room type's price.
 */
final class BusinessBookableFeatureController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BookingAddOnsService $addOns)
    {
    }

    private function item(int $id): BookableItem
    {
        return BookableItem::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function business(): User
    {
        return $this->actingBusiness() ?: User::findOrFail($this->businessId());
    }

    private function payload(BookableItem $item): array
    {
        $selected = $item->modifierOptionIds()->all();
        $prices = $this->business()->currentOfferingAdjustments();

        return ['features' => $this->addOns->featureVocabulary($this->business())->map(fn ($options, $group) => [
            'group' => $group,
            'options' => collect($options)->map(fn ($o) => [
                'id' => (int) $o->id,
                'name' => app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en),
                'selected' => in_array((int) $o->id, $selected, true),
                // what the hotel priced it at — null while no price was written yet
                'price' => isset($prices[(int) $o->id]) ? (float) $prices[(int) $o->id]['value'] : null,
            ])->values()->all(),
        ])->values()->all()];
    }

    /** GET /api/v2/business/bookable-items/{item}/features */
    public function show(int $item)
    {
        return response()->json(['success' => true, 'data' => $this->payload($this->item($item))]);
    }

    /** PUT /api/v2/business/bookable-items/{item}/features — `{option_ids: [853, 854]}` */
    public function update(Request $request, int $item)
    {
        $row = $this->item($item);
        $data = $request->validate([
            'option_ids' => ['nullable', 'array', 'max:100'],
            'option_ids.*' => ['integer'],
        ]);

        $allowed = $this->addOns->featureVocabulary($this->business())->flatten(1)->map(fn ($o) => (int) $o->id);
        $lineOptionId = (int) ($row->line_option_id ?? 0) ?: null;

        $chosen = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $lineOptionId && $allowed->contains($id))
            ->unique()->values()->all();

        // the same call the room editor on the web makes: the line stays, the features are replaced
        $row->syncOfferingOptions($lineOptionId, $chosen);

        return response()->json(['success' => true, 'data' => $this->payload($row->fresh())]);
    }
}
