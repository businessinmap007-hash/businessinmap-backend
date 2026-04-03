@extends('admin-v2.layouts.master')

@section('title','Edit Group')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <h1 class="a2-page-title">
            تعديل Group #{{ $group->id }}
        </h1>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.option-groups.update', $group->id) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.options.groups._form')
    </form>

    <div class="a2-card a2-mt-16">
        <div class="a2-card-head">
            <div class="a2-card-title">حذف المجموعة</div>
        </div>

        <form method="POST"
              action="{{ route('admin.option-groups.destroy', $group->id) }}"
              onsubmit="return confirm('تأكيد الحذف؟');">
            @csrf
            @method('DELETE')

            <button class="a2-btn a2-btn-danger">حذف</button>
        </form>
    </div>

</div>
@endsection