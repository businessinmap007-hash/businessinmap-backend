<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\OfferingOptionGroupSetting;
use App\Models\User;
use App\Services\BookingAddOnsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «إضافات الحجز» — the meal plans a guest chooses (one choice by default) and the price of a room's own features
 * (a pool view), each priced once for the whole business.
 */
final class BusinessBookingAddOnsController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BookingAddOnsService $addOns)
    {
    }

    private function business(): User
    {
        return $this->actingBusiness() ?: User::findOrFail($this->businessId());
    }

    /** GET /api/v2/business/booking-add-ons */
    public function show()
    {
        return response()->json(['success' => true, 'data' => $this->addOns->payload($this->business())]);
    }

    /**
     * PUT /api/v2/business/booking-add-ons
     * `{add_ons: [{option_id, enabled, value, adjust_type, per_person}], features: [...same], selection_types: {groupId: single|multiple}}`
     */
    public function update(Request $request)
    {
        $rules = [
            'add_ons' => ['nullable', 'array', 'max:200'],
            'features' => ['nullable', 'array', 'max:200'],
            'selection_types' => ['nullable', 'array'],
            'selection_types.*' => ['string', Rule::in(OfferingOptionGroupSetting::SELECTION_TYPES)],
        ];

        foreach (['add_ons', 'features'] as $list) {
            $rules["{$list}.*.option_id"] = ['required', 'integer'];
            $rules["{$list}.*.enabled"] = ['nullable', 'boolean'];
            $rules["{$list}.*.value"] = ['nullable', 'numeric', 'min:0', 'max:1000000'];
            $rules["{$list}.*.adjust_type"] = ['nullable', 'string', 'max:20'];
            $rules["{$list}.*.per_person"] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        return response()->json(['success' => true, 'data' => $this->addOns->save(
            $this->business(),
            $data['add_ons'] ?? [],
            $data['features'] ?? [],
            $data['selection_types'] ?? [],
        )]);
    }
}
