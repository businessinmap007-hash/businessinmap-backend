@extends('admin-v2.layouts.master')

@section('title', 'تعديل القسم')
@section('body_class', 'admin-v2-categories-edit')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل القسم</h1>
        <div class="a2-page-subtitle">
            تعديل بيانات القسم والخدمات وإعدادات الحجز
        </div>
    </div>
</div>

<div class="a2-page-card">
    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.categories.update', $row->id) }}"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')

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