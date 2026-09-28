@extends('admin-v2.layouts.master')

@section('title','New Catalog Product')
@section('body_class','admin-v2-catalog-products-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('منتج جديد بالكاتالوج') }}</h1>
            <div class="a2-page-subtitle">{{ __('موديل حقيقي بمواصفاته — لاب توب، موبايل، جهاز كهربائي…') }}</div>
        </div>
    </div>

    @if(session('error'))
        <div class="a2-alert a2-alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.catalog-products.store') }}">
        @csrf
        @include('admin-v2.catalog-products._form', [
            'row' => null,
            'categories' => $categories,
            'children' => $children,
            'brandOptions' => $brandOptions,
            'unitOptions' => $unitOptions,
            'attributeOptions' => $attributeOptions,
            'specs' => collect(),
            'blankSpecRows' => $blankSpecRows,
        ])
    </form>
</div>
@endsection
