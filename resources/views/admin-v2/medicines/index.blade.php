@extends('admin-v2.layouts.master')

@section('title', 'قاموس الأدوية')
@section('body_class', 'admin-v2 admin-v2-medicines')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('قاموس الأدوية') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('ما يكتبه الطبيب في الروشتة يُقترح من هنا. القاموس ينمو وحده مع كل وصفة — والرفع يملؤه دفعة واحدة.') }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success" style="white-space:pre-wrap;">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger" style="white-space:pre-wrap;">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div class="a2-card" style="flex:1;min-width:180px;">
            <div class="a2-muted" style="font-size:12px;">{{ __('إجمالي الأصناف') }}</div>
            <div style="font-size:26px;font-weight:700;">{{ number_format($total) }}</div>
        </div>
        <div class="a2-card" style="flex:1;min-width:180px;">
            <div class="a2-muted" style="font-size:12px;">{{ __('كُتب في روشتة فعلًا') }}</div>
            <div style="font-size:26px;font-weight:700;">{{ number_format($prescribed) }}</div>
        </div>
    </div>

    {{-- «هل يمكن اضافة جدول ونضيف به جميع الادوية الموجودة فى مصر».

         The parsing lives in `medicines:import` and this screen calls it, so
         the panel and the console can never disagree about what a valid row
         is — and nothing here invents a drug: only what is in the file. --}}
    <div class="a2-card" style="margin-bottom:16px;">
        <div class="a2-section-head">
            <div>
                <h2 class="a2-section-title">{{ __('رفع سجل أدوية') }}</h2>
                <div class="a2-section-subtitle">
                    {{ __('ملف CSV أو JSON. يكفي عمود للاسم (name / trade name / الدواء)، والتركيز اختياري (strength / التركيز).') }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.medicines.import') }}" enctype="multipart/form-data">
            @csrf

            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:240px;">
                    <label class="a2-label" for="file">{{ __('الملف') }}</label>
                    <input class="a2-input" type="file" id="file" name="file" accept=".csv,.txt,.json" required>
                </div>

                <label class="a2-check-card">
                    <input type="checkbox" name="dry_run" value="1" checked>
                    <span>{{ __('معاينة أولًا (لا يُكتب شيء)') }}</span>
                </label>

                <button type="submit" class="a2-btn a2-btn-primary">{{ __('رفع') }}</button>
            </div>

            <div class="a2-muted a2-mt-12" style="font-size:12px;">
                {{ __('إعادة رفع نفس الملف آمنة — الاسم والتركيز معًا هما الهوية، فلا يتكرر صنف.') }}
            </div>
        </form>
    </div>

    <div class="a2-card">
        <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;">
            <input class="a2-input" name="q" value="{{ $q }}" placeholder="{{ __('ابحث باسم الدواء') }}" style="max-width:320px;">
            <button class="a2-btn a2-btn-ghost">{{ __('بحث') }}</button>
        </form>

        @if($rows->isEmpty())
            <div class="a2-muted">
                {{ $q !== '' ? __('لا نتائج لهذا البحث.') : __('القاموس فارغ — ارفع سجلًا بالأعلى، أو اتركه ينمو مع أول روشتة.') }}
            </div>
        @else
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>{{ __('الدواء') }}</th>
                            <th>{{ __('التركيز') }}</th>
                            <th>{{ __('مرات الكتابة') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->strength ?: '—' }}</td>
                                <td>{{ (int) $row->uses_count }}</td>
                                <td style="text-align:end;">
                                    @if((int) $row->uses_count === 0)
                                        <form method="POST" action="{{ route('admin.medicines.destroy', $row->id) }}"
                                              onsubmit="return confirm('{{ __('حذف هذا الدواء من القاموس؟') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="a2-btn a2-btn-ghost">{{ __('حذف') }}</button>
                                        </form>
                                    @else
                                        <span class="a2-muted" style="font-size:12px;">{{ __('مستعمل في روشتات') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="a2-mt-12">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
