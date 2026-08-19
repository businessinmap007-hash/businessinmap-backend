@extends('business.layouts.master')

@section('title', __('تعديل وحدة'))

@php
    /*
     * أسماءُ ما فى الجدولين بالعربية. تُقرأ من ثوابت النموذج لا تُكتب مرّةً
     * ثانية: قيمةٌ تُضاف هناك ولا تُترجم هنا تظهر للتاجر بمفتاحها الإنجليزى.
     */
    use App\Models\BookableItemBlockedSlot;
    use App\Models\BookableItemPriceRule;

    $blockLabels = [
        BookableItemBlockedSlot::TYPE_MANUAL => __('إغلاق يدوي'),
        BookableItemBlockedSlot::TYPE_MAINTENANCE => __('صيانة'),
        BookableItemBlockedSlot::TYPE_HOLIDAY => __('إجازة'),
    ];

    $ruleLabels = [
        BookableItemPriceRule::RULE_DATE_RANGE => __('فترة بتاريخين'),
        BookableItemPriceRule::RULE_WEEKDAY => __('يوم من الأسبوع'),
        BookableItemPriceRule::RULE_SEASON => __('موسم'),
        BookableItemPriceRule::RULE_SPECIAL_DAY => __('يوم خاص'),
    ];

    $priceLabels = [
        BookableItemPriceRule::PRICE_FIXED => __('سعر ثابت'),
        BookableItemPriceRule::PRICE_DELTA => __('فرق عن الأساسي'),
        BookableItemPriceRule::PRICE_PERCENT => __('نسبة ٪'),
    ];

    $weekdayNames = [
        0 => __('الأحد'), 1 => __('الإثنين'), 2 => __('الثلاثاء'), 3 => __('الأربعاء'),
        4 => __('الخميس'), 5 => __('الجمعة'), 6 => __('السبت'),
    ];
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('تعديل وحدة') }}</h1>
        <div class="a2-page-subtitle">{{ $row->title ?: $row->code }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.bookable-items.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.bookable-items._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
        'lineOptions' => $lineOptions,
    ])
</form>

{{-- ───────── الصور ─────────
     الغرفةُ تُرى قبل أن تُحجَز. نفسُ معرض صنف المنيو ونفسُ حدِّه، لأنها
     نفسُ الآلية — و`HasOwnedImages` يحذف الملفَّ مع الصف. --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('صور الوحدة') }}</div>
            <div class="a2-card-sub">{{ __('حتى ١٠ صور. تُحذف نهائيًا مع الوحدة.') }}</div>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
        @forelse($row->images as $img)
            <div style="position:relative;width:120px;">
                <img src="{{ asset($img->image) }}" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:8px;display:block;">
                <form method="POST" action="{{ route('business.bookable-items.images.destroy', [$row->id, $img->id]) }}" onsubmit="return confirm('{{ __('حذف هذه الصورة نهائيًا؟') }}')" style="margin-top:6px;">
                    @csrf @method('DELETE')
                    <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit" style="width:100%">{{ __('حذف') }}</button>
                </form>
            </div>
        @empty
            <div class="a2-muted">{{ __('لا صور بعد.') }}</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('business.bookable-items.images.store', $row->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="images">{{ __('أضف صورًا') }}</label>
                <input class="a2-input" id="images" type="file" name="images[]" accept="image/*" multiple required>
            </div>
        </div>
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('رفع') }}</button>
    </form>
</div>

{{-- ───────── إغلاق الوحدة ─────────
     ما يقرؤه المحرّك قبل أن يقبل حجزًا: فترةٌ مغلقة هنا تُرفض هناك، بنفس
     الخدمة التى يفحص بها التوفّر — فلا يظهر مفتوحًا ما أغلقتَه. --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('إغلاق الوحدة') }}</div>
            <div class="a2-card-sub">{{ __('صيانة أو إجازة — لا تُحجز الوحدة في هذه الفترة مهما فعل العميل.') }}</div>
        </div>
    </div>

    <table class="a2-table">
        <thead>
            <tr>
                <th>{{ __('من') }}</th>
                <th>{{ __('إلى') }}</th>
                <th>{{ __('السبب') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($row->activeBlockedSlots as $slot)
                <tr>
                    <td>{{ optional($slot->starts_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ optional($slot->ends_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $blockLabels[$slot->block_type] ?? $slot->block_type }}@if($slot->reason) — {{ $slot->reason }}@endif</td>
                    <td>
                        <form method="POST" action="{{ route('business.bookable-items.blocked-slots.destroy', [$row->id, $slot->id]) }}"
                              onsubmit="return confirm('{{ __('فتح هذه الفترة من جديد؟') }}')">
                            @csrf @method('DELETE')
                            <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">{{ __('فتح') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="a2-muted">{{ __('لا فترات مغلقة.') }}</td></tr>
            @endforelse

            <tr>
                <form method="POST" action="{{ route('business.bookable-items.blocked-slots.store', $row->id) }}">
                    @csrf
                    <td><input class="a2-input" type="datetime-local" name="starts_at" required></td>
                    <td><input class="a2-input" type="datetime-local" name="ends_at" required></td>
                    <td>
                        <select class="a2-select" name="block_type" style="max-width:150px;display:inline-block">
                            @foreach($blockLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="a2-input" name="reason" placeholder="{{ __('ملاحظة (اختياري)') }}" style="max-width:200px;display:inline-block">
                    </td>
                    <td><button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">{{ __('إغلاق') }}</button></td>
                </form>
            </tr>
        </tbody>
    </table>
</div>

{{-- ───────── قواعد السعر ─────────
     «الجمعة والسبت +٢٠٠»، «موسم الصيف ١٢٠٠ ثابت». تُطبَّق على السعر الأساسى
     قبل صفات الغرفة، فقاعدةٌ ثابتة تحمل زيادةَ الإطلالة ولا تمحوها. --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('أسعار المواسم والأيام') }}</div>
            <div class="a2-card-sub">
                {{ __('سعر مختلف في أيام أو فترات بعينها. الأقل أولوية أولًا عند التعارض.') }}
            </div>
        </div>
    </div>

    <table class="a2-table">
        <thead>
            <tr>
                <th>{{ __('القاعدة') }}</th>
                <th>{{ __('متى') }}</th>
                <th>{{ __('السعر') }}</th>
                <th>{{ __('الأولوية') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($row->activePriceRules as $rule)
                <tr>
                    <td>{{ $rule->title ?: ($ruleLabels[$rule->rule_type] ?? $rule->rule_type) }}</td>
                    <td>
                        @if($rule->weekday !== null)
                            {{ $weekdayNames[$rule->weekday] ?? $rule->weekday }}
                        @else
                            {{ optional($rule->start_date)->toDateString() }} → {{ optional($rule->end_date)->toDateString() }}
                        @endif
                    </td>
                    <td>{{ $priceLabels[$rule->price_type] ?? $rule->price_type }} {{ rtrim(rtrim(number_format((float) $rule->price_value, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ (int) $rule->priority }}</td>
                    <td>
                        <form method="POST" action="{{ route('business.bookable-items.price-rules.destroy', [$row->id, $rule->id]) }}"
                              onsubmit="return confirm('{{ __('حذف هذه القاعدة؟') }}')">
                            @csrf @method('DELETE')
                            <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">{{ __('حذف') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="a2-muted">{{ __('لا قواعد — السعر واحد كل الأيام.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('business.bookable-items.price-rules.store', $row->id) }}" style="margin-top:14px;">
        @csrf
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="rule_title">{{ __('اسم القاعدة') }}</label>
                <input class="a2-input" id="rule_title" name="title" value="{{ old('title') }}" placeholder="{{ __('موسم الصيف') }}">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="rule_type">{{ __('تنطبق على') }}</label>
                <select class="a2-select js-rule-type" id="rule_type" name="rule_type">
                    @foreach($ruleLabels as $key => $label)
                        <option value="{{ $key }}" @selected(old('rule_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group js-rule-weekday" hidden>
                <label class="a2-label" for="rule_weekday">{{ __('اليوم') }}</label>
                <select class="a2-select" id="rule_weekday" name="weekday">
                    <option value="">{{ __('اختر اليوم') }}</option>
                    @foreach($weekdayNames as $key => $label)
                        <option value="{{ $key }}" @selected((string) old('weekday') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group js-rule-range">
                <label class="a2-label" for="rule_start">{{ __('من تاريخ') }}</label>
                <input class="a2-input" id="rule_start" type="date" name="start_date" value="{{ old('start_date') }}">
            </div>

            <div class="a2-form-group js-rule-range">
                <label class="a2-label" for="rule_end">{{ __('إلى تاريخ') }}</label>
                <input class="a2-input" id="rule_end" type="date" name="end_date" value="{{ old('end_date') }}">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="rule_price_type">{{ __('طريقة التسعير') }}</label>
                <select class="a2-select" id="rule_price_type" name="price_type">
                    @foreach($priceLabels as $key => $label)
                        <option value="{{ $key }}" @selected(old('price_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="rule_price_value">{{ __('القيمة') }}</label>
                <input class="a2-input" id="rule_price_value" type="number" step="0.01" name="price_value" value="{{ old('price_value') }}" required>
                <small class="a2-help">{{ __('«ثابت ١٢٠٠» أو «فرق +٢٠٠» أو «نسبة ٢٥».') }}</small>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="rule_priority">{{ __('الأولوية') }}</label>
                <input class="a2-input" id="rule_priority" type="number" min="1" max="999" name="priority" value="{{ old('priority', 100) }}">
                <small class="a2-help">{{ __('الأقل رقمًا يغلب عند تعارض قاعدتين.') }}</small>
            </div>
        </div>

        <button class="a2-btn a2-btn-primary" type="submit">{{ __('إضافة قاعدة') }}</button>
    </form>
</div>

@push('scripts')
<script>
// «يوم من الأسبوع» يسأل عن يوم، وما عداه يسأل عن فترة. إظهارُ الاثنين معًا
// يجعل نصفَ الاستمارة دائمًا بلا معنى.
document.addEventListener('DOMContentLoaded', function () {
    var type = document.querySelector('.js-rule-type');
    if (!type) return;

    function sync() {
        var weekday = type.value === 'weekday';
        document.querySelectorAll('.js-rule-weekday').forEach(function (el) { el.hidden = !weekday; });
        document.querySelectorAll('.js-rule-range').forEach(function (el) { el.hidden = weekday; });
    }

    type.addEventListener('change', sync);
    sync();
});
</script>
@endpush
@endsection
