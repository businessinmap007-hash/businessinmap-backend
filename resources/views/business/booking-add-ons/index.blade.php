@extends('business.layouts.master')

@section('title', __('إضافات الحجز'))

@php
    $picked = collect(old('option_ids', array_keys($addOns)))->map(fn ($id) => (int) $id);
    $oldAdjust = collect(old('adjust', []));
    $oldAdjustType = collect(old('adjust_type', []));

    $value = function (int $id) use ($oldAdjust, $addOns) {
        if ($oldAdjust->has($id)) {
            return $oldAdjust[$id];
        }

        $v = (float) ($addOns[$id]['value'] ?? 0);

        return $v !== 0.0 ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '';
    };

    $type = fn (int $id) => (string) ($oldAdjustType[$id] ?? ($addOns[$id]['type'] ?? 'amount'));

    $oldPerPerson = collect(old('per_person', []));
    $perPerson = fn (int $id) => $oldPerPerson->has($id)
        ? (bool) $oldPerPerson[$id]
        : (bool) ($addOns[$id]['per_person'] ?? false);
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إضافات الحجز') }}</h1>
            <div class="a2-page-subtitle">{{ __('ما يختاره النزيل وقت الحجز — نظام الوجبات وما شابهه. سعر ثابت لا يتغيّر بنوع الغرفة.') }}</div>
    </div>
    <div class="a2-page-actions">
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
        <strong>{{ __('وفي شاشة الوحدة') }}</strong> —
        {{ __('ما يخصّ غرفة بعينها: D117 على المسبح وD118 على البحر، وهما من نفس النوع.') }}
    </div>
</div>

<form method="POST" action="{{ route('business.booking-add-ons.update') }}">
        @csrf
        @method('PUT')

        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('الإضافات وأسعارها') }}</div>
                    <div class="a2-card-sub">
                        {{ __('أشّر ما تقدّمه واكتب سعر كلٍّ منه. يُضاف إلى سعر الفترة إن اختاره النزيل.') }}
                        {{ __('و«لكل فرد» تضرب السعر في عدد النزلاء — إفطار الغرفة الثلاثية ليس كإفطار الفردية.') }}
                    </div>
                </div>
            </div>

            <div class="a2-form-grid">
                <div class="a2-form-group a2-field-full">
                    @foreach($vocabulary as $groupName => $options)
                        <div class="bv-group">
                            <div class="bv-group-head">{{ $groupName }}</div>
                            <div class="bv-chips">
                                @foreach($options as $option)
                                    @php $isDeclared = $declared->contains((int) $option->id); @endphp
                                    <label class="bv-chip {{ $isDeclared ? 'is-declared' : '' }}"
                                           @if($isDeclared) title="{{ __('هذه صفة مثبّتة على وحدات عندك — تُدار من شاشة الوحدة.') }}" @endif>
                                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                               @checked($picked->contains((int) $option->id)) @disabled($isDeclared)>
                                        <span>{{ $option->name_ar ?: $option->name_en }}</span>
                                        <input type="number" step="0.01" class="bv-adjust"
                                               name="adjust[{{ $option->id }}]" value="{{ $value($option->id) }}" placeholder="0">
                                        <select class="bv-adjust-type" name="adjust_type[{{ $option->id }}]">
                                            <option value="amount" @selected($type($option->id) === 'amount')>{{ __('ج') }}</option>
                                            <option value="percent" @selected($type($option->id) === 'percent')>%</option>
                                        </select>
                                        {{-- «لكل فرد»: البحرُ لا يُقسَّم على النزلاء، والإفطارُ يُقسَّم. --}}
                                        <span class="bv-per-person">
                                            <input type="checkbox" name="per_person[{{ $option->id }}]" value="1"
                                                   @checked($perPerson($option->id))>
                                            {{ __('لكل فرد') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- ما أعلنته وحدةٌ عن نفسها لا يصلح إضافةً يختارها النزيل:
                         ثمنُه محسوبٌ فى سعرها المعروض، فعرضُه ثانيةً يُحصّله
                         مرّتين. تُعطَّل هنا ولا تُخفى، حتى يُعرف أين تُدار. --}}
                    <small class="a2-help">{{ __('ما تُزيل علامته يُحذف. والمعطَّل صفةُ وحدة — تُدار من شاشة تلك الوحدة.') }}</small>
                </div>
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
        </div>
    </form>
@endsection

@push('styles')
<style>
    .bv-group { margin-bottom: 10px; }
    .bv-group-head { font-size: 12px; opacity: .7; margin-bottom: 4px; }
    .bv-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .bv-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
               border: 1px solid rgba(128,128,128,.35); border-radius: 999px;
               font-size: 13px; cursor: pointer; }
    .bv-chip:has(input[type=checkbox]:checked) { border-color: currentColor; font-weight: 600; }
    .bv-chip.is-declared { opacity: .45; cursor: not-allowed; }
    .bv-chip .bv-adjust, .bv-chip .bv-adjust-type, .bv-chip .bv-per-person { display: none; }
    .bv-chip:has(> input[type=checkbox]:checked) .bv-adjust,
    .bv-chip:has(> input[type=checkbox]:checked) .bv-adjust-type,
    .bv-chip:has(> input[type=checkbox]:checked) .bv-per-person { display: inline-flex; }
    .bv-per-person { align-items: center; gap: 3px; font-size: 11px; opacity: .85; }
    /* الخانةُ تُقرأ خانةَ اختيار: أكبرُ قليلًا وظاهرةٌ دائمًا. */
    .bv-chip > input[type=checkbox] { width: 15px; height: 15px; }
    .bv-adjust { width: 66px; padding: 1px 4px; font-size: 12px; border-radius: 6px;
                 border: 1px solid rgba(128,128,128,.35); }
    .bv-adjust-type { padding: 1px 2px; font-size: 12px; border-radius: 6px;
                      border: 1px solid rgba(128,128,128,.35); }
</style>
@endpush
