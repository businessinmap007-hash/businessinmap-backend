@extends('admin-v2.layouts.master')

@section('title', 'Menu Shapes')
@section('body_class', 'admin-v2 admin-v2-menu-shapes')

@section('content')
@php
    $name = fn ($m) => trim((string) ($m->name_ar ?? '')) ?: (trim((string) ($m->name_en ?? '')) ?: ('#' . $m->id));
    $profileById = $profiles->keyBy('id');
    $isBasic = $preview === \App\Http\Controllers\AdminV2\MenuShapesController::BASIC;
    $savedProfileId = (int) ($group->menu_detail_profile_id ?? 0);
    $previewFields = $previewProfile ? ($fields[$previewProfile->id] ?? []) : [];
    // A list field nobody gave choices to (the generic «الخامة») is not drawn.
    $previewFields = array_values(array_filter($previewFields, fn ($f) => ! (($f['data_type'] ?? '') === 'select' && empty($f['options']))));
    $noCatalog = $previewProfile && ! $previewProfile->uses_catalog;
    // The descriptive groups this kind offers (طراز، نظام التصنيع، أنواع الأخشاب), in order.
    $describingShown = collect($describingChosen ?? [])->map(fn ($id) => $describingGroups->firstWhere('id', $id))->filter()->values();
    // «buttons» up to six options, a dropdown beyond — unless the admin chose.
    $drawAsChips = fn (string $display, int $count) => $display === 'chips' || ($display === 'auto' && $count <= 6);
    $onPage = fn ($setting) => (bool) ($setting['show_on_page'] ?? true);
    if ($noCatalog) {
        $previewFields = array_map(fn ($f) => ['per_item' => true] + $f, $previewFields);
    }
    // The branch the sample product is filed under, so the chip and the
    // product agree («تابلت» over a tablet) — else the group's first branch.
    $firstBranch = ($sample['branch_id'] ?? null) ? $branches->firstWhere('id', $sample['branch_id']) : $branches->first();
    $link = fn (array $extra) => route('admin.menu-shapes.index', array_filter(['q' => $search, 'shape' => $shape, 'group_id' => $group?->id] + $extra, fn ($v) => $v !== null && $v !== ''));
    $value = function (array $field) use ($sample) {
        $v = $sample['values'][$field['code']] ?? null;
        return $v !== null && $v !== '' ? $v : '—';
    };
    $cardLine = collect($previewFields)->where('show_on_card', true)->map(fn ($f) => $value($f))->reject(fn ($v) => $v === '—')->take(3)->implode(' · ');
    $initial = fn ($text) => mb_substr(trim((string) $text), 0, 1);
@endphp

<style>
    .ms-layout { display: grid; grid-template-columns: minmax(280px, 360px) 1fr; gap: 20px; align-items: start; }
    @media (max-width: 1000px) { .ms-layout { grid-template-columns: 1fr; } }
    .ms-groups { max-height: 78vh; overflow-y: auto; }
    .ms-group { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: inherit; text-decoration: none; border: 1px solid transparent; }
    .ms-group:hover { background: rgba(11, 31, 58, .04); }
    .ms-group.is-active { border-color: #D6A94A; background: rgba(214, 169, 74, .10); }
    .ms-group-name { flex: 1; font-weight: 600; }
    .ms-group-meta { font-size: 12px; opacity: .6; }
    .ms-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .ms-badge--basic { background: rgba(11, 31, 58, .07); color: #0B1F3A; }
    .ms-badge--detail { background: rgba(214, 169, 74, .18); color: #8a6420; }

    .ms-kinds { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .ms-kind { padding: 7px 14px; border-radius: 999px; border: 1px solid rgba(11, 31, 58, .18); color: #0B1F3A; text-decoration: none; font-weight: 600; font-size: 13px; background: #fff; }
    .ms-kind.is-active { background: #0B1F3A; color: #fff; border-color: #0B1F3A; }
    .ms-kind .ms-saved { color: #2E9E5B; margin-inline-start: 4px; }
    .ms-kind.is-active .ms-saved { color: #8fe0b0; }

    .ms-stage { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
    .ms-tabs { display: inline-flex; background: rgba(11, 31, 58, .06); border-radius: 10px; padding: 3px; margin-bottom: 10px; }
    .ms-tab { border: 0; background: transparent; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 12px; color: #0B1F3A; cursor: pointer; font-family: inherit; }
    .ms-tab.is-active { background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }

    /* The phone — the app's LIGHT theme (#FAF9F6 / white / navy / gold, Cairo). */
    .ms-phone { width: 360px; height: 740px; border-radius: 38px; border: 10px solid #1b1f26; background: #FAF9F6; overflow: hidden; position: relative; box-shadow: 0 18px 40px rgba(0, 0, 0, .18); font-family: 'Cairo', system-ui, sans-serif; color: #0B1F3A; direction: rtl; }
    .ms-screen { position: absolute; inset: 0; display: flex; flex-direction: column; }
    .ms-screen[hidden] { display: none; }
    .ms-status { height: 26px; display: flex; justify-content: space-between; align-items: center; padding: 0 18px; font-size: 11px; font-weight: 700; }
    .ms-appbar { height: 50px; display: flex; align-items: center; justify-content: center; position: relative; font-weight: 700; font-size: 16px; }
    .ms-appbar .ms-back { position: absolute; right: 14px; font-size: 20px; }
    .ms-body { flex: 1; overflow-y: auto; padding: 4px 14px 14px; }
    .ms-label { font-size: 12px; font-weight: 600; color: rgba(11, 31, 58, .6); margin: 12px 0 6px; }
    .ms-field { background: #fff; border: 1px solid rgba(11, 31, 58, .14); border-radius: 12px; padding: 10px 12px; font-size: 13px; }
    .ms-field--gold { border-color: #D6A94A; }
    .ms-hint { color: rgba(11, 31, 58, .4); }
    .ms-row { display: flex; gap: 8px; }
    .ms-row > * { flex: 1; }
    .ms-product { display: flex; align-items: center; gap: 10px; }
    .ms-thumb { width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, #16305A, #0B1F3A); color: #D6A94A; display: flex; align-items: center; justify-content: center; font-weight: 800; flex: 0 0 auto; overflow: hidden; }
    .ms-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .ms-specs { background: #fff; border: 1px solid rgba(11, 31, 58, .12); border-radius: 12px; overflow: hidden; }
    .ms-spec { display: flex; justify-content: space-between; gap: 10px; padding: 8px 12px; font-size: 12.5px; }
    .ms-spec:nth-child(odd) { background: rgba(11, 31, 58, .03); }
    .ms-spec span:first-child { color: rgba(11, 31, 58, .55); }
    .ms-spec span:last-child { font-weight: 700; text-align: left; }
    .ms-seg { display: flex; background: #fff; border: 1px solid rgba(11, 31, 58, .14); border-radius: 10px; padding: 3px; }
    .ms-seg span { flex: 1; text-align: center; padding: 7px; border-radius: 7px; font-size: 13px; font-weight: 600; }
    .ms-seg span.on { background: #0B1F3A; color: #fff; }
    /* a label holding a checkbox + words: the global .a2-checkbox is the 18px box itself, not a label */
    .ms-check { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; margin: 8px 0 4px; }
    .ms-check input { width: 18px; height: 18px; flex: 0 0 auto; accent-color: var(--a2-primary); }
    .ms-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; border: 1px solid rgba(11, 31, 58, .25); font-size: 12px; background: #fff; }
    .ms-pill.on { background: #D6A94A; border-color: #D6A94A; font-weight: 700; }
    .ms-chip { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #D6A94A; color: #0B1F3A; font-size: 12px; font-weight: 700; }
    .ms-bottom { padding: 12px 14px 16px; border-top: 1px solid rgba(11, 31, 58, .08); background: #fff; }
    .ms-btn { height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 14px; }
    .ms-btn--navy { background: #0B1F3A; color: #fff; }
    .ms-btn--gold { background: #D6A94A; color: #0B1F3A; }
    .ms-btn--outline { border: 1.4px solid #0B1F3A; color: #0B1F3A; background: #fff; }
    .ms-branch { display: flex; align-items: center; gap: 10px; background: #fff; border-radius: 14px; padding: 10px 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(11, 31, 58, .08); }
    .ms-avatar { width: 34px; height: 34px; border-radius: 50%; background: #0B1F3A; color: #D6A94A; display: flex; align-items: center; justify-content: center; font-weight: 800; flex: 0 0 auto; }
    .ms-branch-name { flex: 1; font-weight: 600; font-size: 14px; }
    .ms-add { font-size: 12px; font-weight: 700; }
    .ms-hero { position: relative; height: 190px; background: linear-gradient(135deg, #16305A, #0B1F3A); display: flex; align-items: center; justify-content: center; color: #D6A94A; font-size: 44px; font-weight: 800; }
    .ms-dots { position: absolute; bottom: 8px; left: 0; right: 0; display: flex; justify-content: center; gap: 5px; }
    .ms-dots i { width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.6); }
    .ms-dots i:first-child { width: 16px; border-radius: 4px; background: #D6A94A; }
    .ms-hero img { max-height: 100%; max-width: 100%; object-fit: contain; }
    .ms-price { color: #b8892c; font-weight: 800; font-size: 20px; }
    .ms-badge-ok { background: rgba(46, 158, 91, .14); color: #2E9E5B; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 7px; }
    .ms-stepper { display: inline-flex; align-items: center; border: 1px solid rgba(11, 31, 58, .14); border-radius: 10px; background: #fff; }
    .ms-stepper span { width: 32px; text-align: center; font-weight: 700; padding: 5px 0; }

    .ms-fields-table { min-width: 0 !important; width: 100%; table-layout: auto; }
    .ms-fields-table td, .ms-fields-table th { padding: 6px 6px; font-size: 12.5px; white-space: normal; }
    .ms-fields-table th:not(:first-child), .ms-fields-table td:not(:first-child) { text-align: center; width: 1%; white-space: nowrap; }
    .ms-note { font-size: 12px; opacity: .7; line-height: 1.7; }
    .ms-row-off td { opacity: .45; }
    .ms-row-off td:first-child { opacity: .7; }
</style>

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('أشكال المنيو') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('كل مجموعة خيارات مسعَّرة إمّا «منيو أساسي» (خضار، فاكهة، عطارة — اسم وسعر وكمية) أو «منيو تفصيلي» من نوع محدد (موبايلات، كمبيوتر، لاب توب، سيارات…). اختر مجموعة، جرّبها على كل نوع فوق الموبايل، ثم اعتمد الشكل المناسب. حقول كل نوع تفاصيل هي ما يملؤه التاجر، وما يراه العميل فى صفحة المنتج، وما يُفلتر به البحث لمقارنة الأسعار بين المحلات.') }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="ms-layout">
        {{-- Right (RTL first): the priced option groups. --}}
        <div class="a2-card">
            <form method="GET" action="{{ route('admin.menu-shapes.index') }}" class="a2-mb-16">
                <input class="a2-input" type="search" name="q" value="{{ $search }}" placeholder="{{ __('ابحث عن مجموعة خيارات…') }}">
                <select class="a2-select" name="shape" onchange="this.form.submit()" style="margin-top:8px">
                    <option value="">{{ __('كل الأشكال') }}</option>
                    <option value="basic" @selected($shape === 'basic')>{{ __('منيو أساسي') }}</option>
                    @foreach($profiles as $p)
                        <option value="{{ $p->id }}" @selected($shape === (string) $p->id)>{{ __('تفصيلي — ') }}{{ $p->name_ar }}</option>
                    @endforeach
                </select>
            </form>

            <div class="ms-groups">
                @forelse($groups as $g)
                    @php $gp = $g->menu_detail_profile_id ? $profileById->get($g->menu_detail_profile_id) : null; @endphp
                    <a class="ms-group {{ $group && $group->id === $g->id ? 'is-active' : '' }}"
                       href="{{ route('admin.menu-shapes.index', array_filter(['q' => $search, 'shape' => $shape, 'group_id' => $g->id])) }}">
                        <div class="ms-group-name">
                            {{ $name($g) }}
                            <div class="ms-group-meta">فروع: {{ $g->options_count }}</div>
                        </div>
                        @if($gp)
                            <span class="ms-badge ms-badge--detail">{{ $gp->name_ar }}</span>
                        @else
                            <span class="ms-badge ms-badge--basic">{{ __('أساسي') }}</span>
                        @endif
                    </a>
                @empty
                    <div class="ms-note">{{ __('لا توجد مجموعات مطابقة.') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Left: the detail kinds above a phone. --}}
        <div>
            @if($group)
                <div class="ms-kinds">
                    <a class="ms-kind {{ $isBasic ? 'is-active' : '' }}" href="{{ $link(['preview' => 'basic']) }}">
                        {{ __('منيو أساسي') }}@if(! $savedProfileId)<span class="ms-saved">✓</span>@endif
                    </a>
                    @foreach($profiles as $p)
                        <a class="ms-kind {{ ! $isBasic && $previewProfile && $previewProfile->id === $p->id ? 'is-active' : '' }}" href="{{ $link(['preview' => $p->id]) }}">
                            {{ $p->name_ar }}@if($savedProfileId === (int) $p->id)<span class="ms-saved">✓</span>@endif
                        </a>
                    @endforeach
                </div>

                <div class="ms-stage">
                    <div>
                        <div class="ms-tabs" role="tablist">
                            <button type="button" class="ms-tab is-active" data-screen="merchant">{{ __('إضافة صنف (التاجر)') }}</button>
                            <button type="button" class="ms-tab" data-screen="customer">{{ __('صفحة المنتج (العميل)') }}</button>
                        </div>

                        <div class="ms-phone">
                            {{-- ───────── Page 2: what the merchant adds an item through ───────── --}}
                            <div class="ms-screen" data-screen="merchant">
                                <div class="ms-status"><span>9:41</span><span>●●● ▮</span></div>
                                @if($isBasic)
                                    <div class="ms-appbar"><span class="ms-back">→</span>الأصناف</div>
                                    <div class="ms-body">
                                        <div class="ms-row" style="align-items:center;margin-bottom:10px">
                                            <strong style="flex:1">{{ $name($group) }}</strong>
                                            <span class="ms-chip" style="flex:0 0 auto">إدارة الأصناف</span>
                                        </div>
                                        @foreach($branches->take(4) as $b)
                                            <div class="ms-branch">
                                                <div class="ms-avatar">{{ $initial($b->name_ar) }}</div>
                                                <div class="ms-branch-name">{{ $b->name_ar }}</div>
                                                <div class="ms-add">＋ إضافة سعر</div>
                                            </div>
                                        @endforeach
                                        <div class="ms-field" style="margin-top:12px;box-shadow:0 6px 18px rgba(11,31,58,.12)">
                                            <strong>إضافة سعر — {{ $firstBranch?->name_ar }}</strong>
                                            <div class="ms-row" style="margin-top:10px">
                                                <div class="ms-field"><span class="ms-hint">سعر التوريد</span></div>
                                                <div class="ms-field ms-field--gold"><span class="ms-hint">سعر البيع</span></div>
                                            </div>
                                            <div class="ms-row" style="margin-top:8px">
                                                <div class="ms-field"><span class="ms-hint">الكمية المتاحة</span></div>
                                                <div class="ms-field"><span class="ms-hint">الوحدة</span></div>
                                            </div>
                                            <div class="ms-field" style="margin-top:8px"><span class="ms-hint">الوصف (اختياري)</span></div>
                                        </div>
                                    </div>
                                    <div class="ms-bottom"><div class="ms-btn ms-btn--navy">حفظ</div></div>
                                @else
                                    <div class="ms-appbar"><span class="ms-back">→</span>التسعير والتفاصيل</div>
                                    <div class="ms-body">
                                        @if($firstBranch)<span class="ms-chip">{{ $firstBranch->name_ar }}</span>@endif
                                        @if($noCatalog)
                                        <div class="ms-label">{{ __('الاسم (عربي)') }}</div>
                                        <div class="ms-field"><span class="ms-hint">{{ __('الاسم (عربي)') }}</span></div>
                                        @else
                                        <div class="ms-label">المنتج</div>
                                        <div class="ms-field ms-product">
                                            <div class="ms-thumb">@if($sample && $sample['image'])<img src="{{ asset($sample['image']) }}" alt="">@else{{ $initial($previewProfile->name_ar) }}@endif<span class="ms-dots" title="{{ __('صور الصنف كلها هنا، تُمرَّر باللمس') }}"><i></i><i></i><i></i></span></div>
                                            <div style="flex:1;min-width:0">
                                                <div style="font-weight:700">{{ $sample['name'] ?? __('اختر منتجًا حقيقيًا') }}</div>
                                                @if($sample && $sample['brand'])<div class="ms-hint" style="font-size:12px">{{ $sample['brand'] }}</div>@endif
                                            </div>
                                            <span class="ms-hint">‹</span>
                                        </div>
                                        @endif
                                        @if($previewFields || $describingShown->isNotEmpty())
                                            @if(! $noCatalog && $previewFields)
                                            <div class="ms-label">المواصفات — {{ $previewProfile->name_ar }}</div>
                                            <div class="ms-specs">
                                                @foreach(collect($previewFields)->where('per_item', false) as $f)
                                                    <div class="ms-spec"><span>{{ $f['name'] }}</span><span>{{ $value($f) }}</span></div>
                                                @endforeach
                                            </div>
                                            @endif
                                            @if(collect($previewFields)->where('per_item', true)->isNotEmpty() || $describingShown->isNotEmpty())
                                                <div class="ms-label">{{ $noCatalog ? __('تفاصيل الصنف') : 'لهذه الوحدة بالذات' }}</div>
                                                <div class="ms-row" style="flex-wrap:wrap">
                                                    @foreach(collect($previewFields)->where('per_item', true) as $f)
                                                        @if(($f['data_type'] ?? '') === 'select' && $drawAsChips($f['display'] ?? 'auto', count($f['options'] ?? [])))
                                                            <div style="flex:1 1 100%"><div class="ms-hint" style="font-size:12px;margin-bottom:4px">{{ $f['name'] }}</div>@foreach(array_slice($f['options'], 0, 4) as $i => $o)<span class="ms-pill {{ $i === 0 ? 'on' : '' }}">{{ $o['name'] }}</span> @endforeach</div>
                                                        @elseif(($f['data_type'] ?? '') === 'select')
                                                            <div class="ms-field" style="flex:1 1 44%;display:flex;justify-content:space-between"><span class="ms-hint">{{ $f['name'] }}</span><span class="ms-hint">▾</span></div>
                                                        @else
                                                            <div class="ms-field" style="flex:1 1 44%"><span class="ms-hint">{{ $f['name'] }}@if($f['unit']) ({{ $f['unit'] }})@endif</span></div>
                                                        @endif
                                                    @endforeach
                                                    @foreach($describingShown as $g)
                                                        @if($drawAsChips($describingSettings[$g->id]['display'] ?? 'auto', (int) $g->options_count))
                                                            <div style="flex:1 1 100%"><div class="ms-hint" style="font-size:12px;margin-bottom:4px">{{ $g->name_ar }}</div><span class="ms-pill on">{{ $describingSamples[$g->id] ?? '' }}</span> <span class="ms-pill {{ ($describingSettings[$g->id]['multiple'] ?? true) ? 'on' : '' }}">…</span></div>
                                                        @else
                                                            <div class="ms-field" style="flex:1 1 44%;display:flex;justify-content:space-between"><span class="ms-hint">{{ $g->name_ar }}</span><span class="ms-hint">▾</span></div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <div class="ms-field ms-hint">لا حقول لهذا النوع بعد — اختر حقوله بالأسفل.</div>
                                        @endif
                                        <div class="ms-label">حالة المنتج</div>
                                        <div class="ms-seg"><span class="on">جديد</span><span>مستعمل</span></div>
                                        <div class="ms-row" style="margin-top:12px">
                                            <div class="ms-field ms-field--gold"><span class="ms-hint">السعر</span></div>
                                            <div class="ms-field"><span class="ms-hint">الكمية المتاحة (اختياري)</span></div>
                                        </div>
                                        <div class="ms-field" style="margin-top:8px;min-height:54px"><span class="ms-hint">الوصف (عربي، اختياري)</span></div>
                                        <div class="ms-label">{{ __('صور المنتج') }}</div>
                                        <div class="ms-row">
                                            <div class="ms-field" style="text-align:center;font-weight:700">{{ __('التقاط بالكاميرا') }}</div>
                                            <div class="ms-field" style="text-align:center;font-weight:700">{{ __('من المعرض') }}</div>
                                        </div>
                                    </div>
                                    <div class="ms-bottom"><div class="ms-btn ms-btn--navy">حفظ</div></div>
                                @endif
                            </div>

                            {{-- ───────── Page 4: what the customer sees (same cart bar everywhere) ───────── --}}
                            <div class="ms-screen" data-screen="customer" hidden>
                                <div class="ms-status"><span>9:41</span><span>●●● ▮</span></div>
                                @if($isBasic)
                                    <div class="ms-appbar"><span class="ms-back">→</span>المنيو</div>
                                    <div class="ms-body">
                                        <strong>{{ $name($group) }}</strong>
                                        @foreach($branches->take(4) as $b)
                                            <div class="ms-branch" style="margin-top:8px">
                                                <div class="ms-avatar">{{ $initial($b->name_ar) }}</div>
                                                <div class="ms-branch-name">{{ $b->name_ar }}<div class="ms-hint" style="font-size:11px">٢٥ ج / كجم</div></div>
                                                <div class="ms-stepper"><span>−</span><span>1</span><span>+</span></div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="ms-appbar"><span class="ms-back">→</span>تفاصيل المنتج</div>
                                    <div class="ms-body" style="padding:0">
                                        <div class="ms-hero">@if($sample && $sample['image'])<img src="{{ asset($sample['image']) }}" alt="">@else{{ $initial($previewProfile->name_ar) }}@endif</div>
                                        <div style="padding:12px 14px">
                                            <div style="font-weight:800;font-size:17px">{{ $sample['name'] ?? $previewProfile->name_ar }}</div>
                                            @if($sample && $sample['brand'])<div class="ms-hint" style="font-weight:600">{{ $sample['brand'] }}</div>@endif
                                            <div class="ms-row" style="align-items:center;margin-top:6px;justify-content:flex-start;gap:10px">
                                                <span class="ms-price" style="flex:0 0 auto">12,500</span><span class="ms-badge-ok" style="flex:0 0 auto">جديد</span>
                                            </div>
                                            @if($cardLine)<div class="ms-hint" style="font-size:12px;margin-top:4px">{{ $cardLine }}</div>@endif
                                            <div class="ms-label">المواصفات</div>
                                            <div class="ms-specs">
                                                @foreach(collect($previewFields)->filter(fn ($f) => $onPage($f)) as $f)
                                                    <div class="ms-spec"><span>{{ $f['name'] }}</span><span>{{ $value($f) }}</span></div>
                                                @endforeach
                                                @foreach($describingShown->filter(fn ($g) => $onPage($describingSettings[$g->id] ?? null)) as $g)
                                                    <div class="ms-spec"><span>{{ $g->name_ar }}</span><span>{{ $describingSamples[$g->id] ?? '—' }}</span></div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                {{-- The ONE cart bar — the same shape, labels and place under every service. --}}
                                <div class="ms-bottom">
                                    <div class="ms-row">
                                        <div class="ms-btn ms-btn--outline">🛒 أضف للسلة</div>
                                        <div class="ms-btn ms-btn--gold" style="flex:1.4">شراء مباشر · 12,500</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="flex:1;min-width:280px;max-width:640px">
                        <div class="a2-card a2-mb-16">
                            <form method="POST" action="{{ route('admin.menu-shapes.assign') }}">
                                @csrf
                                <input type="hidden" name="group_id" value="{{ $group->id }}">
                                <input type="hidden" name="profile_id" value="{{ $isBasic ? '' : $previewProfile->id }}">
                                <input type="hidden" name="q" value="{{ $search }}">
                                <input type="hidden" name="shape" value="{{ $shape }}">
                                <div style="margin-bottom:10px">
                                    <strong>{{ $name($group) }}</strong>
                                    <div class="ms-note">
                                        {{ __('الشكل الحالي:') }}
                                        {{ $savedProfileId ? __('منيو تفصيلي — ') . ($profileById->get($savedProfileId)?->name_ar) : __('منيو أساسي') }}
                                    </div>
                                </div>
                                @php $unchanged = $isBasic ? ! $savedProfileId : ($savedProfileId === (int) $previewProfile->id); @endphp
                                <button class="a2-btn a2-btn-primary" type="submit" @disabled($unchanged)>
                                    {{ __('اعتماد') }} «{{ $isBasic ? __('منيو أساسي') : $previewProfile->name_ar }}» {{ __('لهذه المجموعة') }}
                                </button>
                            </form>
                        </div>

                        @if($previewProfile)
                            @php
                                $current = collect($previewFields)->keyBy('id');
                                $ordered = $attributes->sortBy(fn ($a) => $current->has($a->id) ? array_search($a->id, $current->keys()->all(), true) : 1000 + $a->id);
                            @endphp
                            <div class="a2-card a2-mb-16">
                                <form method="POST" action="{{ route('admin.menu-shapes.profiles.fields', $previewProfile) }}">
                                    @csrf
                                    <input type="hidden" name="group_id" value="{{ $group->id }}">
                                    <input type="hidden" name="q" value="{{ $search }}">
                                    <input type="hidden" name="shape" value="{{ $shape }}">
                                    <strong>{{ __('حقول «') }}{{ $previewProfile->name_ar }}{{ __('»') }}</strong>
                                    <div class="ms-note a2-mb-16">{{ __('ما يُعلَّم هنا يظهر للتاجر فى «التسعير والتفاصيل» وللعميل فى صفحة المنتج. «على الكارت» = السطر المختصر تحت اسم المنتج. «فى صفحة المنتج» = يظهر للعميل فى «المواصفات»، «الاختيار» (للمجموعات الوصفية) = واحد فقط (الطراز) أو متعدد (أنواع الأخشاب)، وحقول الكتالوج اختيار واحد دائمًا لأن للوحدة قيمة واحدة. «العرض» = يرسم التاجر القائمة أزرارًا (سريعة، كل الخيارات ظاهرة) أو قائمة منسدلة (مدمجة للكثير)، وتلقائى = أزرار حتى ٦ خيارات وقائمة بعدها. «لكل وحدة» = يدخله التاجر لكل صنف بنفسه (سنة سيارة، كيلومتراتها، لونها) لأن موديل الكتالوج الواحد يُباع بقيم مختلفة؛ غير المعلَّم يؤخذ من الكتالوج. «فلتر البحث» = يُبحث ويُقارَن به بين المحلات. الحقول غير المفعّلة لا تظهر لا فى فلتر هذا النوع ولا فى صفحاته — كل نوع له فلاتره هو فقط.') }}</div>
                                    <div style="max-height:420px;overflow:auto">
                                        <table class="a2-table ms-fields-table">
                                            <thead><tr><th>{{ __('الحقل') }}</th><th>{{ __('مفعّل') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('على الكارت') }}</th><th>{{ __('لكل وحدة') }}</th><th>{{ __('فلتر البحث') }}</th><th>{{ __('فى صفحة المنتج') }}</th><th>{{ __('العرض') }}</th></tr></thead>
                                            <tbody>
                                                @foreach($ordered as $a)
                                                    @php $f = $current->get($a->id); @endphp
                                                    <tr class="{{ $f ? '' : 'ms-row-off' }}" data-attr-row>
                                                        <td>{{ $a->name_ar }} <span class="ms-note">{{ $a->code }}{{ $a->unit ? ' · ' . $a->unit : '' }}</span></td>
                                                        <td><input type="checkbox" name="fields[{{ $a->id }}][enabled]" value="1" data-enable @checked($f)></td>
                                                        <td><input class="a2-input" style="width:58px;min-width:0" type="number" min="0" name="fields[{{ $a->id }}][sort_order]" value="{{ $f ? ($loop->index + 1) * 10 : '' }}" data-dep @disabled(! $f)></td>
                                                        <td><input type="checkbox" name="fields[{{ $a->id }}][show_on_card]" value="1" data-dep @checked($f && $f['show_on_card']) @disabled(! $f)></td>
                                                        <td><input type="checkbox" name="fields[{{ $a->id }}][per_item]" value="1" data-dep @checked($f && $f['per_item']) @disabled(! $f)></td>
                                                        <td><input type="checkbox" name="fields[{{ $a->id }}][is_filterable]" value="1" data-dep @checked($f && $f['is_filterable']) @disabled(! $f)></td>
                                                        <td><input type="hidden" name="fields[{{ $a->id }}][show_on_page]" value="0"><input type="checkbox" name="fields[{{ $a->id }}][show_on_page]" value="1" data-dep @checked($f ? $f['show_on_page'] : true) @disabled(! $f) title="{{ __('يظهر هذا الحقل للعميل فى صفحة المنتج') }}"></td>
                                                        <td>@if($a->data_type === 'select')<select class="a2-select" style="min-width:92px" name="fields[{{ $a->id }}][display]" data-dep @disabled(! $f)>@foreach(['auto' => 'تلقائى', 'chips' => 'أزرار', 'dropdown' => 'قائمة'] as $k => $l)<option value="{{ $k }}" @selected(($f['display'] ?? 'auto') === $k)>{{ __($l) }}</option>@endforeach</select>@else<span class="ms-note">—</span>@endif</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div style="margin-top:14px"><strong>{{ __('حقول وصفية — من مجموعات الخيارات') }}</strong></div>
                                    <div class="ms-note a2-mb-16">{{ __('مجموعات يصفها التاجر بالاختيار منها (طراز الأثاث، نظام التصنيع، أنواع الأخشاب) وتظهر له كقوائم منسدلة فى «التسعير والتفاصيل». المعلَّم هنا فقط هو ما يظهر لهذا النوع، بالترتيب المكتوب؛ وإن لم تُعلِّم شيئًا يظهر كل ما جعلته «مكونات الخدمة» وصفيًا للنشاط.') }}</div>
                                    <table class="a2-table ms-fields-table">
                                        <thead><tr><th>{{ __('المجموعة') }}</th><th>{{ __('مفعّل') }}</th><th>{{ __('الترتيب') }}</th><th>{{ __('فى صفحة المنتج') }}</th><th>{{ __('العرض') }}</th><th>{{ __('الاختيار') }}</th></tr></thead>
                                        <tbody>
                                            @forelse($describingGroups as $g)
                                                @php $on = in_array((int) $g->id, $describingChosen, true); @endphp
                                                <tr class="{{ $on ? '' : 'ms-row-off' }}" data-attr-row>
                                                    <td>{{ $g->name_ar }} <span class="ms-note">{{ $g->options_count }} {{ __('خيار') }}</span></td>
                                                    <td><input type="checkbox" name="describing[{{ $g->id }}][enabled]" value="1" data-enable @checked($on)></td>
                                                    <td><input class="a2-input" style="width:58px;min-width:0" type="number" min="0" name="describing[{{ $g->id }}][sort_order]" value="{{ $on ? (array_search((int) $g->id, $describingChosen, true) + 1) * 10 : '' }}" data-dep @disabled(! $on)></td>
                                                    <td><input type="hidden" name="describing[{{ $g->id }}][show_on_page]" value="0"><input type="checkbox" name="describing[{{ $g->id }}][show_on_page]" value="1" data-dep @checked($on ? ($describingSettings[$g->id]['show_on_page'] ?? true) : true) @disabled(! $on)></td>
                                                    <td><select class="a2-select" style="min-width:92px" name="describing[{{ $g->id }}][display]" data-dep @disabled(! $on)>@foreach(['auto' => 'تلقائى', 'chips' => 'أزرار', 'dropdown' => 'قائمة'] as $k => $l)<option value="{{ $k }}" @selected(($describingSettings[$g->id]['display'] ?? 'auto') === $k)>{{ __($l) }}</option>@endforeach</select></td>
                                                    <td><select class="a2-select" style="min-width:92px" name="describing[{{ $g->id }}][multiple]" data-dep @disabled(! $on)>@foreach(['1' => 'متعدد', '0' => 'واحد فقط'] as $k => $l)<option value="{{ $k }}" @selected((string) (int) ($describingSettings[$g->id]['multiple'] ?? true) === (string) $k)>{{ __($l) }}</option>@endforeach</select></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="ms-note">{{ __('لا توجد مجموعات وصفية بعد — اجعل مجموعة «وصفية» من «مكونات الخدمة».') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    <hr style="margin:14px 0;opacity:.2">
                                    <input type="hidden" name="uses_catalog_form" value="1">
                                    <label class="ms-check"><input type="checkbox" name="uses_catalog" value="1" @checked($previewProfile->uses_catalog)> {{ __('يعتمد على كتالوج منتجات حقيقية (يختار التاجر موديلًا جاهزًا)') }}</label>
                                    <div class="ms-note">{{ $previewProfile->uses_catalog
                                        ? __('التاجر يختار الموديل ويكتب فقط الحقول «لكل وحدة».')
                                        : __('بلا كتالوج: التاجر يسمّى الصنف بنفسه ويدخل كل حقول النوع، ويختار الوصف/الخامة من مجموعات الخيارات، ويرفع الصور والسعر.') }}</div>
                                    <button class="a2-btn a2-btn-primary" type="submit" style="margin-top:12px">{{ __('حفظ') }}</button>
                                    <span class="ms-note">{{ __('يحفظ الحقول والمجموعات الوصفية وخيار الكتالوج معًا.') }}</span>
                                </form>

                                <details style="margin-top:12px">
                                    <summary style="cursor:pointer;font-weight:700">{{ __('＋ إضافة حقل جديد لهذا النوع') }}</summary>
                                    <form method="POST" action="{{ route('admin.menu-shapes.profiles.attributes', $previewProfile) }}" style="margin-top:10px">
                                        @csrf
                                        <input type="hidden" name="group_id" value="{{ $group->id }}">
                                        <div class="ms-row">
                                            <input class="a2-input" name="name_ar" required placeholder="{{ __('اسم الحقل — مثل: سُمك اللوح') }}">
                                            <input class="a2-input" name="name_en" placeholder="Field name (English)">
                                        </div>
                                        <div class="ms-row" style="margin-top:8px">
                                            <select class="a2-select" name="data_type" id="ms-new-type">
                                                <option value="text">{{ __('نص') }}</option>
                                                <option value="number">{{ __('رقم') }}</option>
                                                <option value="select">{{ __('اختيار من قائمة') }}</option>
                                            </select>
                                            <input class="a2-input" name="unit" placeholder="{{ __('الوحدة (اختياري) — مثل: سم') }}">
                                        </div>
                                        <textarea class="a2-input" name="options" rows="3" id="ms-new-options" style="margin-top:8px;display:none" placeholder="{{ __('خيارات القائمة — واحد فى كل سطر') }}"></textarea>
                                        <div class="ms-note" style="margin-top:6px">{{ __('يُضاف مفعّلًا فى هذا النوع وفى فلتر البحث، ويظهر للتاجر فى شاشة إضافة الصنف.') }}</div>
                                        <button class="a2-btn a2-btn-primary" type="submit" style="margin-top:8px">{{ __('إضافة الحقل') }}</button>
                                    </form>
                                </details>
                            </div>
                        @endif

                        <details class="a2-card">
                            <summary style="cursor:pointer;font-weight:700">{{ __('＋ نوع تفاصيل جديد') }}</summary>
                            <form method="POST" action="{{ route('admin.menu-shapes.profiles.store') }}" style="margin-top:10px">
                                @csrf
                                <input type="hidden" name="group_id" value="{{ $group->id }}">
                                <div class="ms-row">
                                    <input class="a2-input" name="name_ar" required placeholder="{{ __('الاسم — مثل: ألواح بديل الخشب') }}">
                                    <input class="a2-input" name="name_en" placeholder="Name (English)">
                                </div>
                                <label class="ms-check" style="margin-top:8px;display:block"><input type="checkbox" name="uses_catalog" value="1"> {{ __('يعتمد على كتالوج منتجات حقيقية (موبايلات، سيارات…) — اتركه فارغًا للأثاث والألواح') }}</label>
                                <button class="a2-btn a2-btn-ghost" type="submit" style="margin-top:8px">{{ __('إضافة') }}</button>
                            </form>
                        </details>
                    </div>
                </div>
            @else
                <div class="a2-card ms-note">{{ __('لا توجد مجموعات خيارات مسعّرة.') }}</div>
            @endif
        </div>
    </div>
</div>

<script>
    // A field the kind does not use has nothing to put on a card, ask per unit
    // or filter by — its other boxes stay off until it is enabled.
    document.querySelectorAll('[data-attr-row]').forEach(function (row) {
        var enable = row.querySelector('[data-enable]');
        enable.addEventListener('change', function () {
            row.classList.toggle('ms-row-off', !enable.checked);
            row.querySelectorAll('[data-dep]').forEach(function (el) {
                el.disabled = !enable.checked;
                if (!enable.checked) { el.checked = false; if (el.type === 'number') el.value = ''; }
                else if (el.name.indexOf('is_filterable') !== -1 || el.name.indexOf('show_on_page') !== -1) { el.checked = true; }
            });
        });
    });

    var newType = document.getElementById('ms-new-type');
    if (newType) {
        newType.addEventListener('change', function () {
            document.getElementById('ms-new-options').style.display = newType.value === 'select' ? 'block' : 'none';
        });
    }

    document.querySelectorAll('.ms-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var screen = tab.getAttribute('data-screen');
            document.querySelectorAll('.ms-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            document.querySelectorAll('.ms-screen').forEach(function (s) { s.hidden = s.getAttribute('data-screen') !== screen; });
        });
    });
</script>
@endsection
