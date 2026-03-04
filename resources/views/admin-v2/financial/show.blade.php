@extends('admin-v2.layouts.master')

@section('title','Transaction')
@section('body_class','admin-v2-financial')

@section('content')
@php
  $backUrl = route('admin.financial.index', request()->query());
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <h2 class="a2-title" style="margin:0;">عرض المعاملة</h2>
      <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">رجوع</a>
    </div>

    <div class="a2-card" style="padding:14px;">
      <div style="display:grid;grid-template-columns: 1fr 1fr;gap:12px;">
        @foreach((array)$tx as $k => $v)
          <div style="display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid var(--a2-border,#eee);padding:8px 0;">
            <div class="a2-hint">{{ $k }}</div>
            <div dir="ltr" style="text-align:left;max-width:65%;word-break:break-word;">{{ is_scalar($v) ? $v : json_encode($v) }}</div>
          </div>
        @endforeach
      </div>
    </div>

  </div>
</div>

<style>
@media (max-width: 900px){
  .a2-card [style*="grid-template-columns"]{grid-template-columns:1fr !important;}
}
</style>
@endsection
