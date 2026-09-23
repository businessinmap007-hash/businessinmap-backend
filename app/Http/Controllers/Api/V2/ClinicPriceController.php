<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A one-call door for the thing a عيادة (one doctor) actually needs: «كشف =
 * ١٠٠، استشارة = ٨٠» — without first discovering service_id/bookable_item_type
 * via GET /business/prices/options, then POSTing the general merchant-pricing
 * shape (line_option_id, modifiers...) that a clinic never uses at all («لا
 * سطر هنا» — see ResolvesOwnerCatalog::sanitizeLineOption). This is a thin,
 * clinic-only convenience over the SAME BusinessServicePrice rows
 * BusinessServicePriceController manages — nothing new is stored.
 */
class ClinicPriceController extends Controller
{
    use ResolvesOwnerCatalog;

    /**
     * GET /api/v2/business/clinic-prices — every visit kind this clinic may
     * price, each with its current price/duration if already set.
     */
    public function index()
    {
        $service = $this->bookingServiceOrFail();
        $itemTypes = $this->allowedTypesByService(collect([$service]))[(int) $service->id] ?? [];

        // business_service_prices.line_option_id is NOT NULL (default 0 —
        // «no line»), unlike bookable_items' own nullable column of the same
        // name — see HasOfferingOptions::lineOptionColumnIsNullable().
        $existing = BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('service_id', $service->id)
            ->where('line_option_id', 0)
            ->get()
            ->keyBy('bookable_item_type');

        return response()->json(['success' => true, 'data' => [
            'kinds' => collect($itemTypes)->map(function (array $type) use ($existing) {
                $row = $existing->get($type['key']);

                return [
                    'key' => $type['key'],
                    'label' => $type['label'],
                    'price' => $row ? (float) $row->price : null,
                    'duration_minutes' => $row?->duration_minutes !== null ? (int) $row->duration_minutes : null,
                    'is_active' => $row ? (bool) $row->is_active : null,
                ];
            })->values(),
        ]]);
    }

    /**
     * PATCH /api/v2/business/clinic-prices — set (or update) several visit
     * kinds' fees in one call. Every key must already be one this clinic may
     * price (index() lists them); an unset kind is left untouched — this
     * never clears a fee the doctor didn't mention.
     *
     * Body: {"prices": {"booking_examination": {"price": 100, "duration_minutes": 20}, ...}}
     */
    public function update(Request $request)
    {
        $service = $this->bookingServiceOrFail();
        $allowedKeys = array_column($this->allowedTypesByService(collect([$service]))[(int) $service->id] ?? [], 'key');

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'prices.*.is_active' => ['nullable', 'boolean'],
        ]);

        $invalid = array_diff(array_keys($data['prices']), $allowedKeys);
        if ($invalid) {
            throw ValidationException::withMessages([
                'prices' => [__('هذه الأنواع غير متاحة لنشاطك: ') . implode('، ', $invalid)],
            ]);
        }

        foreach ($data['prices'] as $itemType => $line) {
            BusinessServicePrice::query()->updateOrCreate(
                [
                    'business_id' => $this->businessId(),
                    'child_id' => $this->childId(),
                    'service_id' => $service->id,
                    'bookable_item_type' => $itemType,
                    'line_option_id' => 0,
                ],
                [
                    'price' => round((float) $line['price'], 2),
                    'duration_minutes' => isset($line['duration_minutes']) ? (int) $line['duration_minutes'] : null,
                    'is_active' => (bool) ($line['is_active'] ?? true),
                    'charge_mode' => BusinessServicePrice::CHARGE_STANDARD,
                    'charge_amount' => 0,
                    'currency' => 'EGP',
                ],
            );
        }

        return $this->index();
    }

    /**
     * Restricted to a doctor's OWN clinic account specifically — not every
     * business that happens to use the `booking` service (a hotel prices a
     * room BY LINE, a rental car by line; only a clinic has none). Widening
     * this to any booking-based child would let one collide a hotel's own
     * named-line rows with a lineless one for the same item type.
     */
    private function bookingServiceOrFail()
    {
        abort_unless($this->childId() === User::DOCTOR_OWN_CLINIC_CHILD_ID, 422, __('هذا متاح لحسابات العيادات فقط.'));

        $service = $this->servicesForChild()->firstWhere('key', 'booking');
        abort_unless($service, 422, __('هذا النشاط لا يقدم خدمة الحجز.'));

        return $service;
    }
}
