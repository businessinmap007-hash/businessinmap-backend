@extends('admin-v2.layouts.master')

@section('title','Job Applicants')
@section('body_class','admin-v2-jobs')

@section('content')
@php
    $post = $item;
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">المتقدمون — {{ $post->title_ar ?: $post->title_en ?: ('#'.$post->id) }}</h2>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.show', ['post'=>$post->id]) }}">رجوع للوظيفة</a>
      </div>
    </div>

    <div class="a2-hint" style="margin-bottom:12px;">
      عرض فقط — للإدارة. المُعلن (صاحب العمل) وحده يرى هذه التفاصيل من التطبيق، والجمهور يرى عدد المتقدمين فقط.
    </div>

    <table class="a2-table" style="width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>الاسم</th>
          <th>الهاتف</th>
          <th>البريد</th>
          <th>تاريخ التقديم</th>
          <th>معتمد؟</th>
        </tr>
      </thead>
      <tbody>
        @forelse($applicants as $a)
          <tr>
            <td>{{ $a->id }}</td>
            <td>{{ optional($a->user)->name ?: '—' }}</td>
            <td dir="ltr">{{ optional($a->user)->phone ?: '—' }}</td>
            <td dir="ltr">{{ optional($a->user)->email ?: '—' }}</td>
            <td dir="ltr">{{ optional($a->created_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $a->approved_at ? 'نعم' : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="a2-hint">لا يوجد متقدمون بعد.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div style="margin-top:14px;">
      {{ $applicants->links() }}
    </div>

  </div>
</div>
@endsection
