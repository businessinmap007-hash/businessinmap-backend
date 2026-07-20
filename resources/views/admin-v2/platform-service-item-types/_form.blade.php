@php
    $isEdit = isset($row) && $row?->exists;

    $displayName = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $metaValue = old('meta', isset($row) && is_array($row->meta ?? null)
        ? json_encode($row->meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        : ''
    );
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">{{ __('ملاحظة') }}</div>
    <div class="a2-section-subtitle">
        {{ __('هذه الأنواع تُستخدم في شاشة أسعار خدمات البزنس بدل القائمة الثابتة داخل الكود.') }}
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('البيانات الأساسية') }}</div>
            <div class="a2-card-sub">{{ __('الخدمة، المفتاح، الاسم العربي والإنجليزي') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="platform_service_id">{{ __('الخدمة') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-psit-service" id="platform_service_id" name="platform_service_id">
                <option value="">{{ __('اختر الخدمة') }}</option>
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
            <label class="a2-label">{{ __('الفروع') }}</label>
            <div class="a2-hint a2-mt-8">
                {{ __('عضوية الأنواع في الفروع تُدار من') }}
                <a href="{{ route('admin.service-branches.index', ['service_id' => old('platform_service_id', $row->platform_service_id ?? 0)]) }}">{{ __('لوحة تنظيم فروع الخدمة') }}</a>
                {{ __('— النوع ممكن يتبع أكتر من فرع.') }}
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Key <span class="a2-danger">*</span></label>
            <input
                class="a2-input"
                name="key"
                value="{{ old('key', $row->key ?? '') }}"
                dir="ltr"
                placeholder="single_room"
            >

            <div class="a2-hint a2-mt-8">
                {{ __('حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.') }}
            </div>

            @error('key')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم العربي') }} <span class="a2-danger">*</span></label>
            <input
                class="a2-input"
                name="name_ar"
                value="{{ old('name_ar', $row->name_ar ?? '') }}"
                placeholder="{{ __('غرفة فردية') }}"
            >

            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم الإنجليزي') }}</label>
            <input
                class="a2-input"
                name="name_en"
                value="{{ old('name_en', $row->name_en ?? '') }}"
                dir="ltr"
                placeholder="Single Room"
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
            <div class="a2-card-title">{{ __('الحالة والترتيب') }}</div>
            <div class="a2-card-sub">{{ __('التفعيل، الافتراضي، وترتيب الظهور') }}</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('الترتيب') }}</label>
            <input
                class="a2-input"
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
            <label class="a2-label">{{ __('الحالة') }}</label>

            <label class="a2-check" style="margin-top:10px;">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))
                >
                <span>{{ __('مفعل') }}</span>
            </label>

            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('افتراضي') }}</label>

            <label class="a2-check" style="margin-top:10px;">
                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                    @checked((bool) old('is_default', (int) ($row->is_default ?? 0)))
                >
                <span>{{ __('النوع الافتراضي لهذه الخدمة') }}</span>
            </label>

            <div class="a2-hint a2-mt-8">
                {{ __('عند تفعيله، سيتم إلغاء الافتراضي عن باقي أنواع نفس الخدمة.') }}
            </div>

            @error('is_default')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">Meta</div>
            <div class="a2-card-sub">{{ __('بيانات إضافية اختيارية بصيغة JSON') }}</div>
        </div>
    </div>

    <div class="a2-form-group">
        <textarea
            class="a2-input"
            name="meta"
            rows="6"
            dir="ltr"
            placeholder='{"icon":"room"}'
        >{{ $metaValue }}</textarea>

        @error('meta')
            <div class="a2-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.platform-service-item-types.index', ['service_id' => old('platform_service_id', $row->platform_service_id ?? 0)]) }}" class="a2-btn a2-btn-ghost">
        {{ __('رجوع') }}
    </a>

    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'حفظ' }}
    </button>
</div>