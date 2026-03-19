<div class="a2-card a2-card--section js-service-panel" data-service-panel="booking" style="display:none;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">إعدادات Booking</div>
            <div class="a2-card-sub">إعدادات خاصة بخدمة الحجز لهذا التصنيف</div>
        </div>
    </div>

    @php
        $bookingModes = old('booking_modes', $bookingConfig['booking_modes'] ?? []);
        $itemFamily = old('item_family', $bookingConfig['item_family'] ?? '');

        $requiresBookableItem = (string) old('requires_bookable_item', (int) ($bookingConfig['requires_bookable_item'] ?? 1)) === '1';
        $requiresStartEnd = (string) old('requires_start_end', (int) ($bookingConfig['requires_start_end'] ?? 1)) === '1';
        $supportsQuantity = (string) old('supports_quantity', (int) ($bookingConfig['supports_quantity'] ?? 0)) === '1';
        $supportsGuestCount = (string) old('supports_guest_count', (int) ($bookingConfig['supports_guest_count'] ?? 0)) === '1';
        $supportsExtras = (string) old('supports_extras', (int) ($bookingConfig['supports_extras'] ?? 0)) === '1';

        $selectedAllowedItemTypes = old('allowed_item_types', $bookingConfig['allowed_item_types'] ?? []);
        $selectedRequiredFields = old('required_fields', $bookingConfig['required_fields'] ?? []);

        if (!is_array($selectedAllowedItemTypes)) {
            $selectedAllowedItemTypes = [];
        }

        if (!is_array($selectedRequiredFields)) {
            $selectedRequiredFields = [];
        }

        $bookingModeOptions = [
            'daily'       => 'Daily',
            'nightly'     => 'Nightly',
            'slot'        => 'Slot',
            'fixed_event' => 'Fixed Event',
            'fixed'       => 'Fixed',
            'flexible'    => 'Flexible',
        ];

        $itemFamilyOptions = [
            'hotel_room'       => 'Hotel Room',
            'apartment_unit'   => 'Apartment Unit',
            'sports_field'     => 'Sports Field',
            'clinic_slot'      => 'Clinic Slot',
            'hall'             => 'Hall',
            'restaurant_table' => 'Restaurant Table',
        ];

        $itemTypeOptions = [
            'single_room'       => 'Single Room',
            'double_room'       => 'Double Room',
            'suite'             => 'Suite',
            'family_room'       => 'Family Room',
            'apartment'         => 'Apartment',
            'villa'             => 'Villa',
            'five_side_field'   => 'Five Side Field',
            'full_field'        => 'Full Field',
            'padel_court'       => 'Padel Court',
            'consultation_slot' => 'Consultation Slot',
            'followup_slot'     => 'Follow-up Slot',
            'hall_standard'     => 'Standard Hall',
            'hall_vip'          => 'VIP Hall',
            'table_2'           => 'Table 2',
            'table_4'           => 'Table 4',
            'table_6'           => 'Table 6',
            'vip_table'         => 'VIP Table',
        ];

        $requiredFieldOptions = [
            'check_in'         => 'Check In',
            'check_out'        => 'Check Out',
            'starts_at'        => 'Starts At',
            'ends_at'          => 'Ends At',
            'reservation_time' => 'Reservation Time',
            'guests'           => 'Guests',
            'quantity'         => 'Quantity',
            'meal_plan'        => 'Meal Plan',
            'notes'            => 'Notes',
        ];
    @endphp

    <div class="a2-check-section">
        <div class="a2-check-section-title">Booking Modes</div>

        <div class="a2-check-grid a2-check-grid--sm">
            @foreach($bookingModeOptions as $modeValue => $modeLabel)
                <label class="a2-check-card">
                    <input type="checkbox"
                           name="booking_modes[]"
                           value="{{ $modeValue }}"
                           @checked(in_array($modeValue, $bookingModes, true))>
                    <span>{{ $modeLabel }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="a2-form-group" style="margin-top:18px;">
        <label class="a2-label">Item Family</label>
        <select class="a2-select" name="item_family">
            <option value="">اختر النوع العام</option>
            @foreach($itemFamilyOptions as $familyValue => $familyLabel)
                <option value="{{ $familyValue }}" @selected((string) $itemFamily === (string) $familyValue)>
                    {{ $familyLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="a2-check-section" style="margin-top:18px;">
        <div class="a2-check-section-title">Booking Flags</div>

        <div class="a2-flag-grid">
            <label class="a2-check-card">
                <input type="hidden" name="requires_bookable_item" value="0">
                <input type="checkbox" name="requires_bookable_item" value="1" @checked($requiresBookableItem)>
                <span>Requires Bookable Item</span>
            </label>

            <label class="a2-check-card">
                <input type="hidden" name="requires_start_end" value="0">
                <input type="checkbox" name="requires_start_end" value="1" @checked($requiresStartEnd)>
                <span>Requires Start / End</span>
            </label>

            <label class="a2-check-card">
                <input type="hidden" name="supports_quantity" value="0">
                <input type="checkbox" name="supports_quantity" value="1" @checked($supportsQuantity)>
                <span>Supports Quantity</span>
            </label>

            <label class="a2-check-card">
                <input type="hidden" name="supports_guest_count" value="0">
                <input type="checkbox" name="supports_guest_count" value="1" @checked($supportsGuestCount)>
                <span>Supports Guest Count</span>
            </label>

            <label class="a2-check-card">
                <input type="hidden" name="supports_extras" value="0">
                <input type="checkbox" name="supports_extras" value="1" @checked($supportsExtras)>
                <span>Supports Extras</span>
            </label>
        </div>
    </div>

    <div class="a2-booking-config-grid" style="margin-top:18px;">
        <div class="a2-check-section">
            <div class="a2-check-section-title">Allowed Item Types</div>

            <div class="a2-check-grid a2-check-grid--sm">
                @foreach($itemTypeOptions as $itemTypeValue => $itemTypeLabel)
                    <label class="a2-check-card">
                        <input type="checkbox"
                               name="allowed_item_types[]"
                               value="{{ $itemTypeValue }}"
                               @checked(in_array($itemTypeValue, $selectedAllowedItemTypes, true))>
                        <span>{{ $itemTypeLabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="a2-check-section">
            <div class="a2-check-section-title">Required Fields</div>

            <div class="a2-check-grid a2-check-grid--sm">
                @foreach($requiredFieldOptions as $fieldValue => $fieldLabel)
                    <label class="a2-check-card">
                        <input type="checkbox"
                               name="required_fields[]"
                               value="{{ $fieldValue }}"
                               @checked(in_array($fieldValue, $selectedRequiredFields, true))>
                        <span>{{ $fieldLabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>