@extends('business.layouts.master')

@section('title', __('مراجعة') . ' — ' . __($catalogLabel))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __($catalogLabel) }} — {{ __('مراجعة') }}</h1>
        <div class="a2-page-subtitle">
            {{ __('قائمتك كاملة: القسم، ثم البند، ثم أصنافه. والبندُ الفارغ بندٌ يحقّ لك بيعه ولم تعرض تحته شيئًا بعد.') }}
        </div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('الأصناف') }}</a>
        <a href="{{ route('business.menu.create') }}" class="a2-btn a2-btn-primary">{{ __('إضافة صنف') }}</a>
    </div>
</div>

@include('shared.menu-outline', [
    'outline' => $outline,
    'editRoute' => fn ($id) => route('business.menu.edit', $id),
])
@endsection
