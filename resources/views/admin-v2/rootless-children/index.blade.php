@extends('admin-v2.layouts.master')

@section('title','Rootless Children')
@section('body_class','admin-v2-rootless-children')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('الأبناء بلا جذر') }}</h2>
        <div class="a2-hint">
          {{ __('صفوف من category_children_master بلا رابطٍ فى category_parent_child — سجلّ تراجعٍ لاندماج أو إيقافٍ سابق فى أغلب الأحيان. المراجعة والحذف هنا يدويان، صفًّا بصف.') }}
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
      <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    <div class="a2-statgrid">
      <div class="a2-stat">
        <div class="a2-stat-label">{{ __('بلا جذر') }}</div>
        <div class="a2-stat-value">{{ $total }}</div>
      </div>
      <div class="a2-stat">
        <div class="a2-stat-label">{{ __('محظور حذفها') }}</div>
        <div class="a2-stat-value">{{ $blocked }}</div>
        <div class="a2-hint">{{ __('مرتبطة بسياسة ضمانٍ لحساب حقيقى') }}</div>
      </div>
    </div>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('الاسم') }}</th>
            <th>{{ __('أُنشئ') }}</th>
            <th>{{ __('روابط خيارات') }}</th>
            <th>{{ __('سجل قرارات') }}</th>
            <th>{{ __('إعدادات خدمة (مُعطَّلة)') }}</th>
            <th style="width:180px;">{{ __('الإجراء') }}</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          <tr>
            <td dir="ltr">{{ $row['id'] }}</td>
            <td>
              <div class="a2-fw-700">{{ $row['name_ar'] ?: '—' }}</div>
              <div class="a2-hint" dir="ltr">{{ $row['name_en'] ?: '—' }}</div>
              @if($row['deposit_policy'])
                <span class="a2-badge a2-badge-danger">
                  {{ __('سياسة ضمان — حساب :ids', ['ids' => $row['deposit_policy']['business_ids']]) }}
                </span>
              @endif
            </td>
            <td dir="ltr">{{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('Y-m-d') }}</td>
            <td>{{ $row['option_links'] }}</td>
            <td>{{ $row['decisions'] }}</td>
            <td>{{ $row['configs'] }}</td>
            <td>
              @if($row['deposit_policy'])
                <span class="a2-hint">{{ __('عالج سياسة الضمان أولًا') }}</span>
              @else
                @php
                  $confirmMsg = __('حذف «:name» نهائيًا؟ لا يمكن التراجع.', ['name' => $row['name_ar']]);
                @endphp
                <form method="post"
                      action="{{ route('admin.rootless-children.destroy', ['categoryChild' => $row['id']]) }}"
                      onsubmit="return confirm({{ Js::from($confirmMsg) }});"
                      style="margin:0;">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger">
                    {{ __('حذف نهائى') }}
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="a2-empty-cell">{{ __('لا يوجد ابنٌ بلا جذر.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
