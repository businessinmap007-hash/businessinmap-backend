@extends('admin-v2.layouts.master')

@section('title', 'تعديل قسم فرعي')
@section('body_class', 'admin-v2 admin-v2-category-children-edit')

@section('content')
<div class="a2-page">
   

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

    <form method="POST" action="{{ route('admin.category-children.update', $row->id) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.category-children._form', [
            'mode' => 'edit',
            'submitLabel' => 'حفظ التعديلات',
        ])
       
    </div>
    </form>
     
</div>
@endsection