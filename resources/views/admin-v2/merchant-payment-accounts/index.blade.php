@extends('admin-v2.layouts.master')

@section('title','Merchant Sub-Accounts')
@section('body_class','admin-v2-merchant-payment-accounts')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:860px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('حسابات الدفع الفرعية للتجّار — Fawry') }}</h2>
        <div class="a2-hint">{{ __('عند تفعيل الخدمة، تُوجَّه دفعة العميل لطلب التاجر مباشرةً إلى حساب Fawry الفرعي الخاص بذلك التاجر بدل حساب المنصّة.') }}</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Global toggle --}}
    <form method="post" action="{{ route('admin.merchant-payment-accounts.toggle') }}" style="margin-bottom:22px">
      @csrf
      @method('PUT')
      <div class="a2-field">
        <label class="a2-label">
          {{ __('حالة الخدمة') }}
          <span class="a2-badge a2-badge-{{ $enabled ? 'success' : 'muted' }}" style="margin-inline-start:8px">
            {{ $enabled ? __('مفعّلة') : __('متوقفة') }}
          </span>
        </label>
        <label style="display:inline-flex;gap:8px;align-items:center;margin-top:6px">
          <input type="checkbox" name="enabled" value="1" @checked($enabled)>
          {{ __('تفعيل توجيه الدفع إلى حسابات التجّار الفرعية') }}
        </label>
      </div>
      <div class="a2-actionsbar" style="margin-top:8px">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ الحالة') }}</button>
      </div>
    </form>

    <hr>

    {{-- Add / edit one merchant's sub-account --}}
    <h3 class="a2-title" style="font-size:16px;margin-top:16px">{{ __('إضافة / تعديل حساب تاجر') }}</h3>
    <form method="post" action="{{ route('admin.merchant-payment-accounts.save') }}">
      @csrf
      <div class="a2-field" style="margin-bottom:14px">
        <label class="a2-label" for="business_id">{{ __('رقم حساب التاجر (business_id)') }}</label>
        <input class="a2-input" id="business_id" name="business_id" type="number" dir="ltr"
               value="{{ old('business_id') }}" required>
      </div>
      <div class="a2-field" style="margin-bottom:14px">
        <label class="a2-label" for="merchant_code">{{ __('كود التاجر الفرعي (Merchant Code)') }}</label>
        <input class="a2-input" id="merchant_code" name="merchant_code" type="text" dir="ltr"
               value="{{ old('merchant_code') }}" required>
      </div>
      <div class="a2-field" style="margin-bottom:14px">
        <label class="a2-label" for="security_key">{{ __('المفتاح الأمني (Security Key)') }}</label>
        <input class="a2-input" id="security_key" name="security_key" type="password" dir="ltr"
               autocomplete="new-password" placeholder="{{ __('سرّي — يُخزَّن مشفّرًا. اتركه فارغًا للإبقاء على الحالي') }}">
      </div>
      <div class="a2-field" style="margin-bottom:14px">
        <label style="display:inline-flex;gap:8px;align-items:center">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
          {{ __('الحساب فعّال') }}
        </label>
      </div>
      <div class="a2-actionsbar">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ حساب التاجر') }}</button>
      </div>
    </form>

    <hr>

    {{-- Configured accounts --}}
    <h3 class="a2-title" style="font-size:16px;margin-top:16px">{{ __('الحسابات المضبوطة') }}</h3>
    @if($accounts->isEmpty())
      <div class="a2-alert a2-alert-muted">{{ __('لا توجد حسابات فرعية مضبوطة بعد.') }}</div>
    @else
      <table class="a2-table" style="width:100%">
        <thead>
          <tr>
            <th>{{ __('التاجر') }}</th>
            <th dir="ltr">Merchant Code</th>
            <th>{{ __('المفتاح') }}</th>
            <th>{{ __('الحالة') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($accounts as $acc)
            <tr>
              <td>{{ optional($acc->business)->name ?? ('#' . $acc->business_id) }} <span class="a2-hint" dir="ltr">#{{ $acc->business_id }}</span></td>
              <td dir="ltr">{{ $acc->merchant_code ?: '—' }}</td>
              <td>
                <span class="a2-badge a2-badge-{{ filled($acc->security_key) ? 'success' : 'danger' }}">
                  {{ filled($acc->security_key) ? __('مضبوط') : __('غير مضبوط') }}
                </span>
              </td>
              <td>
                <span class="a2-badge a2-badge-{{ $acc->is_active ? 'success' : 'muted' }}">
                  {{ $acc->is_active ? __('فعّال') : __('متوقف') }}
                </span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
