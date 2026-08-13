@extends('admin-v2.layouts.master')

@section('title', 'Child Workbench')
@section('body_class', 'admin-v2 admin-v2-child-workbench')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('طاولة عمل الابن') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر الأب والابن، ثم راجع وعدّل خياراته وخدماته من مكان واحد.') }}
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="a2-alert a2-alert-success a2-mb-16">{{ session('status') }}</div>
    @endif

    {{-- ── the two dropdowns ─────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('admin.child-workbench.index', [], false) }}" class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-card-body cw-pickers">
            <label class="cw-field">
                <span class="a2-muted">{{ __('الأب') }}</span>
                <select name="root_id" class="a2-select" onchange="this.form.querySelector('[name=child_id]').value=''; this.form.submit();">
                    <option value="">{{ __('— اختر —') }}</option>
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}" @selected($rootId === (int) $root->id)>{{ $root->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <label class="cw-field">
                <span class="a2-muted">{{ __('الابن') }}</span>
                <select name="child_id" class="a2-select" @disabled($children->isEmpty()) onchange="this.form.submit();">
                    <option value="">{{ __('— اختر —') }}</option>
                    @foreach($children as $row)
                        <option value="{{ $row->id }}" @selected($childId === (int) $row->id)>{{ $row->name_ar }}</option>
                    @endforeach
                </select>
            </label>

            <noscript><button type="submit" class="a2-btn a2-btn-primary">{{ __('عرض') }}</button></noscript>
        </div>
    </form>

    {{-- ── walking the children, the way the bulk picker walks them ────────
         «السابق والتالى بجوار الابن المختار» — the two buttons sit either side
         of the name, on a row pinned where the eye already is.

         Here a step is a page load, not a redraw, so the browser would land at
         the top: the scroll position is carried across in sessionStorage and
         put back, which is the same promise («لا يجب ان ترفع الشاشة لاعلى»)
         kept by different means. --}}
    @if($childId && $children->isNotEmpty())
        @php
            $ids = $children->pluck('id')->map(fn ($v) => (int) $v)->values();
            $at = $ids->search($childId);
            $prev = $at === false ? null : $ids[($at - 1 + $ids->count()) % $ids->count()];
            $next = $at === false ? null : $ids[($at + 1) % $ids->count()];
            $link = fn ($id) => route('admin.child-workbench.index', ['root_id' => $rootId, 'child_id' => $id], false);
        @endphp

        <div id="childNav" class="a2-mb-16"
             style="display:flex;align-items:center;justify-content:center;gap:8px;
                    padding:8px 12px;border:1px solid var(--a2-border,#d4d4d8);border-radius:10px;
                    position:sticky;top:0;z-index:5;background:var(--a2-card,#fff);">
            <a class="a2-btn a2-btn-ghost js-child-step" id="childPrev"
               href="{{ $prev ? $link($prev) : '#' }}">↦ {{ __('السابق') }}</a>

            <span style="font-weight:600;" id="childNavName">{{ $children->firstWhere('id', $childId)->name_ar ?? '' }}</span>
            <span class="a2-badge" id="childNavPos">{{ ($at === false ? 0 : $at + 1) }} / {{ $ids->count() }}</span>

            <a class="a2-btn a2-btn-ghost js-child-step" id="childNext"
               href="{{ $next ? $link($next) : '#' }}">{{ __('التالي') }} ↤</a>
        </div>
    @endif

    @if(! $childId)
        <div class="a2-card a2-card--soft">
            <div class="a2-card-body a2-muted">{{ __('اختر أبًا ثم ابنًا لعرض خياراته وخدماته.') }}</div>
        </div>
    @else
        @if($sharedRoots->isNotEmpty())
            <div class="a2-alert a2-alert-info a2-mb-16">
                {{ __('هذا الابن مشترك أيضًا مع:') }} <strong>{{ $sharedRoots->implode('، ') }}</strong>.
                {{ __('كل ما تحفظه هنا — الخيارات والخدمات — يخص هذا القسم الرئيسي وحده. الخيار المعلَّم «مشترك» ما زال يعمل تحت كل الأقسام؛ إن أزلته من هنا يبقى كما هو عندها.') }}
            </div>
        @endif

        <div class="cw-columns">

            {{-- ── column 1: options ─────────────────────────────────────── --}}
            <form method="POST" action="{{ route('admin.child-workbench.options', [], false) }}" class="a2-card a2-card--section">
                @csrf
                <input type="hidden" name="root_id" value="{{ $rootId }}">
                <input type="hidden" name="child_id" value="{{ $childId }}">

                <div class="a2-card-head">
                    <h2 class="a2-card-title">{{ __('الخيارات') }}</h2>
                    <span class="a2-muted">{{ __('ما يصف هذا الابن') }}</span>
                </div>

                <div class="a2-card-body">
                    {{-- Chips: what this child already answers on the first row,
                         the rest behind «أخرى», one panel open at a time. --}}
                    <div class="cw-chipbar" data-cw-bar="options"></div>
                    <div class="cw-otherbar" data-cw-other="options"></div>

                    <h3 class="cw-section-title js-cw-heading" data-cw-scope="options">
                        {{ __('المحددة') }}
                        <span class="a2-badge">{{ $optionPanel['selected']->flatten()->count() }}</span>
                    </h3>

                    @forelse($optionPanel['selected'] as $groupName => $options)
                        <div class="cw-block js-cw-panel" data-cw-scope="options"
                             data-cw-key="{{ $groupName }}" data-cw-held="1">
                            <div class="cw-block-head">{{ $groupName }}</div>
                            <div class="cw-chips">
                                @foreach($options as $option)
                                    @php
                                        $isLocked = $optionPanel['locked']->contains($option->id);
                                        $isShared = $sharedRoots->isNotEmpty() && $optionPanel['shared']->contains($option->id);
                                    @endphp
                                    <label class="cw-chip @if($isLocked) is-locked @endif"
                                           @if($isLocked) title="{{ __('اختاره تاجر بالفعل — لا يمكن سحبه') }}" @endif>
                                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}" checked
                                               @disabled($isLocked)>
                                        <span>{{ $option->name_ar }}</span>
                                        @if($isShared)
                                            <em class="cw-tag" title="{{ __('ممنوح تحت كل الأقسام التي يقع تحتها هذا الابن') }}">{{ __('مشترك') }}</em>
                                        @endif
                                    </label>
                                    @if($isLocked)
                                        <input type="hidden" name="option_ids[]" value="{{ $option->id }}">
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="a2-muted">{{ __('لا خيارات محددة بعد.') }}</p>
                    @endforelse

                    <h3 class="cw-section-title a2-mt-16 js-cw-heading" data-cw-scope="options">
                        {{ __('باقي الخيارات') }}
                        <span class="a2-badge">{{ $optionPanel['groups']->flatten()->count() }}</span>
                    </h3>

                    @foreach($optionPanel['groups'] as $groupName => $options)
                        <details class="cw-fold js-cw-panel" data-cw-scope="options" data-cw-key="{{ $groupName }}">
                            <summary>{{ $groupName }} <span class="a2-badge">{{ $options->count() }}</span></summary>
                            <div class="cw-chips">
                                @foreach($options as $option)
                                    <label class="cw-chip">
                                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}">
                                        <span>{{ $option->name_ar }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>

                <div class="a2-card-foot">
                    <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ الخيارات') }}</button>
                </div>
            </form>

            {{-- ── column 2: services ────────────────────────────────────── --}}
            <form method="POST" action="{{ route('admin.child-workbench.services', [], false) }}" class="a2-card a2-card--section">
                @csrf
                <input type="hidden" name="root_id" value="{{ $rootId }}">
                <input type="hidden" name="child_id" value="{{ $childId }}">

                <div class="a2-card-head">
                    <h2 class="a2-card-title">{{ __('الخدمات') }}</h2>
                    <span class="a2-muted">{{ __('ما يبيعه هذا الابن') }}</span>
                </div>

                <div class="a2-card-body">
                    <div class="cw-chipbar" data-cw-bar="services"></div>
                    <div class="cw-otherbar" data-cw-other="services"></div>

                    @foreach($servicePanel as $service)
                        @php $selectedCount = $service->selected->flatten()->count(); @endphp
                        <div class="cw-service js-cw-panel" data-cw-scope="services"
                             data-cw-key="{{ $service->name }}" data-cw-held="{{ $service->enabled ? 1 : 0 }}">
                            <div class="cw-service-head">
                                <label class="cw-toggle">
                                    <input type="checkbox" name="services[{{ $service->id }}][enabled]" value="1"
                                           @checked($service->enabled)>
                                    <strong>{{ $service->name }}</strong>
                                </label>

                                @if($service->enabled && $selectedCount === 0)
                                    <span class="a2-badge a2-badge-danger">{{ __('مفعّلة بلا أنواع — لا يمكن بيع شيء') }}</span>
                                @endif
                            </div>

                            @if($service->key === 'booking')
                                <label class="cw-toggle cw-sub">
                                    <input type="checkbox" name="services[{{ $service->id }}][requires_bookable_item]" value="1"
                                           @checked($service->requiresBookable)>
                                    <span>{{ __('يحجز العميل وحدة بعينها (غرفة، ملعب، طاولة)') }}</span>
                                </label>
                            @endif

                            <h3 class="cw-section-title">
                                {{ __('المحددة') }} <span class="a2-badge">{{ $selectedCount }}</span>
                            </h3>

                            @forelse($service->selected as $groupName => $types)
                                <div class="cw-block">
                                    <div class="cw-block-head">{{ $groupName }}</div>
                                    <div class="cw-chips">
                                        @foreach($types as $type)
                                            <label class="cw-chip">
                                                <input type="checkbox" name="services[{{ $service->id }}][item_types][]"
                                                       value="{{ $type->key }}" checked>
                                                <span>{{ $type->name_ar }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="a2-muted">{{ __('لا أنواع محددة.') }}</p>
                            @endforelse

                            @if($service->groups->isNotEmpty())
                                <h3 class="cw-section-title">
                                    {{ __('باقي الأنواع') }} <span class="a2-badge">{{ $service->groups->flatten()->count() }}</span>
                                </h3>

                                @foreach($service->groups as $groupName => $types)
                                    <details class="cw-fold">
                                        <summary>{{ $groupName }} <span class="a2-badge">{{ $types->count() }}</span></summary>
                                        <div class="cw-chips">
                                            @foreach($types as $type)
                                                <label class="cw-chip">
                                                    <input type="checkbox" name="services[{{ $service->id }}][item_types][]"
                                                           value="{{ $type->key }}">
                                                    <span>{{ $type->name_ar }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="a2-card-foot">
                    <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ الخدمات') }}</button>
                </div>
            </form>

            {{-- ── column 3: fees ─────────────────────────────────────────
                 The third thing decided on this same (root, child) key, and
                 the last one that still lived on a page of its own. --}}
            <form method="POST" action="{{ route('admin.child-workbench.fees', [], false) }}" class="a2-card a2-card--section">
                @csrf
                <input type="hidden" name="root_id" value="{{ $rootId }}">
                <input type="hidden" name="child_id" value="{{ $childId }}">

                <div class="a2-card-head">
                    <h2 class="a2-card-title">{{ __('الرسوم') }}</h2>
                    <span class="a2-muted">{{ __('ما تأخذه المنصّة على كل خدمة') }}</span>
                </div>

                <div class="a2-card-body">
                    @foreach($feePanel as $fee)
                        @php $offered = collect($servicePanel)->firstWhere('id', $fee->id)?->enabled; @endphp

                        <div class="cw-service {{ $offered ? '' : 'cw-fee-off' }}">
                            <div class="cw-service-head">
                                <label class="cw-toggle">
                                    <input type="checkbox" name="fees[{{ $fee->id }}][is_active]" value="1"
                                           @checked($fee->is_active) @disabled(! $offered)>
                                    <strong>{{ $fee->name }}</strong>
                                </label>

                                @unless($offered)
                                    <span class="a2-muted" style="font-size:12px;">{{ __('غير مفعّلة لهذا القسم — فعّلها من «الخدمات» أولًا') }}</span>
                                @endunless
                            </div>

                            @if($offered)
                                @foreach([
                                    'business' => __('على التاجر'),
                                    'client' => __('على العميل'),
                                ] as $payer => $payerLabel)
                                    <div class="cw-fee-row">
                                        <label class="cw-toggle">
                                            <input type="checkbox" name="fees[{{ $fee->id }}][{{ $payer }}_fee_enabled]" value="1"
                                                   @checked($fee->{$payer . '_enabled'})>
                                            <span>{{ $payerLabel }}</span>
                                        </label>

                                        <select class="a2-select" name="fees[{{ $fee->id }}][{{ $payer }}_fee_type]">
                                            <option value="fixed" @selected($fee->{$payer . '_type'} === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                                            <option value="percent" @selected($fee->{$payer . '_type'} === 'percent')>{{ __('نسبة %') }}</option>
                                        </select>

                                        <input class="a2-input" type="number" step="0.01" min="0"
                                               name="fees[{{ $fee->id }}][{{ $payer }}_fee_amount]"
                                               value="{{ $fee->{$payer . '_amount'} }}">
                                    </div>
                                @endforeach

                                <div class="cw-fee-row">
                                    <span class="a2-muted" style="font-size:12px;">{{ __('العملة') }}</span>
                                    <input class="a2-input" name="fees[{{ $fee->id }}][currency]" maxlength="3"
                                           value="{{ $fee->currency }}" style="max-width:90px;">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="a2-card-foot">
                    <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ الرسوم') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .cw-pickers { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
    .cw-field { display: flex; flex-direction: column; gap: 4px; min-width: 220px; flex: 1 1 220px; }

    .cw-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 16px; align-items: start; }

    .cw-section-title { font-size: 14px; margin: 12px 0 8px; display: flex; align-items: center; gap: 8px; }
    .cw-block { margin-bottom: 10px; }
    .cw-block-head { font-size: 12px; opacity: .7; margin-bottom: 4px; }

    .cw-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 4px 0; }
    .cw-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
               border: 1px solid rgba(128,128,128,.35); border-radius: 999px; font-size: 13px; cursor: pointer; }
    .cw-chip:has(input:checked) { border-color: currentColor; font-weight: 600; }
    .cw-chip.is-locked { opacity: .6; cursor: not-allowed; }
    .cw-tag { font-size: 10px; font-style: normal; opacity: .65; border: 1px solid currentColor;
              border-radius: 4px; padding: 0 4px; }

    .cw-fold { border: 1px solid rgba(128,128,128,.25); border-radius: 8px; margin-bottom: 6px; }
    .cw-fold > summary { cursor: pointer; padding: 8px 12px; font-size: 13px; user-select: none; }
    .cw-fold[open] > summary { border-bottom: 1px solid rgba(128,128,128,.2); }
    .cw-fold > .cw-chips { padding: 8px 12px; }

    .cw-service { border-top: 1px solid rgba(128,128,128,.2); padding-top: 12px; margin-top: 12px; }
    .cw-service:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
    .cw-service-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .cw-toggle { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .cw-sub { font-size: 13px; opacity: .85; margin-top: 6px; }

    .cw-fee-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .cw-fee-row .a2-select, .cw-fee-row .a2-input { max-width: 150px; }
    .cw-fee-off { opacity: .55; }

    .cw-chipbar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
    .cw-otherbar { display: none; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;
                   padding: 8px; border: 1px dashed rgba(128,128,128,.35); border-radius: 10px; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    /*
    |--------------------------------------------------------------------------
    | One panel at a time — the shape of category-child-options/bulk
    |--------------------------------------------------------------------------
    |
    | Every group of options and every service is a panel with a chip. What the
    | child already answers sits on the first row; the rest waits behind
    | «أخرى»; pressing a chip opens that one and closes the others.
    |
    | Panels are HIDDEN, never disabled. Both forms save by replacing the whole
    | set — `option_ids[]` and `services[…][item_types][]` — so a disabled field
    | is a deleted answer. `display:none` still submits; `disabled` does not.
    */
    const openPanel = { options: '', services: '' };
    const otherOpen = { options: false, services: false };

    function panels(scope) {
        return Array.from(document.querySelectorAll('.js-cw-panel[data-cw-scope="' + scope + '"]'));
    }

    /** Panel keys in page order, each with whether the child already holds it. */
    function keysOf(scope) {
        const seen = new Map();

        panels(scope).forEach(function (panel) {
            const key = panel.dataset.cwKey || '';

            if (key === '') {
                return;
            }

            seen.set(key, (seen.get(key) === true) || panel.dataset.cwHeld === '1');
        });

        return seen;
    }

    function reveal(scope, key) {
        openPanel[scope] = openPanel[scope] === key ? '' : key;

        panels(scope).forEach(function (panel) {
            const wanted = openPanel[scope] !== '' && panel.dataset.cwKey === openPanel[scope];

            panel.style.display = wanted ? '' : 'none';

            if (wanted && panel.tagName === 'DETAILS') {
                panel.open = true;
            }
        });

        // The «المحددة» / «باقي الخيارات» headings name rows that are now
        // hidden; with nothing open they are the only thing left on screen.
        document.querySelectorAll('.js-cw-heading[data-cw-scope="' + scope + '"]').forEach(function (heading) {
            heading.style.display = openPanel[scope] === '' ? '' : 'none';
        });

        render(scope);
    }

    function chip(scope, key, held) {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'a2-btn a2-btn-sm ' + (held ? 'a2-btn-primary' : 'a2-btn-ghost');
        button.textContent = key;

        if (openPanel[scope] === key) {
            button.style.outline = '2px solid currentColor';
        }

        button.title = @json(__('اعرضها بالأسفل'));
        button.addEventListener('click', function () { reveal(scope, key); });

        return button;
    }

    function render(scope) {
        const bar = document.querySelector('[data-cw-bar="' + scope + '"]');
        const other = document.querySelector('[data-cw-other="' + scope + '"]');

        if (!bar || !other) {
            return;
        }

        bar.innerHTML = '';
        other.innerHTML = '';

        const rest = [];

        keysOf(scope).forEach(function (held, key) {
            if (held) {
                bar.appendChild(chip(scope, key, true));
            } else {
                rest.push(key);
            }
        });

        if (!rest.length) {
            other.style.display = 'none';
            return;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'a2-btn a2-btn-sm a2-btn-ghost';
        toggle.textContent = @json(__('أخرى')) + ' (' + rest.length + ')';
        toggle.addEventListener('click', function () {
            otherOpen[scope] = !otherOpen[scope];
            render(scope);
        });
        bar.appendChild(toggle);

        other.style.display = otherOpen[scope] ? 'flex' : 'none';
        rest.forEach(function (key) { other.appendChild(chip(scope, key, false)); });
    }

    ['options', 'services'].forEach(function (scope) {
        if (panels(scope).length) {
            reveal(scope, '');
        }
    });

    /*
    | Stepping to the next child is a page load here, so the scroll position is
    | carried across rather than promised away. «لا يجب ان ترفع الشاشة لاعلى».
    */
    const KEY = 'cw-scroll';

    document.querySelectorAll('.js-child-step').forEach(function (link) {
        link.addEventListener('click', function () {
            try { sessionStorage.setItem(KEY, String(window.scrollY)); } catch (e) { /* private mode */ }
        });
    });

    try {
        const back = sessionStorage.getItem(KEY);

        if (back !== null) {
            sessionStorage.removeItem(KEY);
            window.scrollTo(0, parseInt(back, 10) || 0);
        }
    } catch (e) { /* private mode */ }
});
</script>
@endpush
