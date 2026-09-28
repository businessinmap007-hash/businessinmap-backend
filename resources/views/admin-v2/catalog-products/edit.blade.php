@extends('admin-v2.layouts.master')

@section('title','Edit Catalog Product')
@section('body_class','admin-v2-catalog-products-edit')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل') }} — {{ $row->name_ar ?: $row->name_en }}</h1>
            <div class="a2-page-subtitle" dir="ltr">{{ $row->bim_code }}</div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="a2-alert a2-alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.catalog-products.update', $row->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.catalog-products._form', [
            'row' => $row,
            'productCategories' => $productCategories,
            'children' => $children,
            'brandOptions' => $brandOptions,
            'unitOptions' => $unitOptions,
            'attributeOptions' => $attributeOptions,
            'specs' => $specs,
            'blankSpecRows' => $blankSpecRows,
        ])
    </form>
</div>
@endsection
