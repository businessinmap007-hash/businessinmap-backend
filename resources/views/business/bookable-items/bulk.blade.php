@extends('business.layouts.master')

@section('title', __('إضافة وحدات بالجملة'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إضافة وحدات بالجملة') }}</h1>
        <div class="a2-page-subtitle">{{ __('٦ غرف فردى من ١٠١ إلى ١٠٦ — نوع واحد، ومدى أرقام، وحفظة واحدة.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('وحداتي') }}</a>
        <a href="{{ route('business.bookable-items.create') }}" class="a2-btn a2-btn-ghost">{{ __('وحدة واحدة') }}</a>
    </div>
</div>

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

@php
    $currentService = (int) old('service_id', 0);
    $currentType = (string) old('item_type', '');
    $selectedOptions = collect(old('option_ids', []))->map(fn ($id) => (int) $id);
@endphp

<form method="POST" action="{{ route('business.bookable-items.bulk.store') }}">
    @csrf

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('ما الذي تضيفه') }}</div>
                <div class="a2-card-sub">{{ __('النوع واحد لكل دفعة — كرّرها لكل نوع عندك.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="service_id">{{ __('الخدمة') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-service" id="service_id" name="service_id" required>
                    <option value="">{{ __('اختر') }}</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($currentService === (int) $service->id)>
                            {{ app()->getLocale() === 'en' ? ($service->name_en ?: $service->name_ar) : ($service->name_ar ?: $service->name_en) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="item_type">{{ __('نوع العنصر') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-type" id="item_type" name="item_type" required data-current-value="{{ $currentType }}">
                    <option value="">{{ __('اختر الخدمة أولًا') }}</option>
                </select>
            </div>

            @if($lineOptions->isNotEmpty())
                <div class="a2-form-group">
                    <label class="a2-label" for="line_option_id">{{ __('النوع') }}</label>
                    <select class="a2-select" id="line_option_id" name="line_option_id">
                        <option value="">{{ __('— بدون تحديد —') }}</option>
                        @foreach($lineOptions as $groupName => $options)
                            <optgroup label="{{ $groupName }}">
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected((int) old('line_option_id') === (int) $option->id)>
                                        {{ $option->name_ar ?: $option->name_en }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <small class="a2-help">{{ __('«غرفة فردية» — وهو ما يربط كل وحدة بسطر سعرها.') }}</small>
                </div>
            @endif

            <div class="a2-form-group">
                <label class="a2-label" for="capacity">{{ __('السعة') }}</label>
                <input type="number" min="1" class="a2-input" id="capacity" name="capacity" value="{{ old('capacity') }}">
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('الأرقام') }}</div>
                <div class="a2-card-sub">{{ __('الموجود يُتخطّى ولا يُرفض — أعد المدى متى شئت.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="prefix">{{ __('بادئة (اختياري)') }}</label>
                <input type="text" maxlength="40" class="a2-input js-bi-prefix" id="prefix" name="prefix"
                       value="{{ old('prefix') }}" placeholder="{{ __('غرفة ') }}">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="from">{{ __('من') }} <span class="a2-danger">*</span></label>
                <input type="number" min="0" class="a2-input js-bi-from" id="from" name="from" value="{{ old('from', 101) }}" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="to">{{ __('إلى') }} <span class="a2-danger">*</span></label>
                <input type="number" min="0" class="a2-input js-bi-to" id="to" name="to" value="{{ old('to', 106) }}" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="pad">{{ __('عدد الخانات') }}</label>
                <input type="number" min="0" max="6" class="a2-input js-bi-pad" id="pad" name="pad" value="{{ old('pad', 0) }}">
                <small class="a2-help">{{ __('٣ تكتب ٠٠١ بدل ١. اتركه صفرًا لأرقام كـ١٠١.') }}</small>
            </div>

            <div class="a2-form-group a2-field-full">
                {{-- ما سيُنشأ، قبل الحفظ: دفعةٌ لا تُرى قبل تنفيذها دفعةٌ تُصحَّح واحدةً واحدة. --}}
                <label class="a2-label">{{ __('ستُنشأ') }}</label>
                <div class="a2-alert a2-alert-info js-bi-preview" style="font-weight:400">—</div>
            </div>
        </div>
    </div>

    @if($unitOptions->isNotEmpty())
        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('صفات هذه الوحدات') }}</div>
                    <div class="a2-card-sub">
                        {{ __('ما تشترك فيه كل وحدات هذه الدفعة — «إطلالة بحرية»، «بلكونة».') }}
                        {{ __('وإن كان لها سعر على سطر سعرك، يُضاف تلقائيًا ولا يُسأل عنه العميل.') }}
                    </div>
                </div>
            </div>

            <div class="a2-form-grid">
                <div class="a2-form-group a2-field-full">
                    @foreach($unitOptions as $groupName => $options)
                        <div class="bv-group">
                            <div class="bv-group-head">{{ $groupName }}</div>
                            <div class="bv-chips">
                                @foreach($options as $option)
                                    <label class="bv-chip">
                                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                               @checked($selectedOptions->contains((int) $option->id))>
                                        <span>{{ $option->name_ar ?: $option->name_en }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="a2-btn a2-btn-primary">{{ __('إضافة الدفعة') }}</button>
    </div>
</form>

@push('styles')
<style>
    .bv-group { margin-bottom: 10px; }
    .bv-group-head { font-size: 12px; opacity: .7; margin-bottom: 4px; }
    .bv-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .bv-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
               border: 1px solid rgba(128,128,128,.35); border-radius: 999px;
               font-size: 13px; cursor: pointer; }
    .bv-chip:has(input:checked) { border-color: currentColor; font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const typesByService = @json($allowedTypesByService ?? []);
    const serviceSelect = document.querySelector('.js-bi-service');
    const typeSelect = document.querySelector('.js-bi-type');

    function fillTypes() {
        const wanted = typeSelect.dataset.currentValue || '';
        const list = typesByService[serviceSelect.value] || [];
        typeSelect.innerHTML = '';

        if (!list.length) {
            typeSelect.innerHTML = '<option value="">{{ __('اختر الخدمة أولًا') }}</option>';
            return;
        }

        list.forEach(function (t) {
            const o = document.createElement('option');
            o.value = t.key;
            o.textContent = t.label || t.key;
            if (t.key === wanted) { o.selected = true; }
            typeSelect.appendChild(o);
        });
    }

    // ما سيُنشأ، مرسومًا وهو يكتب: الحدُّ الأقصى محروسٌ فى الخادم أيضًا.
    const preview = document.querySelector('.js-bi-preview');
    const inputs = ['.js-bi-prefix', '.js-bi-from', '.js-bi-to', '.js-bi-pad'].map(s => document.querySelector(s));

    function draw() {
        const [prefix, from, to, pad] = inputs.map(i => i.value);
        const a = parseInt(from, 10), b = parseInt(to, 10), p = parseInt(pad, 10) || 0;

        if (isNaN(a) || isNaN(b) || b < a) { preview.textContent = '—'; return; }

        const count = b - a + 1;
        const code = n => (prefix || '') + (p > 0 ? String(n).padStart(p, '0') : String(n));
        const shown = count <= 6
            ? Array.from({length: count}, (_, i) => code(a + i)).join('، ')
            : code(a) + '، ' + code(a + 1) + ' … ' + code(b);

        preview.textContent = count + ' — ' + shown;
    }

    serviceSelect.addEventListener('change', fillTypes);
    inputs.forEach(i => i.addEventListener('input', draw));
    fillTypes();
    draw();
})();
</script>
@endpush
@endsection
