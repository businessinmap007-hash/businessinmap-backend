@extends('admin-v2.layouts.master')

@section('title', 'Create Fee Group')
@section('body_class', 'admin-v2 admin-v2-fee-groups-create')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مجموعة رسوم جديدة') }}</h1>
            <div class="a2-page-subtitle">{{ __('رسمٌ واحد يشترك فيه عدة أبناء دفعة واحدة.') }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.fee-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.fee-groups.store') }}">
        @csrf
        @include('admin-v2.fee-groups._form', ['row' => $row])
    </form>
</div>
@endsection
