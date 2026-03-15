@extends('admin-v2.layouts.master')

@section('title','سجلات الاشتراك')
@section('body_class','admin-v2-subscriptions')

@section('content')
@php
$qVal = (string)($q ?? '');
$categoryIdVal = (string)($category_id ?? '');
$isActiveVal = (string)($is_active ?? '');

$perPageVal = (int)($perPage ?? 50);
$sortNow = (string)($sort ?? 'id');
$dirNow = (string)($dir ?? 'desc');

$perPageOptions = [10,20,50,100];

$activeOptions = [
'' => 'الكل',
'1' => 'نشط',
'0' => 'غير نشط',
];

$sortOptions = [
'id' => 'ID',
'created_at' => 'Created',
'updated_at' => 'Updated',
'is_active' => 'Active',
'user_id' => 'User ID',
'category_id' => 'Category ID',
];
@endphp

<div class="a2-page">

<div class="a2-page-head">
<div>
<h1 class="a2-page-title">سجلات الاشتراك</h1>
<div class="a2-page-subtitle">إدارة اشتراكات المستخدمين</div>
</div>
</div>

<div class="a2-card">

<form method="GET" action="{{ route('admin.subscriptions.index') }}" class="a2-filterbar">

<input
class="a2-input a2-filter-search"
name="q"
value="{{ $qVal }}"
placeholder="بحث: الاسم / email / phone / ID / القسم"
/>

<select class="a2-select a2-filter-md" name="category_id">
<option value="">كل الأقسام</option>

@foreach(($categoriesForFilter ?? []) as $c)
@php
$isRoot = ((int)($c->parent_id ?? 0) === 0);
if(!$isRoot) continue;

$catName = (string)($c->name_ar ?? '');
$label = $catName !== '' ? $catName : ('#'.$c->id);
@endphp

<option value="{{ $c->id }}" @selected((string)$c->id === $categoryIdVal)>
{{ $label }}
</option>
@endforeach
</select>

<select class="a2-select a2-filter-sm" name="is_active">
@foreach($activeOptions as $k=>$label)
<option value="{{ $k }}" @selected($isActiveVal===(string)$k)>
{{ $label }}
</option>
@endforeach
</select>

<select class="a2-select a2-filter-sm" name="sort">
@foreach($sortOptions as $k=>$label)
<option value="{{ $k }}" @selected($sortNow===$k)>
{{ $label }}
</option>
@endforeach
</select>

<select class="a2-select a2-filter-sm" name="dir">
<option value="desc" @selected($dirNow==='desc')>DESC</option>
<option value="asc" @selected($dirNow==='asc')>ASC</option>
</select>

<select class="a2-select a2-filter-sm" name="per_page">
@foreach($perPageOptions as $n)
<option value="{{ $n }}" @selected((int)$perPageVal===(int)$n)>
{{ $n }} / صفحة
</option>
@endforeach
</select>

<div class="a2-filter-actions">
<button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

<a class="a2-btn a2-btn-ghost"
href="{{ route('admin.subscriptions.index') }}">
تفريغ
</a>
</div>

</form>


<div class="a2-table-wrap">
<table class="a2-table">

<thead>
<tr>
<th>ID</th>
<th>User</th>
<th>Category</th>
<th>Active</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

@forelse($items as $row)

@php
$userName = (string)($row->user->name ?? '');
$userLabel = $userName !== '' ? $userName : ('#'.$row->user_id);

$showUrl = route('admin.subscriptions.show',$row->id);

$userShowUrl = null;
if($row->user_id){
try{
$userShowUrl = route('admin.users.show',$row->user_id);
}catch(\Throwable $e){}
}

$catNameRow = (string)($row->category->name_ar ?? '');
$catLabel = $catNameRow !== '' ? $catNameRow : ($row->category_id ? ('#'.$row->category_id) : '—');
@endphp

<tr>

<td>
<a class="a2-link" href="{{ $showUrl }}">
{{ $row->id }}
</a>
</td>

<td>

@if($userShowUrl)
<a class="a2-link a2-clip"
href="{{ $userShowUrl }}"
title="{{ $userLabel }}">
{{ $userLabel }}
</a>
@else
<span class="a2-clip" title="{{ $userLabel }}">
{{ $userLabel }}
</span>
@endif

</td>

<td>

@if($row->category_id)
<span class="a2-pill a2-pill-gray">
{{ $catLabel }}
</span>
@else
—
@endif

</td>

<td>

@if((int)$row->is_active === 1)
<span class="a2-pill a2-pill-active">Active</span>
@else
<span class="a2-pill a2-pill-inactive">Off</span>
@endif

</td>

<td dir="ltr">
{{ $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—' }}
</td>

<td>

<div class="a2-actions">

<a class="a2-btn a2-btn-ghost a2-btn-sm"
href="{{ route('admin.subscriptions.edit',$row->id) }}">
تعديل
</a>

<form method="POST"
action="{{ route('admin.subscriptions.toggle-active',$row->id) }}">
@csrf
<button class="a2-btn a2-btn-ghost a2-btn-sm" type="submit">
{{ (int)$row->is_active ? 'إيقاف' : 'تفعيل' }}
</button>
</form>

</div>

</td>

</tr>

@empty
<tr>
<td colspan="6" class="a2-empty-cell">لا يوجد بيانات</td>
</tr>
@endforelse

</tbody>
</table>
</div>


@if(method_exists($items,'links'))
<div class="a2-paginate">
{{ $items->links() }}
</div>
@endif


</div>
</div>

@endsection
