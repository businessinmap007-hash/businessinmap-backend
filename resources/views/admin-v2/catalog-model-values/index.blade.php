@extends('admin-v2.layouts.master')

@section('title', 'Catalog Model Values')
@section('body_class', 'admin-v2 admin-v2-catalog-model-values')

@section('content')
@php
    $perItem = collect($allFields[$profile?->id] ?? [])->where('per_item', true);
@endphp

<style>
    .cmv-wrap { overflow: auto; max-height: 72vh; }
    .cmv-table th { position: sticky; top: 0; background: var(--a2-card-bg, #fff); z-index: 1; white-space: nowrap; }
    .cmv-table td, .cmv-table th { padding: 6px 8px; vertical-align: middle; }
    .cmv-table td:first-child { min-width: 240px; }
    .cmv-table .a2-input, .cmv-table .a2-select { min-width: 120px; padding: 5px 8px; }
    .cmv-name { font-weight: 600; }
    .cmv-sub { font-size: 12px; opacity: .6; }
    .cmv-note { font-size: 12.5px; opacity: .75; line-height: 1.7; }
    .cmv-filled { background: rgba(46, 158, 91, .06); }
</style>

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('قيم موديلات الكتالوج') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('لكل نوع تفاصيل: موديلاته كصفوف وحقوله أعمدة — اختر القيمة أو اكتبها. هذه القيم هى ما تظهر فى صفحة المنتج وكارت المتجر، وما يُبحث ويُقارَن به بين المحلات. الحقول التى يدخلها التاجر لكل وحدة (سنة السيارة، كيلومتراتها، لونها) ليست هنا.') }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <form method="GET" action="{{ route('admin.catalog-model-values.index') }}" class="a2-filterbar">
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('نوع التفاصيل') }}</label>
                <select class="a2-select" name="profile" onchange="this.form.shelf.value='';this.form.submit()">
                    @foreach($profiles as $p)
                        <option value="{{ $p->code }}" @selected($profile && $profile->id === $p->id)>{{ $p->name_ar }}</option>
                    @endforeach
                </select>
            </div>
            @if($shelves->count() > 1)
                <div class="a2-filter-md">
                    <label class="a2-label">{{ __('الرف') }}</label>
                    <select class="a2-select" name="shelf" onchange="this.form.submit()">
                        <option value="">{{ __('كل الرفوف') }}</option>
                        @foreach($shelves as $s)
                            <option value="{{ $s->id }}" @selected($shelf === (int) $s->id)>{{ $s->name_ar }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('بحث') }}</label>
                <input class="a2-input" type="search" name="q" value="{{ $search }}" placeholder="{{ __('اسم الموديل…') }}">
            </div>
            <div class="a2-filter-md" style="align-self:flex-end">
                <label class="a2-checkbox"><input type="checkbox" name="missing" value="1" @checked($missing) onchange="this.form.submit()"> {{ __('الناقص فقط') }}</label>
            </div>
        </form>
    </div>

    @if(! $profile)
        <div class="a2-card cmv-note">{{ __('لا توجد أنواع تفاصيل بعد — أنشئها من «أشكال المنيو».') }}</div>
    @elseif(! $profile->uses_catalog)
        <div class="a2-card cmv-note">{{ __('«:kind» بلا كتالوج — التاجر يسمّى الصنف بنفسه ويدخل كل حقوله، فلا موديلات هنا. غيّر ذلك من «أشكال المنيو» إن كان له كتالوج.', ['kind' => $profile->name_ar]) }}</div>
    @elseif($fields === [])
        <div class="a2-card cmv-note">
            {{ __('كل حقول «:kind» يدخلها التاجر لكل وحدة', ['kind' => $profile->name_ar]) }}
            @if($perItem->isNotEmpty()) ({{ $perItem->pluck('name')->implode('، ') }}) @endif
            — {{ __('فلا قيم للموديل نفسه هنا. غيّر ذلك من «أشكال المنيو» (عمود «لكل وحدة») إن أردت قيمًا ثابتة للموديل.') }}
        </div>
    @elseif($products->isEmpty())
        <div class="a2-card cmv-note">{{ __('لا توجد موديلات على رفوف هذا النوع.') }}</div>
    @else
        <form method="POST" action="{{ route('admin.catalog-model-values.save') }}">
            @csrf
            <input type="hidden" name="profile" value="{{ $profile->code }}">

            <div class="a2-card">
                <div class="cmv-wrap">
                    <table class="a2-table cmv-table">
                        <thead>
                            <tr>
                                <th>{{ __('الموديل') }}</th>
                                @foreach($fields as $f)
                                    <th>{{ $f['name'] }}@if($f['unit']) <span class="cmv-sub">({{ $f['unit'] }})</span>@endif</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $p)
                                @php $row = $values[$p->id] ?? []; @endphp
                                <tr class="{{ count($row) === count($fields) ? 'cmv-filled' : '' }}">
                                    <td>
                                        <div class="cmv-name">{{ $p->name_ar ?: $p->name_en }}</div>
                                        <div class="cmv-sub">{{ $p->brand }}@if($p->series) · {{ $p->series }}@endif</div>
                                    </td>
                                    @foreach($fields as $f)
                                        @php $v = $row[$f['id']] ?? null; $name = "values[{$p->id}][{$f['id']}]"; @endphp
                                        <td>
                                            @if($f['data_type'] === 'select')
                                                <select class="a2-select" name="{{ $name }}">
                                                    <option value="">—</option>
                                                    @foreach($f['options'] as $o)
                                                        <option value="{{ $o['id'] }}" @selected($v && (int) $v->option_id === $o['id'])>{{ $o['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($f['data_type'] === 'number')
                                                <input class="a2-input" type="number" step="any" min="0" name="{{ $name }}" value="{{ $v?->value_number !== null ? rtrim(rtrim(number_format((float) $v->value_number, 4, '.', ''), '0'), '.') : '' }}">
                                            @else
                                                <input class="a2-input" name="{{ $name }}" value="{{ $v?->value_text_ar ?: $v?->value_text_en }}">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;gap:12px;align-items:center;margin-top:12px">
                    <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ قيم هذه الصفحة') }}</button>
                    <span class="cmv-note">{{ __('خانة فارغة = بلا قيمة (تُحذف إن كانت موجودة).') }} {{ $products->total() }} {{ __('موديل') }}</span>
                </div>
            </div>
        </form>

        <div style="margin-top:12px">{{ $products->links() }}</div>
    @endif
</div>
@endsection
