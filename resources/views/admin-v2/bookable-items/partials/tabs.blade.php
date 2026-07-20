<div class="a2-tabs">
    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.edit') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.edit', $item) }}">
        {{ __('البيانات الأساسية') }}
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.calendar') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.calendar', $item) }}">
        {{ __('التقويم') }}
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.price-rules.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
        {{ __('قواعد السعر') }}
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.blocked-slots.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.blocked-slots.index', $item) }}">
        {{ __('الفترات المحجوبة') }}
    </a>
</div>