<div class="a2-card a2-card--section js-service-panel" data-service-panel="delivery" style="display:none;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">إعدادات Delivery</div>
            <div class="a2-card-sub">إعدادات خاصة بخدمة التوصيل لهذا التصنيف</div>
        </div>
    </div>

    @php
        $deliveryHasDelivery = (string) old('delivery_has_delivery', (int) ($deliveryConfig['has_delivery'] ?? 1)) === '1';
        $deliveryType = old('delivery_type', $deliveryConfig['delivery_type'] ?? 'distance');
        $deliveryMaxRadiusKm = old('delivery_max_radius_km', $deliveryConfig['max_radius_km'] ?? 0);
        $deliverySupportsScheduled = (string) old('delivery_supports_scheduled', (int) ($deliveryConfig['supports_scheduled_delivery'] ?? 0)) === '1';
    @endphp

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Delivery Type</label>
            <select class="a2-select" name="delivery_type">
                <option value="distance" @selected((string)$deliveryType === 'distance')>Distance</option>
                <option value="zone" @selected((string)$deliveryType === 'zone')>Zone</option>
                <option value="fixed" @selected((string)$deliveryType === 'fixed')>Fixed</option>
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Max Radius (KM)</label>
            <input class="a2-input" type="number" min="0" name="delivery_max_radius_km" value="{{ $deliveryMaxRadiusKm }}">
        </div>
    </div>

    <div class="a2-flag-grid" style="margin-top:18px;">
        <label class="a2-check-card">
            <input type="hidden" name="delivery_has_delivery" value="0">
            <input type="checkbox" name="delivery_has_delivery" value="1" @checked($deliveryHasDelivery)>
            <span>Has Delivery</span>
        </label>

        <label class="a2-check-card">
            <input type="hidden" name="delivery_supports_scheduled" value="0">
            <input type="checkbox" name="delivery_supports_scheduled" value="1" @checked($deliverySupportsScheduled)>
            <span>Scheduled Delivery</span>
        </label>
    </div>
</div>