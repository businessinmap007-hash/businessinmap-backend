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

            {{-- وصفٌ واحد للدفعة كلِّها: عشرُ غرفٍ مزدوجة تشترك فى وصفها،
                 وما يخصّ غرفةً بعينها يُكتب لها من شاشتها. والصورُ تبقى
                 لكل وحدة على حدة — ملفٌّ واحد يُشار إليه من عشرة صفوف
                 يموت مع أوّل حذف. --}}
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="description">{{ __('الوصف (يظهر للعميل، للدفعة كلها)') }}</label>
                <textarea class="a2-input" id="description" name="description" rows="2"
                          placeholder="{{ __('غرفة مزدوجة بتكييف وتليفزيون وحمام خاص.') }}">{{ old('description') }}</textarea>
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

    {{-- ───────── السعر ─────────
         «٦ غرف فردى سعرها ٦٠٠» — السعرُ يُكتب مع الدفعة، لا فى شاشةٍ أخرى
         بعدها. ويُكتب حيث يُقرأ: على سطر سعر هذا النوع، وهو مصدرُ السعر
         الوحيد فى المنصّة — فالوحدةُ لا تحمل سعرًا وإنما تشير إلى نوعها. --}}
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('السعر') }}</div>
                <div class="a2-card-sub">{{ __('سعر الوحدة الواحدة — لليلة أو للساعة حسب نمط حجزك — قبل أى إضافات.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="price">{{ __('سعر الوحدة') }}</label>
                <input type="number" step="0.01" min="0" class="a2-input" id="price" name="price"
                       value="{{ old('price') }}" placeholder="600">
                <small class="a2-help">{{ __('اتركه فارغًا إن كنت قد سعّرت هذا النوع من «أسعاري».') }}</small>
            </div>
        </div>
    </div>

    @if($unitOptions->isNotEmpty())
        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('صفات هذه الوحدات') }}</div>
                    <div class="a2-card-sub">
                        {{ __('ما تشترك فيه كل وحدات هذه الدفعة — «بلكونة»، «تكييف».') }}
                        {{ __('وما يخصّ غرفة بعينها — D117 على المسبح وD118 على البحر — يُضبط من شاشة تلك الغرفة بعد الإضافة.') }}
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
                                        {{-- خانةُ اختيارٍ فقط: السعرُ يُكتب مرّةً فى «الإضافات». --}}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ونظامُ الوجبات ليس هنا.
         «إفطار» و«إقامة كاملة» نفسُهما فى كل غرفة، فتكرارُهما مع كل دفعةٍ
         يعيد إدخالَ الشىء نفسه. يُضبطان مرّةً من «إضافات الحجز». --}}
    <div class="a2-card a2-card--soft" style="margin-top:12px;">
        <div class="a2-card-sub">
            {{ __('نظام الوجبات وما يختاره النزيل عند الحجز يُضبط مرّة واحدة من') }}
            <a href="{{ route('business.booking-add-ons.index') }}">{{ __('إضافات الحجز') }}</a>.
        </div>
    </div>

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
    /* خانةُ السعر لا تظهر إلا على كلمةٍ مختارة: صندوقٌ فارغ بجوار كل كلمة
       يحوّل قائمةً تُقرأ بلمحة إلى استمارة. */
    .bv-chip .bv-adjust, .bv-chip .bv-adjust-type { display: none; }
    .bv-chip:has(input[type=checkbox]:checked) .bv-adjust,
    .bv-chip:has(input[type=checkbox]:checked) .bv-adjust-type { display: inline-block; }
    .bv-adjust { width: 66px; padding: 1px 4px; font-size: 12px; border-radius: 6px;
                 border: 1px solid rgba(128,128,128,.35); }
    .bv-adjust-type { padding: 1px 2px; font-size: 12px; border-radius: 6px;
                      border: 1px solid rgba(128,128,128,.35); }
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
