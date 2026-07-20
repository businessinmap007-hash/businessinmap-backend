@extends('admin-v2.layouts.master')

@section('title',__('صلاحيات المشرفين'))
@section('body_class','admin-v2-admin-roles')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('صلاحيات المشرفين') }}</h2>
        <div class="a2-hint">
          {{ __('كل مشرف يرى ويعمل فقط داخل صلاحياته. لا يمكنك منح صلاحية لا تملكها أنت، ولا تعديل حسابك أنت.') }}
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-table-wrap" style="overflow-x:auto">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('المشرف') }}</th>
            <th>{{ __('الصلاحيات') }}</th>
            <th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
        @forelse($admins as $row)
          @php
            $admin = $row['user'];
            $blocked = $row['block_reason'];
          @endphp
          <tr>
            <td>{{ $admin->id }}</td>
            <td>
              <div style="font-weight:600">{{ $admin->name }}</div>
              <div class="a2-hint">{{ $admin->email }}</div>
            </td>
            <td>
              @if($row['is_super'])
                <span class="a2-badge a2-badge-danger">{{ __('مدير عام — كل الصلاحيات') }}</span>
                <div class="a2-hint" style="margin-top:4px">
                  {{ __('لا يُدار من هذه الشاشة عمدًا: إنشاء أو إلغاء مدير عام يتم من الخادم فقط.') }}
                </div>
              @elseif(count($row['abilities']) === 0)
                <span class="a2-badge a2-badge-muted">{{ __('بلا صلاحيات — لا يرى شيئًا') }}</span>
              @else
                @foreach($row['abilities'] as $ability)
                  <span class="a2-badge {{ $ability === \App\Support\AdminAbility::MONEY || $ability === \App\Support\AdminAbility::ROLES ? 'a2-badge-danger' : 'a2-badge-muted' }}"
                        style="margin:2px 0 2px 4px; display:inline-block">
                    {{ $labels[$ability] ?? $ability }}
                  </span>
                @endforeach
              @endif
            </td>
            <td>
              @if($blocked)
                <span class="a2-hint">{{ $blocked }}</span>
              @else
                <a class="a2-btn a2-btn-sm" href="{{ route('admin.admin-roles.edit', $admin->id) }}">{{ __('تعديل') }}</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="a2-hint">{{ __('لا يوجد مشرفون.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
