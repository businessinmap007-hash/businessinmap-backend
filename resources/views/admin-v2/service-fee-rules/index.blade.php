@extends('admin-v2.layouts.master')

@section('title', __('قواعد الرسوم الديناميكية'))
@section('topbar_title', __('قواعد الرسوم الديناميكية'))
@section('body_class', 'admin-v2-service-fee-rules')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('قواعد الرسوم الديناميكية') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('الرسوم الأساسية تحدد سعر الخدمة عمومًا؛ هذه القواعد تسعّر العملية نفسها — حسب قيمتها ومكانها ووقتها وسجل الطرف واشتراكه. تُطبَّق بالترتيب بعد الرسوم الأساسية وقبل عروض الخصم.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.service-fee-rules.create') }}" class="a2-btn a2-btn-primary">{{ __('قاعدة جديدة') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.service-fee-rules.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('بحث بالاسم أو الملاحظات') }}">

            <select class="a2-select a2-filter-sm" name="platform_service_id">
                <option value="">{{ __('كل الخدمات') }}</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" {{ (int) $filters['platform_service_id'] === (int) $service->id ? 'selected' : '' }}>
                        {{ $service->name_ar ?: $service->key }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="payer">
                <option value="">{{ __('كل الأطراف') }}</option>
                @foreach($payers as $key => $label)
                    <option value="{{ $key }}" {{ $filters['payer'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select" name="effect">
                <option value="">{{ __('كل التأثيرات') }}</option>
                @foreach($effects as $key => $label)
                    <option value="{{ $key }}" {{ $filters['effect'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="active">
                <option value="">{{ __('الكل') }}</option>
                <option value="1" {{ $filters['active'] === '1' ? 'selected' : '' }}>{{ __('مفعلة') }}</option>
                <option value="0" {{ $filters['active'] === '0' ? 'selected' : '' }}>{{ __('موقوفة') }}</option>
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service-fee-rules.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('الأولوية') }}</th>
                        <th>{{ __('الاسم') }}</th>
                        <th>{{ __('النطاق') }}</th>
                        <th>{{ __('الطرف') }}</th>
                        <th>{{ __('التأثير') }}</th>
                        <th>{{ __('الشروط') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th class="a2-text-right">{{ __('إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td>{{ $rule->id }}</td>
                            <td>
                                {{ $rule->priority }}
                                @if($rule->stop_on_match)
                                    <span class="a2-pill a2-pill-sub" title="{{ __('لا تُطبَّق قواعد بعدها') }}">{{ __('يقف هنا') }}</span>
                                @endif
                            </td>
                            <td class="a2-text-right a2-fw-900">{{ $rule->name }}</td>
                            <td>{{ optional($rule->platformService)->name_ar ?: 'كل الخدمات' }}</td>
                            <td>{{ $payers[$rule->payer] ?? $rule->payer }}</td>
                            <td>
                                {{ $effects[$rule->effect] ?? $rule->effect }}
                                @if($rule->effect_value !== null)
                                    <span class="a2-fw-900">{{ rtrim(rtrim(number_format((float) $rule->effect_value, 2), '0'), '.') }}</span>
                                @endif
                                @if($rule->min_fee !== null || $rule->max_fee !== null)
                                    <div class="a2-muted">
                                        {{ __('حد') }} {{ $rule->min_fee !== null ? 'أدنى ' . rtrim(rtrim(number_format((float) $rule->min_fee, 2), '0'), '.') : '' }}
                                        {{ $rule->max_fee !== null ? 'أقصى ' . rtrim(rtrim(number_format((float) $rule->max_fee, 2), '0'), '.') : '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="a2-text-right">
                                @php $conditions = is_array($rule->conditions) ? $rule->conditions : []; @endphp
                                @if(empty($conditions))
                                    <span class="a2-muted">{{ __('بدون شروط (كل النطاق)') }}</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">{{ count($conditions) }} {{ __('شرط') }}</span>
                                    <div class="a2-muted" style="font-size:11px;">{{ implode('، ', array_keys($conditions)) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="a2-pill {{ $rule->is_active ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                    {{ $rule->is_active ? 'مفعلة' : 'موقوفة' }}
                                </span>
                            </td>
                            <td class="a2-text-right">
                                <div class="a2-inline-actions" style="align-items:center;">
                                    <a href="{{ route('admin.service-fee-rules.edit', $rule->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                                    <form method="POST" action="{{ route('admin.service-fee-rules.toggle', $rule->id) }}">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">{{ $rule->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.service-fee-rules.destroy', $rule->id) }}" onsubmit="return confirm('حذف هذه القاعدة؟ ستعود الرسوم لقيمتها الأساسية.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">{{ __('لا قواعد. بدون قواعد تبقى الرسوم كما حددتها الرسوم الأساسية تمامًا.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rules->links() }}</div>
    </div>
</div>
@endsection
