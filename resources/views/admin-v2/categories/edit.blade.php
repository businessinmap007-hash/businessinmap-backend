@extends('admin-v2.layouts.master')

@section('title', 'تعديل القسم الرئيسي')
@section('body_class', 'admin-v2 admin-v2-categories-edit')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل القسم الرئيسي</h1>
        </div>
        
    </div>

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

    <div class="a2-card">
        <form method="POST"
              action="{{ route('admin.categories.update', $row->id) }}"
              enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('admin-v2.categories._form', [
                'row' => $row,
                'rootId' => $rootId ?? 0,
                'backUrl' => $backUrl ?? route('admin.categories.index', !empty($rootId ?? 0) ? ['root_id' => $rootId] : []),
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