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

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
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
                                {{-- Nothing is pre-ticked unless the URL asked for it. The screen
                                     used to tick every child of the open root, so the first thing
                                     an admin had to do was untick 68 of them to reach one. --}}
                                @foreach($children as $child)
                                    <label class="a2-check-card js-child-card" data-child-id="{{ $child->id }}">
                                        <input
                                            type="checkbox"
                                            name="child_ids[]"
                                            value="{{ $child->id }}"
                                            class="js-child-checkbox"
                                            {{ $isActive && in_array((int) $child->id, $selectedChildIds, true) ? 'checked' : '' }}
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

            {{-- «سطر به الابن المختار وعلى يمينه زر السابق وعلى اليسار التالى».

                 Walking 63 children by hunting for the next checkbox in a grid
                 is how a pass gets abandoned half-way. One line, two keys: the
                 step unticks the last child and ticks the next, which is what
                 puts the screen in single-child mode and opens its card. --}}
            {{-- Sticky, and the two keys sit AGAINST the name rather than at the
                 far edges: «السابق والتالى يجب ان يكون بجوار الابن المختار».
                 Pinned because a step re-draws the card below it — without the
                 pin the buttons slide out from under the cursor mid-walk. --}}
            <div id="childNav" class="a2-mt-12"
                 style="display:none;align-items:center;justify-content:center;gap:8px;
                        padding:8px 12px;border:1px solid var(--a2-border,#d4d4d8);border-radius:10px;
                        position:sticky;top:0;z-index:5;background:var(--a2-card,#fff);">
                <button type="button" class="a2-btn a2-btn-ghost" id="childPrev">↦ {{ __('السابق') }}</button>

                <span style="font-weight:600;" id="childNavName"></span>
                <span class="a2-badge" id="childNavPos"></span>

                <button type="button" class="a2-btn a2-btn-ghost" id="childNext">{{ __('التالي') }} ↤</button>
            </div>

            {{-- Everything the selection already carries, grouped, with an × on each.
                 Removing here clears the matching box in the picker below, so the
                 card and the picker are two views of one answer — never two lists
                 that can disagree. --}}
            <div id="childPeek" class="a2-card a2-card--section a2-mt-12" style="display:none;">
                <div class="a2-card-head" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="a2-section-title a2-mb-0" id="childPeekTitle"></span>
                    <span class="a2-badge" id="childPeekCount">0</span>
                    <span class="a2-muted" id="childPeekHint" style="font-size:12px;"></span>
                    <button type="button" class="a2-btn a2-btn-ghost" id="childPeekClose"
                            style="margin-inline-start:auto;">{{ __('إغلاق') }}</button>
                </div>
                <div id="childPeekBody" class="a2-mt-12"></div>

                {{-- «واسفل الكارت مباشرة زر حفظ». It saves THIS child and nothing
                     else: it forces replace mode and leaves only this child
                     ticked before submitting, so what the card shows is exactly
                     what the child ends up with — whatever the bulk controls
                     above happen to be set to. --}}
                <div class="a2-mt-12" id="childPeekSaveRow" style="display:none;">
                    <button type="button" class="a2-btn a2-btn-primary" id="childPeekSave">
                        {{ __('حفظ خيارات هذا القسم') }}
                    </button>
                    <span class="a2-muted" style="margin-inline-start:8px;font-size:12px;">
                        {{ __('يحفظ هذا القسم وحده بما هو مؤشَّر الآن.') }}
                    </span>
                </div>
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
                    <input type="radio" name="mode" value="replace" id="modeReplace">
                    <span>{{ __('استبدال بالكامل') }}</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>{{ __('حذف المحدد') }}</span>
                </label>
            </div>

            {{-- «لا يمكننى الغاء ما هو محدد سابقا» — because nothing was ever
                 ticked: the screen asked «what shall they all get», so the
                 registered set was invisible and there was nothing to untick.
                 In «استبدال بالكامل» with ONE child picked, the ticks now START
                 as what that child already carries, and unticking removes. --}}
            <div class="a2-muted a2-mt-12" id="modeHint" style="font-size:12px;"></div>
        </div>

        {{-- Option Groups --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">{{ __('جروبات الخيارات') }}</h2>
                    <div class="a2-section-subtitle">{{ __('لا يظهر أي جروب حتى تضغط اسمه — مجموعات القسم أولًا، والباقي داخل «أخرى»') }}</div>
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

            {{-- «اخفى باقى مجموعات الخيارات الغير مختارة فى زر other … وعند الضغط
                 على اى مجموعة مختارة او مجموعة من Other تظهر فى الاسفل ويظهر فقط
                 ما تم الضغط عليه».

                 Forty open shells said nothing about which of them this child
                 answers. The chips say it: what it carries, then a door to the
                 rest — and one panel at a time below them. --}}
            <div id="groupChipBar" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;"></div>
            <div id="groupOtherBar" style="display:none;gap:6px;flex-wrap:wrap;margin-bottom:12px;
                        padding:8px;border:1px dashed var(--a2-border,#d4d4d8);border-radius:8px;"></div>

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
                             style="margin-bottom:10px;display:none;">
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
                    <details class="a2-card a2-card--section js-option-group-panel" data-role="" style="margin-bottom:10px;display:none;">
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

        {{-- The screen took two whole roots apart on 2026-08-11 — «أنواع الأبواب
             والشبابيك» onto 42 factories, «أنواع الأجهزة الرياضية» onto 69
             companies — each child losing its own trade list in the same write.
             A save that empties a vocabulary on more than five children now
             comes back once and says whose, before it happens. --}}
        @if (session('confirm_wide_withdrawal'))
            @php($warn = session('confirm_wide_withdrawal'))
            <div class="a2-card" style="border:2px solid #b45309;background:#fffbeb;margin-bottom:16px;">
                <h2 class="a2-section-title" style="color:#92400e;">
                    {{ __('هذا الحفظ يُسكِت :count قسمًا', ['count' => $warn['children']]) }}
                </h2>

                <p class="a2-muted" style="margin-bottom:8px;">
                    {{ __('بعد الحفظ لن يستطيع أيٌّ منها أن يذكر شيئًا من هذه المجموعات:') }}
                </p>

                <p style="font-weight:600;margin-bottom:12px;">{{ implode('، ', $warn['groups']) }}</p>

                <p class="a2-muted" style="margin-bottom:12px;">
                    {{ __('راجع الأقسام المحددة وطريقة التطبيق. إن كان هذا مقصودًا، أكّده.') }}
                </p>

                <label class="a2-check-card" style="display:inline-flex;">
                    <input type="checkbox" name="confirm_wide_withdrawal" value="1">
                    <span>{{ __('نعم، اسحب هذه المجموعات من كل الأقسام المحددة') }}</span>
                </label>
            </div>
        @endif

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

    /*
    | Switching root is a change of subject, and three things have to move with
    | it or the screen keeps saying what the LAST root said.
    |
    | «عند التعديل على خيارات الاب سيارات وعمل حفظ والانتقال الى اى اب اخر وعمل
    |  رفرش للصفحة يعود الى سيارات … ولذلك حدثت مشكلة انتقال خيارات الرياضة الى
    |  خيارات زراعية وحيوانية».
    |
    | The tabs switched root in the page and nowhere else. The URL still named
    | the root the last SAVE had redirected to, so F5 — or a browser restoring
    | the tab — silently put the admin back on it while he believed he was
    | somewhere else. Everything he did next was written under a root he was no
    | longer looking at.
    |
    | Two more, both on the line below, and together they are how one submit
    | rewrote a whole root: the switch ticked EVERY child of the root it opened
    | (the markup three lines up promises the opposite — «Nothing is pre-ticked
    | unless the URL asked for it»), and the picker kept the options seeded from
    | the previous root, because seedFromRegistered() returns early when more
    | than one child is in hand and never clears them. Mode is «استبدال بالكامل»
    | by then, since picking a single child puts it there. Root tab, then save,
    | and every child of the new root carries the old root's vocabulary and
    | nothing of its own.
    */
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
                input.checked = false;
            });
        });

        if (parentInput) {
            parentInput.value = rootId;
        }

        // A tick means «this child gets exactly this», and which child that is
        // has just changed. Carrying the marks across is how a vocabulary
        // crosses a root.
        optionBoxes().forEach(function (input) { input.checked = false; });

        rememberRoot(rootId);
        closePeek();
        refreshChildBadges();
        refreshRegistered();
        // The root decides which children are in play AND which scoped options
        // they carry, so the replace-mode seed has to be taken again.
        seedFromRegistered();
    }

    /**
     * Put the open root in the address bar, so a refresh comes back to the root
     * that was on screen rather than to the one the last save redirected to.
     *
     * `replaceState`, not `pushState`: stepping through eight roots must not
     * bury the page the admin arrived from under eight back presses.
     *
     * `child_ids` is dropped rather than rewritten. It names children of the
     * root being LEFT, and a reload that re-ticks them under a different root
     * is the same confusion in a new place — nothing ticked is the honest state
     * after a switch, and the save refuses an empty selection outright.
     */
    function rememberRoot(rootId) {
        writeUrl(function (params) {
            params.set('parent_id', String(rootId));
            params.delete('child_ids');
            params.delete('child_ids[]');
        });
    }

    /**
     * …and which children are in hand, for the same reason: a walk down sixty
     * children is long enough to be interrupted, and coming back to child #1
     * with the marks of child #43 still in the picker is how the wrong child
     * gets written.
     */
    function rememberChildren(childIds) {
        writeUrl(function (params) {
            params.delete('child_ids');
            params.delete('child_ids[]');

            childIds.forEach(function (id) { params.append('child_ids[]', String(id)); });
        });
    }

    function writeUrl(mutate) {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        const url = new URL(window.location.href);

        mutate(url.searchParams);

        window.history.replaceState(window.history.state, '', url.toString());
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
    /*
     * «هناك اسماء قصيره ليس لديها عدد الخيارات وطويله لديها العدد» — owner,
     * 2026-08-16, and the length was a coincidence. What the children with an
     * empty pill have in common is that they stand under MORE THAN ONE ROOT.
     *
     * This screen draws every root's children at once and hides the ones whose
     * root is not open, so a child under four roots is four cards carrying the
     * same `data-child-id`. Keyed by that id in a Map, `set()` kept the last
     * card and dropped the other three — and `refreshChildBadges()` then wrote
     * the number into one badge while three identical pills stayed blank.
     *
     * «ألمونتال» stands under four roots and showed nothing; «مواد غذائية»
     * stands under one and showed 44. Every case in the screenshot was that,
     * and none of them was about the name.
     *
     * A LIST of badges, so every card gets its own.
     */
    const childBadges = [];

    function buildChildBadges() {
        document.querySelectorAll('.js-child-card').forEach(function (card) {
            const badge = document.createElement('span');
            badge.className = 'a2-badge';
            badge.style.marginInlineStart = 'auto';
            badge.style.cursor = 'pointer';

            // The name span is `flex: 1 1 auto` and grows; a plain flex item
            // gives up its own width first. The count is the point of the
            // badge, so it does not shrink.
            badge.style.flex = '0 0 auto';
            badge.title = @json(__('اعرض الخيارات المسجّلة لهذا القسم'));

            // The badge lives inside a <label>, so a plain click would toggle the
            // checkbox on its way past. Opening the list must not select the child.
            badge.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openPeek(card.dataset.childId, (card.querySelector('span') || {}).textContent || '');
            });

            card.appendChild(badge);
            childBadges.push({ childId: card.dataset.childId, badge: badge });
        });
    }

    function refreshChildBadges() {
        childBadges.forEach(function (entry) {
            entry.badge.textContent = String(optionsOf(entry.childId).length);
        });
    }

    const peek = document.getElementById('childPeek');
    const peekTitle = document.getElementById('childPeekTitle');
    const peekCount = document.getElementById('childPeekCount');
    const peekHint = document.getElementById('childPeekHint');
    const peekBody = document.getElementById('childPeekBody');

    function closePeek() {
        if (peek) {
            peek.style.display = 'none';
        }

        peekChildId = null;
        peekChildName = '';
    }

    function openPeek(childId, childName) {
        if (!peek) {
            return;
        }

        // What it carries, PLUS anything ticked in the picker since the card
        // opened — otherwise «افتح المجموعة وحدد منها ما هو مناسب» ticks a box
        // forty groups down and the card, still listing only what is written in
        // the table, appears not to have noticed.
        const ids = Array.from(new Set(
            optionsOf(childId).map(String).concat(removalIsLive() ? checkedOptionIds() : [])
        ));

        peekChildId = childId;
        peekChildName = childName;

        peek.style.display = '';
        peekTitle.textContent = childName;
        peekCount.textContent = String(removalIsLive() ? checkedOptionIds().length : ids.length);
        peekBody.innerHTML = '';

        /*
        | «اريد الخيارت المختارة لكل ابن تظهر فى كارت وبجانبها تشيك بوكس قابل
        |  لالغاء التحديد … وبجانبة اسم مجموعات الخيارات الخاصة به وعند الضغط
        |  عليها تفتح المجموعة واحدد منها ما هو مناسب لهاذا الابن»
        |
        | Two halves, and the second is the one the picker below could never
        | give: forty collapsed groups say nothing about WHICH of them this child
        | answers. The card names them, and clicking one opens it in place.
        |
        | Every checkbox here is a VIEW of the picker's own box — clicking it
        | toggles that box, never a second list. So the card and the picker
        | cannot disagree, and the form still posts exactly one set.
        */
        const removable = removalIsLive();

        if (peekHint) {
            peekHint.textContent = removable
                ? @json(__('ارفع العلامة عمّا لا يناسب هذا القسم، واضغط اسم المجموعة لتفتحها وتختار منها.'))
                : @json(__('للعرض فقط — اختر «استبدال بالكامل» وقسمًا واحدًا حتى يصبح التعديل من هنا ممكنًا.'));
        }

        // The groups this child answers, named up front. A group it carries
        // nothing from still belongs here when it is the child's own axis, but
        // the honest list is what it actually holds — plus a door to the rest.
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
                byGroup.set(entry[1], { role: entry[2] || '', items: [] });
            }

            byGroup.get(entry[1]).items.push({ id: String(id), name: entry[0] });
        });

        // The chips live in the picker card below, not in here: they are how the
        // picker is opened at all now, and they must stay reachable when no
        // child card is open.
        renderGroupChips(Array.from(byGroup.keys()).sort());

        if (!ids.length) {
            const empty = document.createElement('div');
            empty.className = 'a2-muted';
            empty.textContent = @json(__('لا توجد خيارات مسجّلة لهذا القسم تحت هذا التصنيف — افتح مجموعة من الأسفل واختر منها.'));
            peekBody.appendChild(empty);
            return;
        }

        Array.from(byGroup.keys()).sort().forEach(function (name) {
            const group = byGroup.get(name);
            const row = document.createElement('div');
            row.style.marginBottom = '10px';

            const head = document.createElement('div');
            head.style.fontWeight = '600';
            head.style.display = 'flex';
            head.style.alignItems = 'center';
            head.style.gap = '6px';
            head.style.flexWrap = 'wrap';

            const heading = document.createElement('button');
            heading.type = 'button';
            heading.textContent = name + ' (' + group.items.length + ')';
            heading.title = @json(__('افتح هذه المجموعة بالأسفل واختر منها'));
            heading.style.border = '0';
            heading.style.background = 'transparent';
            heading.style.font = 'inherit';
            heading.style.cursor = 'pointer';
            heading.style.textDecoration = 'underline';
            heading.style.padding = '0';
            heading.addEventListener('click', function () { revealGroup(name); });
            head.appendChild(heading);

            if (ROLE_LABELS[group.role]) {
                const chip = document.createElement('span');
                chip.className = 'a2-badge';
                chip.textContent = ROLE_LABELS[group.role];
                head.appendChild(chip);
            }

            const body = document.createElement('div');
            body.style.display = 'grid';
            body.style.gridTemplateColumns = 'repeat(auto-fill,minmax(200px,1fr))';
            body.style.gap = '6px';
            body.style.marginTop = '4px';

            group.items.forEach(function (item) {
                const box = optionBoxById(item.id);

                const label = document.createElement('label');
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.gap = '6px';
                label.style.cursor = removable && box ? 'pointer' : 'default';

                const tick = document.createElement('input');
                tick.type = 'checkbox';
                tick.checked = box ? box.checked : true;
                tick.disabled = !removable || !box;

                const text = document.createElement('span');
                text.textContent = item.name;

                // Cleared in this session: struck through rather than dropped,
                // so «what I removed» stays readable until the save.
                if (!tick.checked) {
                    text.style.textDecoration = 'line-through';
                    text.style.opacity = '0.55';
                }

                if (removable && box) {
                    tick.addEventListener('change', function () {
                        box.checked = tick.checked;
                        redrawing = true;
                        refreshGroupCounts();
                        redrawing = false;
                        openPeek(childId, childName);
                    });
                }

                label.appendChild(tick);
                label.appendChild(text);
                body.appendChild(label);
            });

            row.appendChild(head);
            row.appendChild(body);
            peekBody.appendChild(row);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | The chips: the child's own groups, and a door to the rest
    |--------------------------------------------------------------------------
    | Nothing below is drawn until one of these is pressed, and pressing one
    | closes whatever was open. Forty shells at once is how «أنواع الأبواب
    | والشبابيك» ended up ticked on a root of factories — the axis being written
    | was never in view.
    */
    const chipBar = document.getElementById('groupChipBar');
    const otherBar = document.getElementById('groupOtherBar');

    let carriedGroups = [];
    let activeRole = '';
    let openGroupName = '';

    /** Every group the picker draws, in its own order, with what it does. */
    function groupPanels() {
        return Array.from(document.querySelectorAll('.js-option-group-panel'))
            .map(function (panel) {
                const title = panel.querySelector('.a2-section-title');

                return {
                    panel: panel,
                    name: title ? title.textContent.trim() : '',
                    role: panel.dataset.role || '',
                };
            })
            .filter(function (entry) { return entry.name !== ''; });
    }

    function groupChip(name, held) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'a2-btn a2-btn-sm '
            + (openGroupName === name ? 'a2-btn-primary' : (held ? 'a2-btn-primary' : 'a2-btn-ghost'));
        button.textContent = name;

        if (openGroupName === name) {
            button.style.outline = '2px solid currentColor';
        }

        button.title = @json(__('اعرضها بالأسفل'));
        button.addEventListener('click', function () { revealGroup(name); });

        return button;
    }

    /**
     * @param carried group names the open child already answers — passed in
     *                rather than read back, since the card knows and the picker
     *                does not.
     */
    function renderGroupChips(carried) {
        if (!chipBar || !otherBar) {
            return;
        }

        if (Array.isArray(carried)) {
            carriedGroups = carried;
        }

        const carriedSet = new Set(carriedGroups);

        chipBar.innerHTML = '';
        otherBar.innerHTML = '';

        const rest = [];

        groupPanels().forEach(function (entry) {
            if (activeRole !== '' && entry.role !== activeRole) {
                return;
            }

            if (carriedSet.has(entry.name)) {
                chipBar.appendChild(groupChip(entry.name, true));
            } else {
                rest.push(entry.name);
            }
        });

        if (!carriedSet.size) {
            const hint = document.createElement('span');
            hint.className = 'a2-muted';
            hint.style.fontSize = '12px';
            hint.style.alignSelf = 'center';
            hint.textContent = @json(__('اختر قسمًا واحدًا لتظهر مجموعاته هنا — أو افتح «أخرى».'));
            chipBar.appendChild(hint);
        }

        if (!rest.length) {
            return;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'a2-btn a2-btn-sm a2-btn-ghost';
        toggle.textContent = @json(__('أخرى')) + ' (' + rest.length + ')';
        toggle.addEventListener('click', function () {
            otherBar.style.display = otherBar.style.display === 'none' ? 'flex' : 'none';
        });

        chipBar.appendChild(toggle);

        rest.forEach(function (name) { otherBar.appendChild(groupChip(name, false)); });
    }

    /** Show that group below — and only it. */
    function revealGroup(name) {
        openGroupName = name;

        groupPanels().forEach(function (entry) {
            const wanted = entry.name === name;

            // A role filter no longer hides panels — it filters the chips — so
            // the group an admin just asked for always comes up.
            entry.panel.style.display = wanted ? '' : 'none';
            entry.panel.open = wanted;

            if (wanted) {
                entry.panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        renderGroupChips();
    }

    /** The picker's box for an option, or null when no group draws it. */
    function optionBoxById(optionId) {
        return document.querySelector('.js-option-checkbox[value="' + optionId + '"]');
    }

    /** What the form would post right now. */
    function checkedOptionIds() {
        return Array.from(document.querySelectorAll('.js-option-checkbox:checked'))
            .map(function (input) { return String(input.value); });
    }

    /**
     * Re-draw the card for whichever child it is showing. Guarded against the
     * card's own checkboxes, which already re-open it — without the guard a tick
     * inside the card would rebuild the card from under the click.
     */
    let peekChildId = null;
    let peekChildName = '';
    let redrawing = false;

    function refreshOpenPeek() {
        if (redrawing || !peek || peek.style.display === 'none' || peekChildId === null) {
            return;
        }

        openPeek(peekChildId, peekChildName);
    }

    /**
     * The card edits a CHILD, so it is live whenever exactly one child is in
     * hand. It does not also ask which bulk mode is selected: picking one child
     * switches the screen to replace (see seedFromRegistered), because that is
     * the only mode in which a cleared box means anything at all.
     */
    function removalIsLive() {
        return selectedChildIds().length === 1;
    }

    const peekClose = document.getElementById('childPeekClose');

    if (peekClose) {
        peekClose.addEventListener('click', closePeek);
    }

    const peekSave = document.getElementById('childPeekSave');

    if (peekSave) {
        peekSave.addEventListener('click', function () {
            const form = document.getElementById('bulkOptionsForm');

            if (!form || peekChildId === null) {
                return;
            }

            // Save THIS child alone, exactly as the card shows it. The bulk
            // controls above are left out of it: the button sits under a card
            // about one child, and it would be a trap if it also wrote the other
            // sixty-seven the admin had ticked earlier.
            const replace = document.getElementById('modeReplace');

            if (replace) {
                replace.checked = true;
            }

            document.querySelectorAll('.js-child-checkbox').forEach(function (input) {
                if (input.disabled) {
                    return;
                }

                input.checked = String(input.value) === String(peekChildId);
            });

            form.submit();
        });
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
        refreshChildNav();

        // Every path that changes the selection comes through here — the step
        // keys, the checkboxes, «تحديد الظاهر», the root switch — so this is the
        // one place the address bar has to be kept honest.
        rememberChildren(children);
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
            // Ticking in the picker must show up in the card, or the two views
            // of one answer start to look like two answers.
            refreshOpenPeek();
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

            // It filters the CHIPS now. Filtering the panels meant hiding a group
            // that is open and ticked, which is how a role button became a way to
            // lose sight of what you were about to save.
            activeRole = role;
            renderGroupChips();
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
            // Programmatic .checked fires no change event, so the seed is taken
            // by hand — otherwise the ticks below would describe a child that is
            // no longer the only one selected.
            seedFromRegistered();
        });
    }

    if (uncheckVisibleChildren) {
        uncheckVisibleChildren.addEventListener('click', function () {
            visibleChildren().forEach(function (input) {
                input.checked = false;
            });
            refreshRegistered();
            seedFromRegistered();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Step one child at a time
    |--------------------------------------------------------------------------
    | The whole point of a pass is to see every child once. Stepping ticks
    | exactly one, which is also the state in which the card becomes editable
    | and the chips name that child's own groups — so «التالي» is the whole
    | gesture, not the first half of one.
    */
    const childNav = document.getElementById('childNav');
    const childNavName = document.getElementById('childNavName');
    const childNavPos = document.getElementById('childNavPos');

    function childLabel(input) {
        const card = input.closest('.js-child-card');
        const span = card ? card.querySelector('span') : null;

        return span ? span.textContent.trim() : ('#' + input.value);
    }

    function refreshChildNav() {
        if (!childNav) {
            return;
        }

        const inputs = Array.from(visibleChildren());

        if (!inputs.length) {
            childNav.style.display = 'none';
            return;
        }

        childNav.style.display = 'flex';

        const picked = inputs.filter(function (input) { return input.checked; });

        if (picked.length === 1) {
            childNavName.textContent = childLabel(picked[0]);
            childNavPos.textContent = (inputs.indexOf(picked[0]) + 1) + ' / ' + inputs.length;
            return;
        }

        childNavName.textContent = picked.length
            ? @json(__('عدة أقسام محددة')) + ' (' + picked.length + ')'
            : @json(__('لم يُحدَّد قسم — اضغط «التالي» للبدء'));
        childNavPos.textContent = inputs.length;
    }

    function stepChild(delta) {
        const inputs = Array.from(visibleChildren());

        if (!inputs.length) {
            return;
        }

        // Several ticked (or none) has no "current", so a step starts the walk
        // from whichever end it was heading towards.
        const picked = inputs.filter(function (input) { return input.checked; });
        const from = picked.length === 1 ? inputs.indexOf(picked[0]) : (delta > 0 ? -1 : 0);

        let next = from + delta;

        if (next < 0) { next = inputs.length - 1; }
        if (next >= inputs.length) { next = 0; }

        inputs.forEach(function (input, index) { input.checked = index === next; });

        // No scrollIntoView. «لا يجب ان ترفع الشاشة لاعلى» — the page moves when
        // the admin scrolls it and at no other time. Stepping already says which
        // child is in hand, on a row that is pinned where he is looking.
        refreshRegistered();
        seedFromRegistered();
    }

    const childPrev = document.getElementById('childPrev');
    const childNext = document.getElementById('childNext');

    if (childPrev) { childPrev.addEventListener('click', function () { stepChild(-1); }); }
    if (childNext) { childNext.addEventListener('click', function () { stepChild(1); }); }

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

    /*
    |--------------------------------------------------------------------------
    | «استبدال بالكامل» starts from what is already registered
    |--------------------------------------------------------------------------
    | The owner could not untick a registered option because nothing was ever
    | ticked: the screen only ever asked «what shall they all get». Removing one
    | meant switching to «حذف المحدد» and ticking it — where a tick means the
    | opposite of a tick two modes above.
    |
    | So in replace mode the boxes are seeded with the child's real set and the
    | screen finally says «this is what it has»; unticking one and saving removes
    | it, which is what «استبدال بالكامل» already meant on the server.
    |
    | Only with EXACTLY ONE child selected. With several the sets differ, and a
    | box left unticked because the OTHER child lacks it would silently strip it
    | from the one that had it. There the screen keeps its old empty start and
    | says so.
    */
    // Queried inside the functions rather than held in a const up here:
    // activateRoot() calls seedFromRegistered() and can run before this line
    // does, and a const read before its declaration throws.
    function optionBoxes() {
        return document.querySelectorAll('.js-option-checkbox');
    }

    function currentMode() {
        const picked = document.querySelector('input[name="mode"]:checked');
        return picked ? picked.value : 'append';
    }

    function seedFromRegistered() {
        const children = selectedChildIds();
        const modeHint = document.getElementById('modeHint');
        const saveRow = document.getElementById('childPeekSaveRow');

        if (saveRow) {
            saveRow.style.display = children.length === 1 ? '' : 'none';
        }

        // ONE child means the screen is editing that child, so it puts itself
        // in the only mode where a cleared box means «withdraw this». Leaving
        // the admin in «إضافة» while the card offered him checkboxes to clear
        // was the original complaint in a new costume.
        if (children.length === 1 && currentMode() !== 'replace') {
            const replace = document.getElementById('modeReplace');

            if (replace) {
                replace.checked = true;
            }
        }

        const mode = currentMode();

        if (mode !== 'replace' || children.length !== 1) {
            // No single child in hand means no group is "the child's" — every
            // one of them falls under «أخرى».
            renderGroupChips([]);

            if (modeHint) {
                modeHint.textContent = mode === 'replace'
                    ? (children.length === 1
                        ? ''
                        : @json(__('«استبدال بالكامل» يجعل كل الأقسام المحددة تحمل ما تختاره هنا بالضبط. اختر قسمًا واحدًا لتبدأ من خياراته الحالية.')))
                    : @json(__('التحديد هنا يعني «أضف هذه» أو «احذف هذه». للتعديل على ما هو مسجّل فعلًا، اختر قسمًا واحدًا.'));
            }

            return;
        }

        const registered = new Set(optionsOf(children[0]).map(String));

        optionBoxes().forEach(function (input) {
            input.checked = registered.has(String(input.value));
        });

        if (modeHint) {
            modeHint.textContent = @json(__('المحدد الآن هو ما يحمله هذا القسم فعلًا — أزل ما لا تريده واحفظ.'));
        }

        refreshGroupCounts();

        // «لو قمت بتحدد ابن واحد تظهر كل الخيارات له مجمعة فى كارت»: the card is
        // the point of picking one child, so it opens itself rather than waiting
        // for the admin to find the badge that opens it.
        const card = document.querySelector('.js-child-card[data-child-id="' + children[0] + '"]');

        openPeek(children[0], card ? (card.querySelector('span') || {}).textContent || '' : '');
    }

    document.querySelectorAll('input[name="mode"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            // Leaving replace mode clears the seed, so a tick never carries the
            // meaning it had under a different mode.
            if (currentMode() !== 'replace') {
                optionBoxes().forEach(function (input) { input.checked = false; });
                refreshGroupCounts();
            }

            seedFromRegistered();
        });
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('.js-child-checkbox')) {
            seedFromRegistered();
        }
    });

    buildChildBadges();

    // Arriving with no `parent_id` the controller falls back to the first root
    // it drew, and the URL says nothing about it — so the very first refresh
    // would already be a guess. Name it, without touching whatever selection
    // came in the link.
    if (!new URL(window.location.href).searchParams.has('parent_id')) {
        writeUrl(function (params) { params.set('parent_id', currentRootId()); });
    }

    refreshChildBadges();
    refreshRegistered();
    renderGroupChips([]);
    seedFromRegistered();

    /*
     * ── 419 Page Expired on save ──────────────────────────────────────────
     *
     * «عند الحفظ يعطى 419 Page Expired» — owner, 2026-08-16.
     *
     * This is the screen an admin leaves open for an hour while he reads a
     * root child by child, and `session.lifetime` is 120 minutes of INACTIVITY.
     * A page open that long has made no request since it loaded, so the session
     * is already gone when the form posts and the token matches nothing —
     * Laravel's answer to that is 419, and the whole edit is lost with it. No
     * other admin screen is open long enough to reach it.
     *
     * A GET refreshes the session's last activity, so a heartbeat is the fix.
     * The token in the reply is written back for the other case: a session that
     * was rotated rather than expired, where the page's baked-in token is stale
     * while the session is perfectly alive.
     *
     * Every ten minutes, and once more immediately before the form is
     * submitted — the last one is what actually saves an edit that began
     * before the last beat.
     */
    const PING_URL = @json(route('admin.session.ping', [], false));

    function keepSessionAlive() {
        return fetch(PING_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.token) {
                    return;
                }

                document.querySelectorAll('input[name="_token"]').forEach(function (input) {
                    input.value = data.token;
                });
            })
            .catch(function () { /* offline or logged out — the POST will say so */ });
    }

    setInterval(keepSessionAlive, 10 * 60 * 1000);

    const bulkForm = document.getElementById('bulkOptionsForm');

    if (bulkForm) {
        let refreshed = false;

        bulkForm.addEventListener('submit', function (event) {
            if (refreshed) {
                return;
            }

            // Hold the submit for one round trip, then let it through with a
            // token that is certainly current.
            event.preventDefault();
            refreshed = true;

            keepSessionAlive().then(function () { bulkForm.submit(); });
        });
    }
});
</script>
@endsection