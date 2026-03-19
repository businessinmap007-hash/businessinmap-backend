<div class="a2-card a2-card--section js-service-panel" data-service-panel="menu" style="display:none;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">إعدادات Menu</div>
            <div class="a2-card-sub">إعدادات خاصة بخدمة المنيو لهذا التصنيف</div>
        </div>
    </div>

    @php
        $menuHasVariants = (string) old('menu_has_variants', (int) ($menuConfig['has_variants'] ?? 0)) === '1';
        $menuHasAddons = (string) old('menu_has_addons', (int) ($menuConfig['has_addons'] ?? 0)) === '1';
        $menuSupportsNotes = (string) old('menu_supports_notes', (int) ($menuConfig['supports_notes'] ?? 0)) === '1';
        $menuSupportsStock = (string) old('menu_supports_stock', (int) ($menuConfig['supports_stock'] ?? 0)) === '1';
    @endphp

    <div class="a2-flag-grid">
        <label class="a2-check-card">
            <input type="hidden" name="menu_has_variants" value="0">
            <input type="checkbox" name="menu_has_variants" value="1" @checked($menuHasVariants)>
            <span>Has Variants</span>
        </label>

        <label class="a2-check-card">
            <input type="hidden" name="menu_has_addons" value="0">
            <input type="checkbox" name="menu_has_addons" value="1" @checked($menuHasAddons)>
            <span>Has Addons</span>
        </label>

        <label class="a2-check-card">
            <input type="hidden" name="menu_supports_notes" value="0">
            <input type="checkbox" name="menu_supports_notes" value="1" @checked($menuSupportsNotes)>
            <span>Supports Notes</span>
        </label>

        <label class="a2-check-card">
            <input type="hidden" name="menu_supports_stock" value="0">
            <input type="checkbox" name="menu_supports_stock" value="1" @checked($menuSupportsStock)>
            <span>Supports Stock</span>
        </label>
    </div>
</div>