@extends('admin-v2.layouts.master')

@section('title', 'Fee Groups')
@section('body_class', 'admin-v2 admin-v2-fee-groups-index')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مجموعات الرسوم') }}</h1>
            <div class="a2-page-subtitle">{{ __('رسمٌ واحد يشترك فيه عدة أبناء — عدّله هنا فيتحرّك كل أعضائه معًا.') }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.fee-groups.create') }}" class="a2-btn a2-btn-primary">{{ __('مجموعة جديدة') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <form method="GET" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">{{ __('بحث') }}</label>
                <input class="a2-input" name="q" value="{{ $q }}" placeholder="{{ __('اسم المجموعة') }}">
            </div>
            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تصفية') }}</button>
                <a href="{{ route('admin.fee-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('إعادة') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('الاسم') }}</th>
                        <th>{{ __('على التاجر') }}</th>
                        <th>{{ __('على العميل') }}</th>
                        <th>{{ __('الأعضاء') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th class="a2-text-right">{{ __('إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td class="a2-fw-900">{{ $row->name_ar }}</td>
                            <td>
                                @if($row->business_fee_enabled)
                                    {{ number_format((float) $row->business_fee_amount, 2) }}
                                    {{ $row->business_fee_type === 'percent' ? '%' : $row->currency }}
                                @else
                                    <span class="a2-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row->client_fee_enabled)
                                    {{ number_format((float) $row->client_fee_amount, 2) }}
                                    {{ $row->client_fee_type === 'percent' ? '%' : $row->currency }}
                                @else
                                    <span class="a2-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $row->members_count }}</span>
                            </td>
                            <td>
                                @if($row->is_active)
                                    <span class="a2-pill a2-pill-success">{{ __('مفعّلة') }}</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">{{ __('معطّلة') }}</span>
                                @endif
                            </td>
                            <td class="a2-text-right">
                                <div class="a2-inline-actions">
                                    <a href="{{ route('admin.fee-groups.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                                    <form method="POST" action="{{ route('admin.fee-groups.destroy', $row->id) }}"
                                          onsubmit="return confirm({{ Js::from(__('حذف مجموعة الرسوم؟')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="a2-empty">{{ __('لا توجد مجموعات رسوم بعد.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($rows, 'links'))
            <div class="a2-pagination">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
