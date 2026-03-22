@extends('admin-v2.layouts.master')

@section('title', 'إضافة قسم رئيسي')
@section('body_class', 'admin-v2 admin-v2-categories-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة قسم رئيسي</h1>
            <div class="a2-page-subtitle">
                إنشاء قسم رئيسي جديد وربطه بالخدمات والإعدادات الخاصة بها
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card">
        <form method="POST"
              action="{{ route('admin.categories.store') }}"
              enctype="multipart/form-data">
            @csrf

            @include('admin-v2.categories._form', [
                'row' => $row,
                'rootId' => $rootId ?? 0,
                'backUrl' => $backUrl ?? route('admin.categories.index'),
                'platformServices' => $platformServices ?? collect(),
                'selectedPlatformServices' => $selectedPlatformServices ?? [],
                'bookingConfig' => $bookingConfig ?? [],
                'menuConfig' => $menuConfig ?? [],
                'deliveryConfig' => $deliveryConfig ?? [],
            ])
        </form>
    </div>
</div>
@endsection