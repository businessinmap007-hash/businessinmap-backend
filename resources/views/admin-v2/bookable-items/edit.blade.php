@extends('admin-v2.layouts.master')

@section('title', 'Edit Bookable Item')
@section('body_class', 'admin-v2-bookable-items-edit')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل عنصر قابل للحجز</h1>
            <div class="a2-page-subtitle">
                {{ $row->title ?: ('#' . $row->id) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">
                رجوع
            </a>
        </div>
    </div>

    @include('admin-v2.bookable-items.partials.tabs', ['item' => $row])

    @if(session('success'))
        <div class="a2-alert a2-alert-success a2-mb-12">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="a2-alert a2-alert-danger a2-mb-12">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء</div>
            <ul class="a2-errors-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookable-items.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.bookable-items._form', [
            'row' => $row,
            'businesses' => $businesses,
            'services' => $services,
            'allowedItemTypes' => $allowedItemTypes ?? [],
            'submitLabel' => 'تحديث',
        ])
    </form>
</div>
@endsection