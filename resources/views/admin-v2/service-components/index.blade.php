@extends('admin-v2.layouts.master')

@section('title', 'Service Components')
@section('body_class', 'admin-v2 admin-v2-service-components')

@section('content')
@php
    use App\Models\ServiceOptionGroupPlacement as P;

    $surfaceLabels = [
        P::SURFACE_ITEM_FORM => 'نموذج إضافة الصنف',
        P::SURFACE_PRICING => 'شاشة التسعير',
        P::SURFACE_SEARCH_FILTER => 'فلتر بحث العميل',
        P::SURFACE_RESULT_CARD => 'كارت النتيجة',
        P::SURFACE_ITEM_DETAIL => 'الوصف / تفاصيل الصنف',
        P::SURFACE_BUSINESS_PAGE => 'صفحة البزنس',
    ];
    $usageLabels = [
        P::USAGE_DEFINES_ITEM => 'مسعَّر — هو الصنف نفسه (السطر)',
        P::USAGE_CHANGES_PRICE => 'معدِّل للسعر — يزيد/ينقص سعر المسعَّر',
        P::USAGE_DESCRIPTIVE => 'وصفي — يظهر فى الوصف',
        P::USAGE_FILTER_ONLY => 'فلتر بحث فقط',
    ];
    $inputLabels = [P::INPUT_SINGLE => 'اختيار واحد', P::INPUT_MULTIPLE => 'اختيار متعدد', P::INPUT_CHECKBOX => 'Checkbox'];
    $roleLabel = ['line' => 'مسعَّر', 'modifier' => 'معدِّل', 'descriptive' => 'وصفي'];
    $name = fn ($m) => trim((string) ($m->name_ar ?? '')) ?: (trim((string) ($m->name_en ?? '')) ?: ('#' . $m->id));
    $serviceIdx = $services->pluck('id')->search($serviceId);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مكونات الخدمة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر الأب والابن، ثم خدمة خدمة: حدّد لكل مجموعة خيارات إن كانت المسعَّر أو معدِّلًا للسعر أو وصفًا، وأين تظهر. الإعدادات لهذا الابن فقط.') }}
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
        <form method="GET" action="{{ route('admin.service-components.index') }}" class="a2-filterbar">
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('الأب') }}</label>
                <select class="a2-select" name="root_id" onchange="this.form.child_id.value=0;this.form.service_id.value=0;this.form.submit()">
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}" @selected($rootId === (int) $root->id)>{{ $name($root) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="a2-filter-md">
                <label class="a2-label">{{ __('الابن') }}</label>
                <select class="a2-select" name="child_id" onchange="this.form.service_id.value=0;this.form.submit()">
                    @foreach($children as $child)
                        <option value="{{ $child->id }}" @selected($childId === (int) $child->id)>{{ $name($child) }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="service_id" value="{{ $serviceId }}">
        </form>

        <div style="margin-top:12px">
            <span class="a2-label">{{ __('الخدمات المتاحة لهذا الابن') }}</span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                @forelse($services as $service)
                    <a class="a2-btn {{ $serviceId === (int) $service->id ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
                       href="{{ route('admin.service-components.index', ['root_id' => $rootId, 'child_id' => $childId, 'service_id' => $service->id]) }}">
                        {{ $name($service) }}
                    </a>
                @empty
                    <span class="a2-muted">{{ __('لا توجد خدمات مفعّلة لهذا الابن تحت هذا الأب.') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    @if($serviceId > 0)
        <form method="POST" action="{{ route('admin.service-components.save') }}">
            @csrf
            <input type="hidden" name="root_id" value="{{ $rootId }}">
            <input type="hidden" name="child_id" value="{{ $childId }}">
            <input type="hidden" name="service_id" value="{{ $serviceId }}">

            <div class="a2-card a2-mb-16">
                <div class="a2-table-wrap">
                    <table class="a2-table">
                        <thead>
                            <tr>
                                <th>{{ __('البند (مجموعة الخيارات)') }}</th>
                                <th>{{ __('هو فى الخدمة') }}</th>
                                <th>{{ __('أين يظهر') }}</th>
                                <th>{{ __('نوع الإدخال') }}</th>
                                <th>{{ __('إلزامي') }}</th>
                                <th>{{ __('مفعّل') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groups as $i => $group)
                                @php
                                    $row = $saved->get($group->id);
                                    [$defUsage, $defSurfaces] = $roleDefaults[$group->price_role] ?? [P::USAGE_DESCRIPTIVE, [P::SURFACE_ITEM_FORM]];
                                    $usage = $row->usage ?? $defUsage;
                                    $surfaces = $row ? (array) $row->surfaces : $defSurfaces;
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][option_group_id]" value="{{ $group->id }}">
                                        <div class="a2-fw-900">{{ $name($group) }}</div>
                                        <div class="a2-muted">{{ __('نوعها الحالي') }}: {{ $roleLabel[$group->price_role] ?? $group->price_role }}@if(! $row) — {{ __('لم يُحفظ بعد') }}@endif</div>
                                    </td>
                                    <td>
                                        <select class="a2-select" name="rows[{{ $i }}][usage]">
                                            @foreach($usageLabels as $key => $label)
                                                <option value="{{ $key }}" @selected($usage === $key)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @foreach($surfaceLabels as $key => $label)
                                            <label style="display:block;white-space:nowrap">
                                                <input type="checkbox" name="rows[{{ $i }}][surfaces][]" value="{{ $key }}" @checked(in_array($key, $surfaces, true))>
                                                {{ __($label) }}
                                            </label>
                                        @endforeach
                                    </td>
                                    <td>
                                        <select class="a2-select" name="rows[{{ $i }}][input_type]">
                                            @foreach($inputLabels as $key => $label)
                                                <option value="{{ $key }}" @selected(($row->input_type ?? 'single') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][is_required]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][is_required]" value="1" @checked($row->is_required ?? false)>
                                    </td>
                                    <td>
                                        <input type="hidden" name="rows[{{ $i }}][is_active]" value="0">
                                        <input type="checkbox" name="rows[{{ $i }}][is_active]" value="1" @checked($row->is_active ?? true)>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="a2-muted">{{ __('هذا الابن لا يحمل أي مجموعة خيارات. اربطها من «خيارات التصنيفات الفرعية».') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($groups->isNotEmpty())
                <button class="a2-btn a2-btn-ghost" type="submit">{{ __('حفظ') }}</button>
                @if($serviceIdx !== false && $serviceIdx < $services->count() - 1)
                    <button class="a2-btn a2-btn-primary" type="submit" name="next" value="1">{{ __('حفظ والانتقال للخدمة التالية') }}</button>
                @endif
            @endif
        </form>
    @endif
</div>
@endsection
