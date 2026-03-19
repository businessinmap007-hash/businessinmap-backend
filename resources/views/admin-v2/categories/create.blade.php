@extends('admin-v2.layouts.master')

@section('title', 'إضافة قسم')
@section('body_class', 'admin-v2-categories-create')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة قسم جديد</h1>
        <div class="a2-page-subtitle">
            إنشاء قسم جديد وربطه بالخدمات وإعدادات الحجز
        </div>
    </div>
</div>

<div class="a2-page-card">
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.categories.store') }}"
          enctype="multipart/form-data">
        @csrf

        @include('admin-v2.categories._form', [
            'row' => $row,
            'rootId' => $rootId ?? 0,
            'backUrl' => $backUrl ?? route('admin.categories.index', !empty($rootId ?? 0) ? ['root_id' => $rootId] : []),
            'parents' => $parents ?? collect(),
            'platformServices' => $platformServices ?? collect(),
            'selectedPlatformServices' => $selectedPlatformServices ?? [],
            'bookingProfile' => $bookingProfile ?? null,
        ])
    </form>
</div>
@endsection