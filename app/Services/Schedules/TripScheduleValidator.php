<?php

namespace App\Services\Schedules;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\TripSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The rules for publishing/updating a trip leg, shared by the carrier's API
 * (Api\V2\TripScheduleController) and the business panel
 * (Business\TripScheduleController) so the two faces of the same action cannot
 * drift apart: scope decides which geography anchors the leg, origin must
 * differ from destination on that axis, a return leg may only hang off a
 * parent the same carrier owns, and a vehicle class must belong to the
 * scheduling service.
 */
final class TripScheduleValidator
{
    /**
     * @return array<string, mixed> attributes ready for create/update
     */
    public function validated(Request $request, int $businessId, ?TripSchedule $existing = null): array
    {
        $pattern = (string) $request->input('schedule_pattern', $existing->schedule_pattern ?? TripSchedule::PATTERN_WEEKLY);
        $scope = (string) $request->input('scope', $existing->scope ?? TripSchedule::SCOPE_DOMESTIC);
        $isIntl = $scope === TripSchedule::SCOPE_INTERNATIONAL;

        $data = $request->validate([
            'mode' => ['required', Rule::in(TripSchedule::modes())],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:platform_service_item_types,id'],
            'vehicle_label' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', Rule::in(TripSchedule::scopes())],
            // Domestic legs anchor on governorate; international on country.
            'origin_governorate_id' => [Rule::requiredIf(! $isIntl), 'nullable', 'integer', 'exists:governorates,id'],
            'origin_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'destination_governorate_id' => [Rule::requiredIf(! $isIntl), 'nullable', 'integer', 'exists:governorates,id'],
            'destination_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'origin_country_id' => [Rule::requiredIf($isIntl), 'nullable', 'integer', 'exists:countries,id'],
            'destination_country_id' => [Rule::requiredIf($isIntl), 'nullable', 'integer', 'exists:countries,id'],
            'schedule_pattern' => ['required', Rule::in(TripSchedule::patterns())],
            'day_of_week' => [Rule::requiredIf($pattern === TripSchedule::PATTERN_WEEKLY), 'nullable', 'integer', 'between:0,6'],
            'trip_date' => [Rule::requiredIf($pattern === TripSchedule::PATTERN_ONE_OFF), 'nullable', 'date'],
            'departure_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'return_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:24'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'deposit_per_unit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_return_leg' => ['nullable', 'boolean'],
            'parent_trip_id' => ['nullable', 'integer', 'exists:trip_schedules,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in([
                TripSchedule::STATUS_ACTIVE,
                TripSchedule::STATUS_PAUSED,
                TripSchedule::STATUS_EXPIRED,
                TripSchedule::STATUS_CANCELLED,
            ])],
        ]);

        // A return leg may only hang off a parent trip the same business owns.
        if (! empty($data['parent_trip_id'])) {
            $ownsParent = TripSchedule::query()
                ->where('id', (int) $data['parent_trip_id'])
                ->where('business_id', $businessId)
                ->exists();

            if (! $ownsParent) {
                throw ValidationException::withMessages([
                    'parent_trip_id' => __('الرحلة الأصلية غير موجودة أو ليست ملكك.'),
                ]);
            }
        }

        // Origin and destination must differ, on whichever axis anchors the leg.
        if ($isIntl) {
            if (! empty($data['origin_country_id']) && (int) $data['origin_country_id'] === (int) ($data['destination_country_id'] ?? 0)) {
                throw ValidationException::withMessages(['destination_country_id' => __('دولة الوصول يجب أن تختلف عن دولة المصدر.')]);
            }
        } else {
            if (! empty($data['origin_governorate_id']) && (int) $data['origin_governorate_id'] === (int) ($data['destination_governorate_id'] ?? 0)) {
                throw ValidationException::withMessages(['destination_governorate_id' => __('محافظة الوصول يجب أن تختلف عن محافظة المصدر.')]);
            }
        }

        // A vehicle type must belong to the scheduling service (not another one).
        if (! empty($data['vehicle_type_id'])) {
            $serviceId = PlatformService::query()->where('key', PlatformService::KEY_SCHEDULES)->value('id');
            $ok = PlatformServiceItemType::query()
                ->whereKey((int) $data['vehicle_type_id'])
                ->where('platform_service_id', (int) $serviceId)
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages(['vehicle_type_id' => __('نوع المركبة غير صالح لخدمة الجدولة.')]);
            }
        }

        $data['scope'] = $isIntl ? TripSchedule::SCOPE_INTERNATIONAL : TripSchedule::SCOPE_DOMESTIC;
        $data['is_return_leg'] = $request->boolean('is_return_leg', (bool) ($existing->is_return_leg ?? false));
        $data['currency'] = (string) ($data['currency'] ?? ($existing->currency ?? 'EGP'));
        $data['status'] = (string) ($data['status'] ?? ($existing->status ?? TripSchedule::STATUS_ACTIVE));

        return $data;
    }
}
