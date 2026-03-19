<div class="a2-tabs">
    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.edit') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.edit', $item) }}">
        البيانات الأساسية
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.calendar') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.calendar', $item) }}">
        التقويم
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.price-rules.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
        قواعد السعر
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.blocked-slots.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.blocked-slots.index', $item) }}">
        الفترات المحجوبة
    </a>
</div>