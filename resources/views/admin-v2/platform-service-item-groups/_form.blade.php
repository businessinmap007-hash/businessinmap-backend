@php
    $isEdit = isset($row) && $row?->exists;

    $displayName = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">ملاحظة</div>
    <div class="a2-section-subtitle">
        الفرع يقسّم أنواع العناصر داخل خدمة واحدة (مثال: فنادق / عيادات / ملاعب داخل الحجز).
        أنواع العناصر نفسها تُدار من شاشة أنواع عناصر خدمات المنصة.
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">بيانات الفرع</div>
            <div class="a2-card-sub">الخدمة، المفتاح، والاسم</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="platform_service_id">الخدمة <span class="a2-danger">*</span></label>
            <select class="a2-select" id="platform_service_id" name="platform_service_id">
                <option value="">اختر الخدمة</option>
                @foreach(($services ?? []) as $service)
                    <option
                        value="{{ $service->id }}"
                        @selected((string) old('platform_service_id', $row->platform_service_id ?? '') === (string) $service->id)
                    >
                        {{ $displayName($service) }} — {{ $service->key }}
                    </option>
                @endforeach
            </select>

            @error('platform_service_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="key">Key <span class="a2-danger">*</span></label>
            <input
                class="a2-input"
                id="key"
                name="key"
                value="{{ old('key', $row->key ?? '') }}"
                dir="ltr"
                placeholder="hotel"
            >

            <div class="a2-hint a2-mt-8">
                حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.
            </div>

            @error('key')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="name_ar">الاسم العربي <span class="a2-danger">*</span></label>
            <input
                class="a2-input"
                id="name_ar"
                name="name_ar"
                value="{{ old('name_ar', $row->name_ar ?? '') }}"
                placeholder="فنادق ووحدات سكنية"
            >

            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="name_en">الاسم الإنجليزي</label>
            <input
                class="a2-input"
                id="name_en"
                name="name_en"
                value="{{ old('name_en', $row->name_en ?? '') }}"
                dir="ltr"
                placeholder="Hotels & Units"
            >

            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الحالة والترتيب</div>
            <div class="a2-card-sub">التفعيل وترتيب الظهور</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label" for="sort_order">الترتيب</label>
            <input
                class="a2-input"
                id="sort_order"
                name="sort_order"
                value="{{ old('sort_order', (int) ($row->sort_order ?? 0)) }}"
                inputmode="numeric"
                placeholder="0"
            >

            @error('sort_order')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>

            <label class="a2-check" style="margin-top:10px;">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))
                >
                <span>مفعل</span>
            </label>

            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        @if($isEdit)
            <div class="a2-form-group">
                <label class="a2-label">أنواع العناصر داخله</label>
                <div style="margin-top:10px;">
                    <span class="a2-pill a2-pill-sub">{{ (int) ($row->item_types_count ?? 0) }} نوع</span>
                </div>
                <div class="a2-hint a2-mt-8">
                    عند حذف الفرع تصبح أنواعه "بدون فرع" ولا تُحذف.
                </div>
            </div>
        @endif
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.platform-service-item-groups.index', ['service_id' => old('platform_service_id', $row->platform_service_id ?? 0)]) }}" class="a2-btn a2-btn-ghost">
        رجوع
    </a>

    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'حفظ' }}
    </button>
</div>
