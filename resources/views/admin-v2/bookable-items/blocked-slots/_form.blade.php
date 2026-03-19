@php
    $slot = $slot ?? null;
    $submitLabel = $submitLabel ?? 'حفظ';

    $startsAtValue = old('starts_at', $slot && $slot->starts_at ? \Illuminate\Support\Carbon::parse($slot->starts_at)->format('Y-m-d\TH:i') : '');
    $endsAtValue   = old('ends_at', $slot && $slot->ends_at ? \Illuminate\Support\Carbon::parse($slot->ends_at)->format('Y-m-d\TH:i') : '');
    $blockType     = old('block_type', $slot->block_type ?? 'manual');
    $reason        = old('reason', $slot->reason ?? '');
    $notes         = old('notes', $slot->notes ?? '');
    $isActive      = (int) old('is_active', isset($slot) ? (int) $slot->is_active : 1);
@endphp

<div class="a2-form-grid">
    <div class="a2-form-group">
        <label class="a2-label">بداية الغلق</label>
        <input type="datetime-local"
               name="starts_at"
               class="a2-input @error('starts_at') is-invalid @enderror"
               value="{{ $startsAtValue }}"
               required>
        @error('starts_at')
            <div class="a2-input-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="a2-form-group">
        <label class="a2-label">نهاية الغلق</label>
        <input type="datetime-local"
               name="ends_at"
               class="a2-input @error('ends_at') is-invalid @enderror"
               value="{{ $endsAtValue }}"
               required>
        @error('ends_at')
            <div class="a2-input-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="a2-form-grid">
    <div class="a2-form-group">
        <label class="a2-label">نوع الغلق</label>
        <select name="block_type" class="a2-select @error('block_type') is-invalid @enderror" required>
            <option value="manual" {{ $blockType === 'manual' ? 'selected' : '' }}>manual</option>
            <option value="maintenance" {{ $blockType === 'maintenance' ? 'selected' : '' }}>maintenance</option>
            <option value="holiday" {{ $blockType === 'holiday' ? 'selected' : '' }}>holiday</option>
            <option value="admin" {{ $blockType === 'admin' ? 'selected' : '' }}>admin</option>
        </select>
        @error('block_type')
            <div class="a2-input-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="a2-form-group">
        <label class="a2-label">الحالة</label>
        <select name="is_active" class="a2-select">
            <option value="1" {{ $isActive === 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ $isActive === 0 ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>

<div class="a2-form-group">
    <label class="a2-label">السبب</label>
    <input type="text"
           name="reason"
           class="a2-input @error('reason') is-invalid @enderror"
           value="{{ $reason }}"
           placeholder="سبب الغلق">
    @error('reason')
        <div class="a2-input-error">{{ $message }}</div>
    @enderror
</div>

<div class="a2-form-group">
    <label class="a2-label">ملاحظات</label>
    <textarea name="notes"
              rows="5"
              class="a2-textarea @error('notes') is-invalid @enderror"
              placeholder="ملاحظات إضافية">{{ $notes }}</textarea>
    @error('notes')
        <div class="a2-input-error">{{ $message }}</div>
    @enderror
</div>

<div class="a2-divider"></div>

<div class="a2-form-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $submitLabel }}
    </button>

    <a href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id]) }}"
       class="a2-btn a2-btn-ghost">
        إلغاء
    </a>
</div>