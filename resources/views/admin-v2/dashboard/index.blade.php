{{-- resources/views/admin-v2/dashboard/index.blade.php --}}
@extends('admin-v2.layouts.master')

@section('title', 'Dashboard')

@section('content')
  <h2 style="margin: 10px 0 20px;">لوحة التحكم (Admin V2)</h2>

  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">

    <div style="border:1px solid #eee; padding:15px; border-radius:10px;">
      <div style="font-size:13px; color:#666;">Users</div>
      <div style="font-size:28px; font-weight:700;">{{ $stats['users'] ?? 0 }}</div>
    </div>

    <div style="border:1px solid #eee; padding:15px; border-radius:10px;">
      <div style="font-size:13px; color:#666;">Categories</div>
      <div style="font-size:28px; font-weight:700;">{{ $stats['categories'] ?? 0 }}</div>
    </div>

    <div style="border:1px solid #eee; padding:15px; border-radius:10px;">
      <div style="font-size:13px; color:#666;">Products</div>
      <div style="font-size:28px; font-weight:700;">{{ $stats['products'] ?? 0 }}</div>
    </div>

    {{-- Disputes Widget --}}
    <div class="a2-card" style="padding:14px; grid-column: 1 / -1;">
      <div class="a2-header" style="margin-bottom:8px;">
        <div>
          <div class="a2-title" style="font-size:16px;">النزاعات</div>
        </div>

        {{-- Disputes Button --}}
@if(\Illuminate\Support\Facades\Route::has('admin.disputes.index'))

<div class="a2-card" style="padding:20px; grid-column: 1 / -1; text-align:center;">

    <div class="a2-title" style="font-size:18px; margin-bottom:10px;">
        النزاعات المفتوحة
    </div>

    @if(($openDisputesCount ?? 0) > 0)

        <div style="font-size:32px;font-weight:900;margin-bottom:12px;">
            {{ (int)$openDisputesCount }}
        </div>

        <a href="{{ route('admin.disputes.index') }}"
           class="a2-btn a2-btn-danger"
           style="font-size:16px;padding:10px 20px;">
            عرض النزاعات المفتوحة
        </a>

    @else

        <div class="a2-alert a2-alert-success" style="margin:10px auto;max-width:400px;">
            لا توجد نزاعات حالياً ✅
        </div>

    @endif

</div>

@endif
      </div>

      @if(($openDisputesCount ?? 0) > 0)
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <div class="a2-card" style="padding:12px;min-width:220px;">
            <div class="a2-hint">عدد النزاعات المفتوحة</div>
            <div style="font-size:28px;font-weight:900;">{{ (int)$openDisputesCount }}</div>
          </div>

          <div class="a2-alert a2-alert-warning" style="margin:0;flex:1;">
            يوجد نزاعات تحتاج مراجعة فورية.
          </div>
        </div>
      @else
        <div class="a2-alert a2-alert-success" style="margin:0;">
          لا توجد نزاعات مفتوحة حالياً ✅
        </div>
      @endif
    </div>

  </div>
@endsection