@extends('admin-v2.layouts.master')

@section('title', 'Edit Guarantee Level')
@section('topbar_title', 'Edit Guarantee Level')
@section('body_class', 'admin-v2-guarantee-levels')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل مستوى الضمان #{{ $level->id }}</h1>
            <div class="a2-page-subtitle">تعديل الرصيد المطلوب، قدرة التغطية، وشروط التأهيل لهذا المستوى.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.guarantee-levels.index') }}" class="a2-btn a2-btn-ghost">رجوع للمستويات</a>
            <a href="{{ route('admin.guarantees.index', ['level_id' => $level->id]) }}" class="a2-btn a2-btn-primary">ضمانات هذا المستوى</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.guarantee-levels.update', $level->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.guarantee-levels._form', ['level' => $level])
    </form>
</div>
@endsection
