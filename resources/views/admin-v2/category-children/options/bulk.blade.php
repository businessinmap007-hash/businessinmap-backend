@extends('admin-v2.layouts.master')

@section('title', 'Bulk Category Child Options')
@section('body_class', 'admin-v2 admin-v2-category-child-options-bulk')

@section('content')
@php
    $rootsSafe = collect($roots ?? []);
    $optionGroupsSafe = collect($optionGroups ?? []);
    $ungroupedSafe = collect($ungroupedOptions ?? []);
    $parentIdInt = (int) ($parentId ?? 0);

    $hasUngrouped = $ungroupedSafe->isNotEmpty();

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    // Where a group actually surfaces. Two groups look identical on this screen
    // and behave nothing alike: «الغرف» is what a hotel prices and a customer
    // books, «مرافق الإقامة» only ever narrows a search result.
    $roles = [
        'line' => [
            'label' => __('سطر مُسعَّر'),
            'hint' => __('يظهر للتاجر ليختار منه، ثم يصير بندًا مُسعَّرًا في الحجز أو الطلب'),
            'color' => '#0f766e',
            'bg' => '#ccfbf1',
        ],
        'modifier' => [
            'label' => __('مُعدِّل للسعر'),
            'hint' => __('يُضاف فوق البند ويغيّر سعره، ولا يُباع وحده'),
            'color' => '#92400e',
            'bg' => '#fef3c7',
        ],
        'descriptive' => [
            'label' => __('وصفي'),
            'hint' => __('للبحث والتصفية فقط — لا يحمل سعرًا ولا يدخل الحجز'),
            'color' => '#3730a3',
            'bg' => '#e0e7ff',
        ],
    ];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل خيارات الأقسام الفرعية دفعة واحدة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر التصنيف الرئيسي، ثم الأقسام الفرعية، ثم اختر الخيارات من داخل الجروبات') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                {{ __('رجوع') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.category-child-options.bulk.update') }}" id="bulkOptionsForm">
        @csrf

        <input type="hidden" name="parent_id" id="bulk_parent_id" value="{{ $parentIdInt }}">

        {{-- Root Categories --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">{{ __('التصنيفات الرئيسية') }}</h2>
                    <div class="a2-section-subtitle">{{ __('اضغط على التصنيف لعرض الأقسام الفرعية الخاصة به فقط') }}</div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد تصنيفات رئيسية بها أقسام فرعية.') }}</div>
            @else
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    @foreach($rootsSafe as $root)
                        @php
                            $rootId = (int) $root->id;
                            $isActive = $rootId === $parentIdInt || ($parentIdInt === 0 && $loop->first);
                            $childrenCount = collect($root->children ?? [])->count();
                        @endphp

                        <button
                            type="button"
                            class="a2-btn {{ $isActive ? 'a2-btn-primary' : 'a2-btn-ghost' }} js-root-tab"
                            data-root-id="{{ $rootId }}"
                        >
                            {{ $nameOf($root) }}
                            <span class="a2-badge" style="margin-inline-start:6px;">{{ $childrenCount }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Children --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">{{ __('الأقسام الفرعية') }}</h2>
                    <div class="a2-section-subtitle">{{ __('سيتم تطبيق التعديل على الأقسام المحددة فقط') }}</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">
                        {{ __('تحديد الظاهر') }}
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">
                        {{ __('إلغاء تحديد الظاهر') }}
                    </button>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد أقسام فرعية متاحة.') }}</div>
            @else
                @foreach($rootsSafe as $root)
                    @php
                        $rootId = (int) $root->id;
                        $isActive = $rootId === $parentIdInt || ($parentIdInt === 0 && $loop->first);
                        $children = collect($root->children ?? []);
                    @endphp

                    <div class="js-root-panel"
                         data-root-id="{{ $rootId }}"
                         style="{{ $isActive ? '' : 'display:none;' }}">

                        @if($children->isEmpty())
                            <div class="a2-muted">{{ __('لا توجد أقسام فرعية داخل هذا التصنيف.') }}</div>
                        @else
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                                {{-- The count badge and its click handler are attached in JS. Baked
                                     into the markup they cost ~180 bytes × 364 children on a page that
                                     already ships 900 checkboxes; built once on load they cost nothing. --}}
                                @foreach($children as $child)
                                    <label class="a2-check-card js-child-card" data-child-id="{{ $child->id }}">
                                        <input
                                            type="checkbox"
                                            name="child_ids[]"
                                            value="{{ $child->id }}"
                                            class="js-child-checkbox"
                                            {{ $isActive ? 'checked' : '' }}
                                            {{ $isActive ? '' : 'disabled' }}
                                        >
                                        <span>{{ $nameOf($child) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

            {{-- One child at a time, read-only: what is already written down for it
                 under the open root. Bulk modes answer «what shall they all get»;
                 this answers «what does this one have», which no screen said before. --}}
            <div id="childPeek" class="a2-card a2-card--section a2-mt-12" style="display:none;">
                <div class="a2-card-head" style="display:flex;align-items:center;gap:8px;">
                    <span class="a2-section-title a2-mb-0" id="childPeekTitle"></span>
                    <span class="a2-badge" id="childPeekCount">0</span>
                    <button type="button" class="a2-btn a2-btn-ghost" id="childPeekClose"
                            style="margin-inline-start:auto;">{{ __('إغلاق') }}</button>
                </div>
                <div id="childPeekBody" class="a2-mt-12"></div>
            </div>
        </div>

        {{-- Mode --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <h2 class="a2-section-title">{{ __('طريقة التطبيق') }}</h2>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>{{ __('إضافة فقط') }}</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>{{ __('استبدال بالكامل') }}</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>{{ __('حذف المحدد') }}</span>
                </label>
            </div>
        </div>

        {{-- Option Groups --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">{{ __('جروبات الخيارات') }}</h2>
                    <div class="a2-section-subtitle">{{ __('الجروبات مغلقة — اضغط أي جروب لفتحه وعرض خياراته') }}</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleOptions">
                        {{ __('تحديد خيارات الجروب المفتوح') }}
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleOptions">
                        {{ __('إلغاء تحديد الجروب المفتوح') }}
                    </button>
                </div>
            </div>

            {{-- The legend, and a filter on it. --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                <button type="button" class="a2-btn a2-btn-primary js-role-filter" data-role="">{{ __('الكل') }}</button>
                @foreach($roles as $roleKey => $role)
                    <button type="button" class="a2-btn a2-btn-ghost js-role-filter" data-role="{{ $roleKey }}"
                            title="{{ $role['hint'] }}">
                        <span class="a2-badge" style="background:{{ $role['bg'] }};color:{{ $role['color'] }};">{{ $role['label'] }}</span>
                    </button>
                @endforeach
                <span class="a2-muted" style="font-size:12px;">{{ __('السطر يُسعَّر ويُحجَز · الوصفي يُبحث به فقط') }}</span>
            </div>

            @if($optionGroupsSafe->isEmpty() && !$hasUngrouped)
                <div class="a2-muted">{{ __('لا توجد خيارات متاحة.') }}</div>
            @else
                @foreach($optionGroupsSafe as $group)
                    @php
                        $groupOptions = collect($group->options ?? []);
                        $roleKey = (string) ($group->price_role ?? '');
                        $role = $roles[$roleKey] ?? null;
                    @endphp

                    <details class="a2-card a2-card--section js-option-group-panel"
                             data-role="{{ $roleKey }}"
                             style="margin-bottom:10px;">
                        <summary class="a2-card-head" style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="a2-section-title a2-mb-0">{{ $nameOf($group) }}</span>

                            {{-- «المختار / الإجمالي»: the count the screen never showed, so a
                                 collapsed group hid whether it held one tick or twenty. --}}
                            <span class="a2-badge js-group-count"
                                  data-total="{{ $groupOptions->count() }}">0 / {{ $groupOptions->count() }}</span>

                            @if($role)
                                <span class="a2-badge" title="{{ $role['hint'] }}"
                                      style="background:{{ $role['bg'] }};color:{{ $role['color'] }};">{{ $role['label'] }}</span>
                            @endif

                            <span class="a2-muted js-group-registered" style="font-size:12px;"></span>
                        </summary>

                        @if($role)
                            <div class="a2-muted a2-mt-12" style="font-size:12px;">{{ $role['hint'] }}</div>
                        @endif

                        @if($groupOptions->isEmpty())
                            <div class="a2-muted a2-mt-12">{{ __('لا توجد خيارات داخل هذا الجروب.') }}</div>
                        @else
                            <div class="a2-mt-12" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                                @foreach($groupOptions as $option)
                                    <label class="a2-check-card">
                                        <input
                                            type="checkbox"
                                            name="option_ids[]"
                                            value="{{ $option->id }}"
                                            class="js-option-checkbox"
                                        >
                                        <span>{{ $nameOf($option) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </details>
                @endforeach

                @if($hasUngrouped)
                    <details class="a2-card a2-card--section js-option-group-panel" data-role="" style="margin-bottom:10px;">
                        <summary class="a2-card-head" style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;">
                            <span class="a2-section-title a2-mb-0">{{ __('بدون جروب') }}</span>
                            <span class="a2-badge js-group-count"
                                  data-total="{{ $ungroupedSafe->count() }}">0 / {{ $ungroupedSafe->count() }}</span>
                            <span class="a2-muted js-group-registered" style="font-size:12px;"></span>
                        </summary>
                        <div class="a2-mt-12" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                            @foreach($ungroupedSafe as $option)
                                <label class="a2-check-card">
                                    <input
                                        type="checkbox"
                                        name="option_ids[]"
                                        value="{{ $option->id }}"
                                        class="js-option-checkbox"
                                    >
                                    <span>{{ $nameOf($option) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        </div>

        <div class="a2-card">
            <button type="submit" class="a2-btn a2-btn-primary">
                {{ __('تطبيق التعديل الجماعي') }}
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // child_id => [option ids] under EVERY root, and root => child => extras.
    // A child's real set is the union of the two, and it changes when the root
    // tab changes without a page load — so both halves are here and the union
    // is taken on the spot.
    const CHILD_OPTIONS = @json($childOptionMap ?? ['shared' => [], 'scoped' => []]);
    const OPTION_INDEX = @json($optionIndex ?? []);

    const ROLE_LABELS = @json(collect($roles)->map(fn ($r) => $r['label']));

    /*
    |--------------------------------------------------------------------------
    | Root Tabs
    |--------------------------------------------------------------------------
    */
    const rootTabs = document.querySelectorAll('.js-root-tab');
    const rootPanels = document.querySelectorAll('.js-root-panel');
    const parentInput = document.getElementById('bulk_parent_id');

    function activateRoot(rootId) {
        rootTabs.forEach(function (tab) {
            const active = tab.dataset.rootId === rootId;
            tab.classList.toggle('a2-btn-primary', active);
            tab.classList.toggle('a2-btn-ghost', !active);
        });

        rootPanels.forEach(function (panel) {
            const active = panel.dataset.rootId === rootId;
            panel.style.display = active ? '' : 'none';

            panel.querySelectorAll('.js-child-checkbox').forEach(function (input) {
                input.disabled = !active;
                input.checked = active;
            });
        });

        if (parentInput) {
            parentInput.value = rootId;
        }

        closePeek();
        refreshChildBadges();
        refreshRegistered();
    }

    rootTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateRoot(tab.dataset.rootId);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | What each child already carries
    |--------------------------------------------------------------------------
    */
    function currentRootId() {
        return parentInput ? String(parentInput.value || '0') : '0';
    }

    /** The union the table stores in two halves. */
    function optionsOf(childId) {
        const shared = CHILD_OPTIONS.shared[childId] || [];
        const scopedRoot = CHILD_OPTIONS.scoped[currentRootId()] || {};
        const scoped = scopedRoot[childId] || [];

        return Array.from(new Set(shared.concat(scoped)));
    }

    function selectedChildIds() {
        return Array.from(document.querySelectorAll('.js-child-checkbox:not(:disabled):checked'))
            .map(function (input) { return input.value; });
    }

    /**
     * The count on every child card, so a child nobody has configured is visible
     * as such. The badge doubles as the handle that opens the child's own list.
     */
    const childBadges = new Map();

    function buildChildBadges() {
        document.querySelectorAll('.js-child-card').forEach(function (card) {
            const badge = document.createElement('span');
            badge.className = 'a2-badge';
            badge.style.marginInlineStart = 'auto';
            badge.style.cursor = 'pointer';
            badge.title = @json(__('اعرض الخيارات المسجّلة لهذا القسم'));

            // The badge lives inside a <label>, so a plain click would toggle the
            // checkbox on its way past. Opening the list must not select the child.
            badge.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openPeek(card.dataset.childId, (card.querySelector('span') || {}).textContent || '');
            });

            card.appendChild(badge);
            childBadges.set(card.dataset.childId, badge);
        });
    }

    function refreshChildBadges() {
        childBadges.forEach(function (badge, childId) {
            badge.textContent = String(optionsOf(childId).length);
        });
    }

    const peek = document.getElementById('childPeek');
    const peekTitle = document.getElementById('childPeekTitle');
    const peekCount = document.getElementById('childPeekCount');
    const peekBody = document.getElementById('childPeekBody');

    function closePeek() {
        if (peek) {
            peek.style.display = 'none';
        }
    }

    function openPeek(childId, childName) {
        if (!peek) {
            return;
        }

        const ids = optionsOf(childId);

        peek.style.display = '';
        peekTitle.textContent = childName;
        peekCount.textContent = String(ids.length);
        peekBody.innerHTML = '';

        if (!ids.length) {
            const empty = document.createElement('div');
            empty.className = 'a2-muted';
            empty.textContent = @json(__('لا توجد خيارات مسجّلة لهذا القسم تحت هذا التصنيف.'));
            peekBody.appendChild(empty);
            return;
        }

        // Grouped exactly as the picker below groups them, so «مسجّل» and
        // «سأضيف» can be read against each other without hunting.
        const byGroup = new Map();

        ids.forEach(function (id) {
            const entry = OPTION_INDEX[id];

            if (!entry) {
                return;
            }

            // Keyed by the group NAME, with the role carried in the value. Packing
            // both into one string would need a separator, and a group name has
            // spaces in it — «أثاث وتشطيب منزلي» does not survive a naive split.
            if (!byGroup.has(entry[1])) {
                byGroup.set(entry[1], { role: entry[2] || '', names: [] });
            }

            byGroup.get(entry[1]).names.push(entry[0]);
        });

        Array.from(byGroup.keys()).sort().forEach(function (name) {
            const group = byGroup.get(name);
            const row = document.createElement('div');
            row.style.marginBottom = '8px';

            const head = document.createElement('div');
            head.style.fontWeight = '600';
            head.textContent = name + ' (' + group.names.length + ')';

            if (ROLE_LABELS[group.role]) {
                const chip = document.createElement('span');
                chip.className = 'a2-badge';
                chip.style.marginInlineStart = '6px';
                chip.textContent = ROLE_LABELS[group.role];
                head.appendChild(chip);
            }

            const body = document.createElement('div');
            body.className = 'a2-muted';
            body.textContent = group.names.join('، ');

            row.appendChild(head);
            row.appendChild(body);
            peekBody.appendChild(row);
        });
    }

    const peekClose = document.getElementById('childPeekClose');

    if (peekClose) {
        peekClose.addEventListener('click', closePeek);
    }

    /*
    |--------------------------------------------------------------------------
    | Counts on the collapsed groups
    |--------------------------------------------------------------------------
    */
    /**
     * How many of the SELECTED children already carry each option. Without it a
     * bulk «إضافة» reports nothing about what it changed, and a «حذف المحدد»
     * cannot be aimed.
     */
    // option id => its badge. Created on first use rather than shipped in the
    // markup: 532 hidden spans is a third of this page's weight for a marking
    // most of them will never show.
    const optionBadges = new Map();

    function optionBadgeFor(input) {
        const id = input.value;

        if (optionBadges.has(id)) {
            return optionBadges.get(id);
        }

        const badge = document.createElement('span');
        badge.className = 'a2-badge';
        badge.style.marginInlineStart = 'auto';
        badge.style.display = 'none';
        badge.title = @json(__('عدد الأقسام المحددة التي تحمل هذا الخيار بالفعل'));

        (input.closest('label') || input.parentNode).appendChild(badge);
        optionBadges.set(id, badge);

        return badge;
    }

    function refreshRegistered() {
        const children = selectedChildIds();
        const tally = {};

        children.forEach(function (childId) {
            optionsOf(childId).forEach(function (id) {
                tally[id] = (tally[id] || 0) + 1;
            });
        });

        document.querySelectorAll('.js-option-checkbox').forEach(function (input) {
            const count = tally[input.value] || 0;

            if (!count || !children.length) {
                if (optionBadges.has(input.value)) {
                    optionBadges.get(input.value).style.display = 'none';
                }

                return;
            }

            const badge = optionBadgeFor(input);
            badge.style.display = '';
            badge.textContent = count + ' / ' + children.length;
        });

        refreshGroupCounts();
    }

    function refreshGroupCounts() {
        const childCount = selectedChildIds().length;

        document.querySelectorAll('.js-option-group-panel').forEach(function (panel) {
            const badge = panel.querySelector('.js-group-count');

            if (badge) {
                const checked = panel.querySelectorAll('.js-option-checkbox:checked').length;
                badge.textContent = checked + ' / ' + badge.dataset.total;
            }

            const registered = panel.querySelector('.js-group-registered');

            if (!registered) {
                return;
            }

            const shown = Array.from(panel.querySelectorAll('.js-option-checkbox'))
                .filter(function (input) {
                    const badge = optionBadges.get(input.value);
                    return badge && badge.style.display !== 'none';
                }).length;

            registered.textContent = (childCount && shown)
                ? (@json(__('مسجّل')) + ': ' + shown)
                : '';
        });
    }

    document.addEventListener('change', function (event) {
        if (!event.target.matches) {
            return;
        }

        if (event.target.matches('.js-option-checkbox')) {
            refreshGroupCounts();
        }

        if (event.target.matches('.js-child-checkbox')) {
            refreshRegistered();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Filter the groups by what they DO
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('.js-role-filter').forEach(function (button) {
        button.addEventListener('click', function () {
            const role = button.dataset.role || '';

            document.querySelectorAll('.js-role-filter').forEach(function (other) {
                const active = other === button;
                other.classList.toggle('a2-btn-primary', active);
                other.classList.toggle('a2-btn-ghost', !active);
            });

            document.querySelectorAll('.js-option-group-panel').forEach(function (panel) {
                panel.style.display = (role === '' || panel.dataset.role === role) ? '' : 'none';
            });
        });
    });

    const checkVisibleChildren = document.getElementById('checkVisibleChildren');
    const uncheckVisibleChildren = document.getElementById('uncheckVisibleChildren');

    function visibleChildren() {
        const activePanel = Array.from(rootPanels).find(function (panel) {
            return panel.style.display !== 'none';
        });

        if (!activePanel) {
            return [];
        }

        return activePanel.querySelectorAll('.js-child-checkbox:not(:disabled)');
    }

    if (checkVisibleChildren) {
        checkVisibleChildren.addEventListener('click', function () {
            visibleChildren().forEach(function (input) {
                input.checked = true;
            });
            refreshRegistered();
        });
    }

    if (uncheckVisibleChildren) {
        uncheckVisibleChildren.addEventListener('click', function () {
            visibleChildren().forEach(function (input) {
                input.checked = false;
            });
            refreshRegistered();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Option Group Tabs
    |--------------------------------------------------------------------------
    */
    // Option groups are collapsible <details> (start closed). The group
    // check/uncheck buttons act on the OPEN groups' options.
    const checkVisibleOptions = document.getElementById('checkVisibleOptions');
    const uncheckVisibleOptions = document.getElementById('uncheckVisibleOptions');

    function openGroupOptions() {
        const inputs = [];
        document.querySelectorAll('.js-option-group-panel[open]').forEach(function (panel) {
            panel.querySelectorAll('.js-option-checkbox').forEach(function (input) {
                inputs.push(input);
            });
        });
        return inputs;
    }

    if (checkVisibleOptions) {
        checkVisibleOptions.addEventListener('click', function () {
            openGroupOptions().forEach(function (input) { input.checked = true; });
            refreshGroupCounts();
        });
    }

    if (uncheckVisibleOptions) {
        uncheckVisibleOptions.addEventListener('click', function () {
            openGroupOptions().forEach(function (input) { input.checked = false; });
            refreshGroupCounts();
        });
    }

    buildChildBadges();
    refreshChildBadges();
    refreshRegistered();
});
</script>
@endsection