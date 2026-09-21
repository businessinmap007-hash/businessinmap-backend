@extends('admin-v2.layouts.master')

@section('title',__('مكتبة التمارين'))
@section('body_class','admin-v2-exercise-library')

@section('content')
@php
    $opts = function (array $map, $selected, bool $blank = false) {
        $html = $blank ? '<option value="">—</option>' : '';
        foreach ($map as $key => $names) {
            $html .= '<option value="'.e($key).'"'.($selected === $key ? ' selected' : '').'>'.e($names['ar']).'</option>';
        }
        return $html;
    };
    $catOpts = function ($selected, bool $blank = false) use ($categories) {
        $html = $blank ? '<option value="0">'.e(__('كل الأقسام')).'</option>' : '';
        foreach ($categories as $c) {
            $html .= '<option value="'.$c->id.'"'.((int) $selected === (int) $c->id ? ' selected' : '').'>'.e($c->name_ar).'</option>';
        }
        return $html;
    };
@endphp

<div class="a2-page">
    <div class="a2-card">
        <div class="a2-header">
            <h2 class="a2-title">{{ __('مكتبة التمارين') }}</h2>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.training-plans.index') }}">{{ __('خطط التدريب') }}</a>
        </div>

        @if(session('success'))<div class="a2-alert a2-alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>@endif

        <p style="opacity:.75;margin:0 0 12px">{{ __('القائمة التي يختار منها المدرب التمرين عند بناء الخطة أو القالب. الخطط القائمة تحتفظ بنسختها الخاصة، فتعديل التمرين هنا لا يغيّرها.') }}</p>

        <form method="POST" action="{{ route('admin.exercise-library.exercises.store') }}" class="a2-filterbar">
            @csrf
            <select class="a2-input" name="exercise_category_id" required>{!! $catOpts($categoryId) !!}</select>
            <input class="a2-input" name="name_ar" required maxlength="160" placeholder="{{ __('اسم التمرين') }}">
            <input class="a2-input" name="name_en" maxlength="160" dir="ltr" placeholder="English">
            <select class="a2-input" name="kind" required>{!! $opts($kinds, 'strength') !!}</select>
            <select class="a2-input" name="equipment">{!! $opts($equipment, null, true) !!}</select>
            <input class="a2-input" type="number" name="default_sets" min="0" max="100" placeholder="{{ __('مجموعات') }}" style="max-width:110px">
            <input class="a2-input" name="default_reps" maxlength="40" placeholder="{{ __('تكرارات') }}" style="max-width:130px">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('إضافة') }}</button>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-header"><h2 class="a2-title">{{ __('الأقسام') }}</h2></div>

        <form method="POST" action="{{ route('admin.exercise-library.categories.store') }}" class="a2-filterbar">
            @csrf
            <input class="a2-input" name="name_ar" required maxlength="120" placeholder="{{ __('اسم القسم') }}">
            <input class="a2-input" name="name_en" maxlength="120" dir="ltr" placeholder="English">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('إضافة قسم') }}</button>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead><tr>
                    <th>{{ __('القسم') }}</th><th>English</th>
                    <th style="width:90px;">{{ __('الترتيب') }}</th><th style="width:90px;">{{ __('التمارين') }}</th>
                    <th style="width:80px;">{{ __('نشط') }}</th><th style="width:170px;"></th>
                </tr></thead>
                <tbody>
                @foreach($categories as $c)
                    <tr>
                        <td><input class="a2-input" form="cat-{{ $c->id }}" name="name_ar" value="{{ $c->name_ar }}" required maxlength="120"></td>
                        <td><input class="a2-input" form="cat-{{ $c->id }}" name="name_en" value="{{ $c->name_en }}" maxlength="120" dir="ltr"></td>
                        <td><input class="a2-input" form="cat-{{ $c->id }}" type="number" name="sort_order" value="{{ $c->sort_order }}" min="0"></td>
                        <td>{{ $c->exercises_count }}</td>
                        <td><input type="checkbox" form="cat-{{ $c->id }}" name="is_active" value="1" @checked($c->is_active)></td>
                        <td>
                            <button type="submit" form="cat-{{ $c->id }}" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
                            <button type="submit" form="cat-del-{{ $c->id }}" class="a2-btn a2-btn-ghost" onclick="return confirm('{{ __('حذف القسم؟') }}')">{{ __('حذف') }}</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @foreach($categories as $c)
            <form method="POST" action="{{ route('admin.exercise-library.categories.update', $c) }}" id="cat-{{ $c->id }}">@csrf @method('PUT')</form>
            <form method="POST" action="{{ route('admin.exercise-library.categories.destroy', $c) }}" id="cat-del-{{ $c->id }}">@csrf @method('DELETE')</form>
        @endforeach
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.exercise-library.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث في التمارين') }}">
            <select class="a2-input" name="category_id">{!! $catOpts($categoryId, true) !!}</select>
            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.exercise-library.index') }}">{{ __('تفريغ') }}</a>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead><tr>
                    <th>{{ __('القسم') }}</th><th>{{ __('التمرين') }}</th><th>English</th>
                    <th>{{ __('النوع') }}</th><th>{{ __('الأداة') }}</th>
                    <th style="width:80px;">{{ __('مجموعات') }}</th><th style="width:110px;">{{ __('تكرارات') }}</th>
                    <th style="width:70px;">{{ __('نشط') }}</th><th style="width:170px;"></th>
                </tr></thead>
                <tbody>
                @forelse($exercises as $e)
                    <tr>
                        <td>
                            <select class="a2-input" form="ex-{{ $e->id }}" name="exercise_category_id">{!! $catOpts($e->exercise_category_id) !!}</select>
                        </td>
                        <td><input class="a2-input" form="ex-{{ $e->id }}" name="name_ar" value="{{ $e->name_ar }}" required maxlength="160"></td>
                        <td><input class="a2-input" form="ex-{{ $e->id }}" name="name_en" value="{{ $e->name_en }}" maxlength="160" dir="ltr"></td>
                        <td><select class="a2-input" form="ex-{{ $e->id }}" name="kind">{!! $opts($kinds, $e->kind) !!}</select></td>
                        <td><select class="a2-input" form="ex-{{ $e->id }}" name="equipment">{!! $opts($equipment, $e->equipment, true) !!}</select></td>
                        <td><input class="a2-input" form="ex-{{ $e->id }}" type="number" name="default_sets" value="{{ $e->default_sets }}" min="0"></td>
                        <td><input class="a2-input" form="ex-{{ $e->id }}" name="default_reps" value="{{ $e->default_reps }}" maxlength="40"></td>
                        <td><input type="checkbox" form="ex-{{ $e->id }}" name="is_active" value="1" @checked($e->is_active)></td>
                        <td>
                            <button type="submit" form="ex-{{ $e->id }}" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
                            <button type="submit" form="ex-del-{{ $e->id }}" class="a2-btn a2-btn-ghost" onclick="return confirm('{{ __('حذف التمرين؟') }}')">{{ __('حذف') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="a2-empty-cell">{{ __('لا يوجد بيانات') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Forms live outside the table: a <form> between <tr>/<td> is invalid HTML and browsers eject it. --}}
        @foreach($exercises as $e)
            <form method="POST" action="{{ route('admin.exercise-library.exercises.update', $e) }}" id="ex-{{ $e->id }}">@csrf @method('PUT')<input type="hidden" name="sort_order" value="{{ $e->sort_order }}"></form>
            <form method="POST" action="{{ route('admin.exercise-library.exercises.destroy', $e) }}" id="ex-del-{{ $e->id }}">@csrf @method('DELETE')</form>
        @endforeach

        <div class="a2-paginate">{{ $exercises->links() }}</div>
    </div>
</div>
@endsection
