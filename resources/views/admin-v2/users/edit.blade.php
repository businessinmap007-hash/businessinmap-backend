@extends('admin-v2.layouts.master')

@section('title','Edit User')
@section('body_class','admin-v2 admin-v2-users-edit')

@section('content')
@php
    $id = (int) $user->id;
    $isTrashed = method_exists($user, 'trashed') ? (bool) $user->trashed() : false;
@endphp

<div class="a2-page" style="max-width:1100px;margin:0 auto;">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">
                تعديل المستخدم <span class="a2-muted">#{{ $id }}</span>
            </h1>
            <div class="a2-page-subtitle">
                @if($isTrashed)
                    <span class="a2-pill a2-pill-danger">محذوف (Soft)</span>
                @else
                    <span class="a2-pill a2-pill-success">نشط</span>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.users.show', $id) }}" class="a2-btn a2-btn-ghost">عرض المستخدم</a>
            <a href="{{ route('admin.users.index') }}" class="a2-btn a2-btn-ghost">القائمة</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $id) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.users._form', [
            'user' => $user,
            'categories' => $categories ?? collect(),
            'children' => $children ?? collect(),
            'groups' => $groups ?? collect(),
            'ungroupedOptions' => $ungroupedOptions ?? collect(),
            'selectedOptionIds' => $selectedOptionIds ?? [],
            'childCatalog' => $childCatalog ?? [],
            'optionCatalog' => $optionCatalog ?? [],
            'submitLabel' => 'حفظ'
        ])
    </form>
</div>
@endsection