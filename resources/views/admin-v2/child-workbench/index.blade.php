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
                    <h3 class="cw-section-title">
                        {{ __('المحددة') }}
                        <span class="a2-badge">{{ $optionPanel['selected']->flatten()->count() }}</span>
                    </h3>

                    @forelse($optionPanel['selected'] as $groupName => $options)
                        <div class="cw-block">
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

                    <h3 class="cw-section-title a2-mt-16">
                        {{ __('باقي الخيارات') }}
                        <span class="a2-badge">{{ $optionPanel['groups']->flatten()->count() }}</span>
                    </h3>

                    @foreach($optionPanel['groups'] as $groupName => $options)
                        <details class="cw-fold">
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
                    @foreach($servicePanel as $service)
                        @php $selectedCount = $service->selected->flatten()->count(); @endphp
                        <div class="cw-service">
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
</style>
@endpush
