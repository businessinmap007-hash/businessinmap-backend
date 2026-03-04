@php
  $typeVal = old('type', $sponsor->type ?? 'free');
@endphp

<div class="spon-row">
  <div class="spon-label">User ID <span class="spon-help">(اختياري)</span></div>
  <div>
    <input type="number"
           name="user_id"
           value="{{ old('user_id', $sponsor->user_id) }}"
           placeholder="مثال: 184"
           class="a2-input">
  </div>
</div>



<div class="spon-row">
  <div class="spon-label">السعر <span class="spon-help">(اختياري)</span></div>
  <div>
    <input type="number"
           step="0.01"
           name="price"
           value="{{ old('price', $sponsor->price) }}"
           placeholder="مثال: 45"
           class="a2-input">
  </div>
</div>

<div class="spon-row">
  <div class="spon-label">تاريخ الانتهاء <span class="spon-help">(expire_at)</span></div>
  <div>
    <input type="datetime-local"
           name="expire_at"
           value="{{ old('expire_at', $sponsor->expire_at ? \Carbon\Carbon::parse($sponsor->expire_at)->format('Y-m-d\TH:i') : '') }}"
           class="a2-input">
  </div>
</div>

<div class="spon-row">
  <div class="spon-label">الصورة <span class="spon-help">(اختياري في التعديل)</span></div>
  <div>
    <input type="file" name="image" accept="image/*" class="a2-input">
  </div>
</div>
