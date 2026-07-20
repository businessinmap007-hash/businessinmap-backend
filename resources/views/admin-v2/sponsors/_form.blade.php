@php
  $typeVal = old('type', $sponsor->type ?? 'free');
@endphp

<div class="spon-row">
  <div class="spon-label">User ID <span class="spon-help">{{ __('(اختياري)') }}</span></div>
  <div>
    <input type="number"
           name="user_id"
           value="{{ old('user_id', $sponsor->user_id) }}"
           placeholder="{{ __('مثال: 184') }}"
           class="a2-input">
  </div>
</div>



{{-- `type` is required by SponsorController::validateData, but this field was
     missing: $typeVal above was computed and never rendered, so every save
     failed validation on a value the form never sent. --}}
<div class="spon-row">
  <div class="spon-label">{{ __('النوع') }}</div>
  <div>
    <select name="type" class="a2-select" required>
      @foreach(['free' => __('مجاني'), 'paid' => __('مدفوع')] as $value => $label)
        <option value="{{ $value }}" @selected($typeVal === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
</div>

<div class="spon-row">
  <div class="spon-label">{{ __('السعر') }} <span class="spon-help">{{ __('(اختياري)') }}</span></div>
  <div>
    <input type="number"
           step="0.01"
           name="price"
           value="{{ old('price', $sponsor->price) }}"
           placeholder="{{ __('مثال: 45') }}"
           class="a2-input">
  </div>
</div>

<div class="spon-row">
  <div class="spon-label">{{ __('تاريخ الانتهاء') }} <span class="spon-help">(expire_at)</span></div>
  <div>
    <input type="datetime-local"
           name="expire_at"
           value="{{ old('expire_at', $sponsor->expire_at ? \Carbon\Carbon::parse($sponsor->expire_at)->format('Y-m-d\TH:i') : '') }}"
           class="a2-input">
  </div>
</div>

<div class="spon-row">
  {{-- Required on create (the column is NOT NULL), optional on edit where
       leaving it empty keeps the existing image. --}}
  <div class="spon-label">
    {{ __('الصورة') }}
    <span class="spon-help">{{ $sponsor->exists ? __('(اختياري في التعديل)') : __('(مطلوبة)') }}</span>
  </div>
  <div>
    <input type="file" name="image" accept="image/*" class="a2-input" @unless($sponsor->exists) required @endunless>
  </div>
</div>
