@extends('admin-v2.layouts.master')

@section('title',__('المسميات الوظيفية'))
@section('body_class','admin-v2-job-titles')

@section('content')
@php
    $scopeOptions = function ($selected) use ($roots, $children) {
        $html = '<option value="g"'.($selected === 'g' ? ' selected' : '').'>'.e(__('عام (كل الجهات)')).'</option>';
        $html .= '<optgroup label="'.e(__('تصنيف كامل')).'">';
        foreach ($roots as $r) { $v = 'r:'.$r->id; $html .= '<option value="'.$v.'"'.($selected === $v ? ' selected' : '').'>'.e($r->name_ar).'</option>'; }
        $html .= '</optgroup><optgroup label="'.e(__('نشاط بعينه')).'">';
        foreach ($children as $c) { $v = 'c:'.$c->id; $html .= '<option value="'.$v.'"'.($selected === $v ? ' selected' : '').'>'.e($c->name_ar).'</option>'; }
        return $html.'</optgroup>';
    };
@endphp

<div class="a2-page">
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-title">{{ __('المسميات الوظيفية') }}</h2>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index') }}">{{ __('الوظائف') }}</a>
        </div>

        @if(session('success'))<div class="a2-alert a2-alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>@endif

        <p style="opacity:.75;margin:0 0 12px">{{ __('القائمة التي يختار منها صاحب النشاط مسمى الوظيفة عند الإعلان (طباخ، ويتر…). المسمى «العام» يظهر لكل الأنشطة.') }}</p>

        <form method="POST" action="{{ route('admin.job-titles.store') }}" class="a2-filterbar">
            @csrf
            <select class="a2-input" name="scope" required>{!! $scopeOptions('g') !!}</select>
            <input class="a2-input" name="name_ar" required maxlength="120" placeholder="{{ __('المسمى بالعربية') }}">
            <input class="a2-input" name="name_en" maxlength="120" dir="ltr" placeholder="English">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('إضافة') }}</button>
        </form>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.job-titles.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث في المسميات') }}">
            <select class="a2-input" name="scope"><option value="">{{ __('كل النطاقات') }}</option>{!! $scopeOptions($scope) !!}</select>
            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.job-titles.index') }}">{{ __('تفريغ') }}</a>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th>{{ __('النطاق') }}</th>
                    <th>{{ __('المسمى') }}</th>
                    <th>English</th>
                    <th style="width:90px;">{{ __('الترتيب') }}</th>
                    <th style="width:80px;">{{ __('نشط') }}</th>
                    <th style="width:170px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($titles as $t)
                    <tr>
                        <td>{{ $t->categoryChild?->name_ar ?: ($t->category?->name_ar ? $t->category->name_ar.' — '.__('كامل') : __('عام')) }}</td>
                        <td><input class="a2-input" form="jt-{{ $t->id }}" name="name_ar" value="{{ $t->name_ar }}" required maxlength="120"></td>
                        <td><input class="a2-input" form="jt-{{ $t->id }}" name="name_en" value="{{ $t->name_en }}" maxlength="120" dir="ltr"></td>
                        <td><input class="a2-input" form="jt-{{ $t->id }}" type="number" name="sort_order" value="{{ $t->sort_order }}" min="0"></td>
                        <td><input type="checkbox" form="jt-{{ $t->id }}" name="is_active" value="1" @checked($t->is_active)></td>
                        <td>
                            <button type="submit" form="jt-{{ $t->id }}" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
                            <button type="submit" form="jt-del-{{ $t->id }}" class="a2-btn a2-btn-ghost" onclick="return confirm('{{ __('حذف المسمى؟') }}')">{{ __('حذف') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty-cell">{{ __('لا يوجد بيانات') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Forms live outside the table: a <form> between <tr>/<td> is invalid HTML and browsers eject it. --}}
        @foreach($titles as $t)
            <form method="POST" action="{{ route('admin.job-titles.update', $t) }}" id="jt-{{ $t->id }}">@csrf @method('PUT')</form>
            <form method="POST" action="{{ route('admin.job-titles.destroy', $t) }}" id="jt-del-{{ $t->id }}">@csrf @method('DELETE')</form>
        @endforeach

        <div class="a2-paginate">{{ $titles->links() }}</div>
    </div>
</div>
@endsection
