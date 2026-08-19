<div class="a2-card a2-card--section js-service-panel" data-service-panel="booking" style="display:none;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('إعدادات Booking') }}</div>
            <div class="a2-card-sub">{{ __('إعدادات خاصة بخدمة الحجز لهذا التصنيف') }}</div>
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

        $pattern = \App\Enums\BookingPattern::tryFrom((string) old('booking_pattern', $bookingConfig['booking_pattern'] ?? ''));
        $openPatterns = collect($bookingConfig['booking_patterns'] ?? [])
            ->map(fn ($p) => \App\Enums\BookingPattern::tryFrom((string) $p))
            ->filter()->values();

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
            <option value="">{{ __('اختر النوع العام') }}</option>
            @foreach($itemFamilyOptions as $familyValue => $familyLabel)
                <option value="{{ $familyValue }}" @selected((string) $itemFamily === (string) $familyValue)>
                    {{ $familyLabel }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- ── نمط الحجز ──────────────────────────────────────────────────────
         كانت هنا خمسةُ مربّعات مستقلّة وقائمةُ حقولٍ مطلوبة. على ١٩٤ إعدادًا
         نشطًا كان اثنان من ثمانية مفاتيح مضبوطَين، والستّة الباقية مطفأةً بلا
         استثناءٍ واحد — لأن ثمانية مفاتيح لا تُملأ يدويًا صحيحةً مرّتين.
         النمط يُذكر باسمه فيأتى بشكله كاملًا، ولا يُنسى نصفه. --}}
    <div class="a2-check-section" style="margin-top:18px;">
        <div class="a2-check-section-title">{{ __('نمط الحجز') }}</div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <select name="booking_pattern" class="a2-input">
                    <option value="">{{ __('— بلا نمط —') }}</option>
                    @foreach(\App\Enums\BookingPattern::cases() as $case)
                        <option value="{{ $case->value }}" @selected($pattern === $case)>
                            {{ $case->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($openPatterns->count() > 1)
            <div class="a2-card-sub" style="margin-top:8px;">
                {{ __('الأنماط التى يفتحها هذا التصنيف، ويختار النشاط بينها:') }}
                {{ $openPatterns->map(fn ($p) => $p->label())->implode('، ') }}
            </div>
        @endif

        @if($pattern)
            {{-- نتيجةُ النمط، معروضةً لا محرَّرة: هذه المفاتيح تُشتقّ منه. --}}
            <div class="a2-card-sub" style="margin-top:12px;line-height:2;">
                <strong>{{ __('الوحدة') }}:</strong>
                @switch($pattern->unit())
                    @case(\App\Enums\BookingPattern::UNIT_ALWAYS) {{ __('مطلوبة دائماً') }} @break
                    @case(\App\Enums\BookingPattern::UNIT_NEVER) {{ __('لا وحدة') }} @break
                    @default {{ __('يقرّرها النشاط') }}
                @endswitch
                &nbsp;·&nbsp;
                <strong>{{ __('يُعرض على العميل') }}:</strong>
                @foreach($pattern->asks() as $field)
                    <span class="a2-badge">{{ __('booking.field.' . $field) }}@if(in_array($field, $pattern->requires(), true)) *@endif</span>
                @endforeach
            </div>
            <div class="a2-card-sub" style="margin-top:6px;opacity:.75;">
                {{ __('* ما لا يُقبل الحجز بدونه. وما عداه يشترطه صاحب النشاط من شاشته.') }}
            </div>
        @endif
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

    </div>
</div>