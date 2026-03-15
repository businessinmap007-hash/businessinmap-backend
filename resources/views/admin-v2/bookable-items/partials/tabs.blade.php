<div class="a2-tabs">
    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.edit') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.edit', $item) }}">
        General
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.calendar') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.calendar', $item) }}">
        Calendar
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.price-rules.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.price-rules.index', $item) }}">
        Price Rules
    </a>

    <a class="a2-tab {{ request()->routeIs('admin.bookable-items.blocked-slots.*') ? 'is-active' : '' }}"
       href="{{ route('admin.bookable-items.blocked-slots.index', $item) }}">
        Blocked Dates
    </a>
</div>
