@extends('business.layouts.master')

@section('title', __('الإضافات والمميزات'))

@php
    // القائمتان تُقرآن من مكانٍ واحد: كلُّ سعرٍ مكتوبٍ على النشاط مؤشَّر.
    $picked = collect(old('option_ids', array_keys($addOns)))
        ->merge(old('feature_ids', []))
        ->map(fn ($id) => (int) $id);
    $oldAdjust = collect(old('adjust', []));
    $oldAdjustType = collect(old('adjust_type', []));
    $oldPerPerson = collect(old('per_person', []));

    $value = function (int $id) use ($oldAdjust, $addOns) {
        if ($oldAdjust->has($id)) {
            return $oldAdjust[$id];
        }

        $v = (float) ($addOns[$id]['value'] ?? 0);

        return $v !== 0.0 ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '';
    };

    $type = fn (int $id) => (string) ($oldAdjustType[$id] ?? ($addOns[$id]['type'] ?? 'amount'));

    $perPerson = fn (int $id) => $oldPerPerson->has($id)
        ? (bool) $oldPerPerson[$id]
        : (bool) ($addOns[$id]['per_person'] ?? false);
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('الإضافات والمميزات') }}</h1>
        <div class="a2-page-subtitle">{{ __('كل سعر يُكتب هنا مرّة واحدة: ما يختاره النزيل، وما يميّز وحدة بعينها.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.booking-settings.edit') }}" class="a2-btn a2-btn-ghost">{{ __('إعدادات الخدمات') }}</a>
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('وحداتي') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

{{-- الفرقُ الذى تقوم عليه الشاشتان، مكتوبًا حيث يُقرأ. --}}
<div class="a2-alert a2-alert-info">
    <div><strong>{{ __('هنا') }}</strong> — {{ __('ما يقرّره النزيل وسعره واحد مع كل الغرف: «إفطار»، «إقامة كاملة». لا يزيد بتغيير النوع من فردية إلى مزدوجة — ويزيد بعدد الأفراد إن أشّرت «لكل فرد».') }}</div>
    <div style="margin-top:6px;">
        <strong>{{ __('والمميزات') }}</strong> —
        {{ __('سعرها هنا أيضًا، ويُؤشَّر في شاشة الوحدة أيُّ غرفة تحملها: D117 على المسبح وD118 على البحر، وهما من نفس النوع.') }}
    </div>
</div>

<form method="POST" action="{{ route('business.booking-add-ons.update') }}">
    @csrf
    @method('PUT')

    @php
        /*
         * قائمتان بنفس الشكل ويفترقان فيمن يقرّر:
         *   الإضافةُ يختارها النزيلُ وقت الحجز.
         *   والميزةُ تحملها غرفةٌ بعينها، فتُحسب بلا أن يُسأل عنها.
         *
         * والسعرُ يُكتب هنا مرّةً فى الحالتين. وكان سعرُ الميزة يُكتب داخل كل
         * غرفة، فستُّ غرفٍ مطلّة ستُّ فرصٍ لرقمٍ مختلف.
         */
        $sections = [
            ['kind' => 'addon', 'field' => 'option_ids', 'groups' => $vocabulary,
             'note' => __('يختارها النزيل عند الحجز، وتُضاف إلى سعر الفترة إن اختارها.')],
            ['kind' => 'feature', 'field' => 'feature_ids', 'groups' => $features,
             'note' => __('تزيد على سعر الوحدة التي تحملها. تؤشَّر الغرف من شاشة «وحداتي».')],
        ];
    @endphp

    @foreach($sections as $section)
    @foreach($section['groups'] as $groupName => $options)
        <div class="a2-card a2-card--section" style="margin-bottom:14px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">
                        {{ $groupName }}
                        <span class="ao-kind">{{ $section['kind'] === 'addon' ? __('يختارها النزيل') : __('ميزة وحدة') }}</span>
                    </div>
                    <div class="a2-card-sub">{{ $section['note'] }}</div>
                </div>
            </div>

            {{-- جدولٌ لا أقراص.
                 كان كلُّ سطرٍ <label> واحدًا يلفّ خانتَى اختيار: خانةُ الاختيار
                 وخانةُ «لكل فرد». و<label> يوجّه أىَّ نقرةٍ فيه إلى أوّل حقلٍ
                 فيه، فالضغط على اليسرى يقلب اليمنى — فبدت الثانيةُ معطّلة.
                 كلُّ خانةٍ الآن فى <label> خاصٍّ بها لا يلفّ غيرَها. --}}
            <div class="a2-table-wrap">
                <table class="a2-table ao-table">
                    <thead>
                        <tr>
                            <th style="width:44px"></th>
                            <th>{{ __('الإضافة') }}</th>
                            <th style="width:130px">{{ __('السعر') }}</th>
                            <th style="width:90px">{{ __('الوحدة') }}</th>
                            <th style="width:120px">{{ __('لكل فرد') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($options as $option)
                            @php
                                $id = (int) $option->id;
                                $isOn = $picked->contains($id);
                            @endphp
                            <tr class="ao-row {{ $isOn ? 'is-on' : '' }}">
                                <td>
                                    <input type="checkbox" class="ao-pick" id="ao_{{ $id }}"
                                           name="{{ $section['field'] }}[]" value="{{ $id }}" @checked($isOn)>
                                </td>
                                <td>
                                    <label for="ao_{{ $id }}" class="ao-name">{{ $option->name_ar ?: $option->name_en }}</label>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="a2-input ao-price"
                                           name="adjust[{{ $id }}]" value="{{ $value($id) }}" placeholder="0">
                                </td>
                                <td>
                                    <select class="a2-select ao-unit" name="adjust_type[{{ $id }}]">
                                        <option value="amount" @selected($type($id) === 'amount')>{{ __('ج') }}</option>
                                        <option value="percent" @selected($type($id) === 'percent')>%</option>
                                    </select>
                                </td>
                                <td>
                                    {{-- <label> يلفّ خانتَه وحدها: لفُّ خانتين
                                         فى واحدٍ يجعل النقرَ على إحداهما يقلب
                                         الأخرى، فتبدو الثانيةُ معطّلة. --}}
                                    <label class="ao-per">
                                        <input type="checkbox" name="per_person[{{ $id }}]" value="1"
                                               @checked($perPerson($id))>
                                        <span>{{ __('× عدد الأفراد') }}</span>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
    @endforeach

    <div class="a2-card a2-card--soft">
        <div class="a2-card-sub">
            {{ __('ما تُزيل علامته يُحذف عند الحفظ.') }}
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
    </div>
</form>
@endsection

@push('styles')
<style>
    .ao-table td { vertical-align: middle; }

    /* خانةُ الاختيار تُقرأ خانةَ اختيار. */
    .ao-table input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; }

    .ao-name { cursor: pointer; font-weight: 600; }

    /* السعرُ باهتٌ حتى تُؤشَّر إضافتُه: الصفُّ كلُّه يقول إن كان يعمل أو لا،
       بدل أن يختفى الحقلُ فيقفز الجدول. */
    .ao-row:not(.is-on) .ao-price,
    .ao-row:not(.is-on) .ao-unit,
    .ao-row:not(.is-on) .ao-per { opacity: .4; }

    .ao-row.is-on { background: rgba(128, 128, 128, .06); }
    .ao-row.is-declared { opacity: .5; }

    .ao-per { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; }

    /* شارةٌ تقول أىُّ قسمٍ هذا، فالجدولان متطابقان فى الشكل. */
    .ao-kind { font-size: 11px; font-weight: 400; opacity: .7; padding: 2px 8px;
               border: 1px solid rgba(128,128,128,.35); border-radius: 999px; margin-inline-start: 6px; }
    .ao-price { max-width: 110px; }
    .ao-unit { max-width: 80px; }
</style>
@endpush

@push('scripts')
<script>
// الصفُّ يضىء بمجرّد التأشير، فلا ينتظر التاجرُ الحفظَ ليعرف ما هو مُفعَّل.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ao-pick').forEach(function (box) {
        box.addEventListener('change', function () {
            box.closest('.ao-row').classList.toggle('is-on', box.checked);
        });
    });
});
</script>
@endpush
