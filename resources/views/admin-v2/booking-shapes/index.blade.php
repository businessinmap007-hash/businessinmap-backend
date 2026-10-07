@extends('admin-v2.layouts.master')

@section('title', 'Booking Shapes')
@section('body_class', 'admin-v2 admin-v2-booking-shapes')

@section('content')
@php
    $name = fn ($m) => trim((string) ($m->name_ar ?? '')) ?: (trim((string) ($m->name_en ?? '')) ?: ('#' . $m->id));
    $patternLabels = ['stay' => 'إقامة', 'duration' => 'مدّة', 'table' => 'طاولة', 'appointment' => 'موعد', 'consultation' => 'استشارة', 'course' => 'كورس'];
@endphp

<style>
    .bs-layout { display: grid; grid-template-columns: 250px minmax(320px, 1fr) 400px; gap: 20px; align-items: start; }
    @media (max-width: 1200px) { .bs-layout { grid-template-columns: 1fr; } }
    .bs-shape { display: block; padding: 10px 12px; border-radius: 10px; color: inherit; text-decoration: none; border: 1px solid transparent; margin-bottom: 6px; }
    .bs-shape:hover { background: rgba(11, 31, 58, .04); }
    .bs-shape.is-active { border-color: #D6A94A; background: rgba(214, 169, 74, .10); }
    .bs-shape small { display: block; opacity: .65; }
    .bs-setting { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(11, 31, 58, .08); }
    .bs-setting:last-child { border-bottom: 0; }
    .bs-setting label { font-weight: 700; display: block; }
    .bs-setting small { display: block; opacity: .65; margin-top: 2px; }
    .bs-trade { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-bottom: 1px dashed rgba(11, 31, 58, .12); }
    .bs-chip { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: rgba(11, 31, 58, .07); }

    /* the phone — the app LIGHT theme (#FAF9F6 / white / navy / gold, Cairo) */
    .bs-phone { width: 360px; height: 740px; border-radius: 38px; border: 10px solid #1b1f26; background: #FAF9F6; overflow: hidden; position: relative; box-shadow: 0 18px 40px rgba(0, 0, 0, .18); font-family: 'Cairo', system-ui, sans-serif; color: #0B1F3A; direction: rtl; margin-inline: auto; }
    .bs-screen { position: absolute; inset: 0; display: flex; flex-direction: column; overflow-y: auto; }
    .bs-screen[hidden] { display: none; }
    .bs-appbar { height: 56px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex: none; }
    .bs-tabs { display: flex; border-bottom: 1px solid rgba(11, 31, 58, .1); flex: none; }
    .bs-tabs span { flex: 1; text-align: center; padding: 10px 0; font-size: 13px; font-weight: 600; }
    .bs-tabs span.is-on { color: #8a6420; border-bottom: 2px solid #D6A94A; }
    .bs-body { padding: 12px 14px; display: flex; flex-direction: column; }
    .bs-sec-head { font-weight: 700; font-size: 15px; margin: 12px 0 8px; display: flex; align-items: center; gap: 8px; }
    .bs-sec-head i { font-style: normal; font-size: 11px; opacity: .6; font-weight: 600; }
    .bs-card { display: flex; gap: 10px; background: #fff; border-radius: 14px; padding: 10px; margin-bottom: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
    .bs-photo { width: 72px; height: 72px; border-radius: 10px; background: linear-gradient(135deg, #cfd8e6, #e8edf5); flex: none; }
    .bs-card-main { flex: 1; min-width: 0; }
    .bs-card-title { font-weight: 700; font-size: 14px; }
    .bs-card-meta { font-size: 12px; opacity: .7; margin-top: 2px; }
    .bs-price { font-weight: 800; margin-top: 6px; }
    .bs-avail { display: inline-block; font-size: 11px; padding: 1px 8px; border-radius: 999px; background: rgba(46, 158, 91, .15); color: #2E9E5B; margin-inline-start: 6px; }
    .bs-btn { background: #D6A94A; color: #0B1F3A; text-align: center; font-weight: 800; border-radius: 12px; padding: 11px; margin-top: 10px; }
    .bs-btn.is-ghost { background: transparent; border: 1.5px solid #D6A94A; }
    .bs-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid rgba(11, 31, 58, .08); font-size: 13px; }
    .bs-radio { display: flex; gap: 8px; align-items: center; padding: 7px 0; font-size: 13px; }
    .bs-radio b { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #8a93a3; display: inline-block; }
    .bs-radio.is-on b { border-color: #D6A94A; background: radial-gradient(#D6A94A 40%, transparent 45%); }
    .bs-radio span:last-child { margin-inline-start: auto; opacity: .8; }
    .bs-total { background: rgba(214, 169, 74, .16); border-radius: 12px; padding: 10px 12px; display: flex; justify-content: space-between; font-weight: 800; margin-top: 10px; }
    .bs-order { display: flex; flex-direction: column; }
    .bs-phone-tabs { display: inline-flex; background: rgba(11, 31, 58, .06); border-radius: 10px; padding: 3px; margin-bottom: 10px; }
    .bs-phone-tabs button { border: 0; background: transparent; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; font-family: inherit; }
    .bs-phone-tabs button.is-on { background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
    [hidden] { display: none !important; }
</style>

<div class="a2-page-header">
    <div>
        <h1 class="a2-page-title">{{ __('أشكال الحجز') }}</h1>
        <div class="a2-page-subtitle">{{ __('الشكل هو كيف تُرسم صفحة الحجز للضيف: الغرف مجمّعة تحت نوعها (كأقسام المنيو) بصورها وأسعارها، وما يُسأل عنه الضيف وبأي ترتيب. جرّب الإعدادات على الموبايل ثم احفظ؛ كل نشاط مربوط بالشكل يتبعه. نمط الحجز (إقامة، مدّة، طاولة…) لا يتغيّر من هنا — الشكل يقرّر كيف يُعرض فقط.') }}</div>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="a2-alert a2-alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
@endif

@if(! $current)
    <div class="a2-card">{{ __('لا توجد أشكال بعد — شغّل BookingShapesSeeder.') }}</div>
@else
<div class="bs-layout">
    {{-- the shapes --}}
    <div class="a2-card">
        @foreach($shapes as $s)
            <a class="bs-shape {{ $s->id === $current->id ? 'is-active' : '' }}" href="{{ route('admin.booking-shapes.index', ['shape' => $s->code]) }}">
                <strong>{{ $name($s) }}</strong>
                <small>{{ $patternLabels[$s->pattern] ?? $s->pattern }} · {{ $counts[$s->id] ?? 0 }} {{ __('نشاط') }}@if(! $s->is_active) · {{ __('موقوف') }}@endif</small>
            </a>
        @endforeach
    </div>

    {{-- settings and trades --}}
    <div>
        <form class="a2-card" method="POST" action="{{ route('admin.booking-shapes.settings', $current) }}" id="bs-form">
            @csrf
            <div class="a2-mb-16">
                <input class="a2-input" name="name_ar" value="{{ old('name_ar', $current->name_ar) }}" placeholder="{{ __('اسم الشكل') }}" required>
                <input class="a2-input" name="name_en" value="{{ old('name_en', $current->name_en) }}" placeholder="English name" style="margin-top:8px">
                <textarea class="a2-input" name="description" rows="2" placeholder="{{ __('ما يفعله هذا الشكل') }}" style="margin-top:8px">{{ old('description', $current->description) }}</textarea>
                <label style="display:flex;gap:8px;align-items:center;margin-top:8px"><input type="checkbox" name="is_active" value="1" @checked($current->is_active)> {{ __('مفعّل') }}</label>
            </div>

            @foreach($definitions as $key => $def)
                <div class="bs-setting">
                    @if($def['type'] === 'bool')
                        <input type="checkbox" id="set-{{ $key }}" name="settings[{{ $key }}]" value="1" data-setting="{{ $key }}" @checked($settings[$key])>
                        <div><label for="set-{{ $key }}">{{ __($def['label']) }}</label><small>{{ __($def['hint']) }}</small></div>
                    @else
                        <div style="flex:1">
                            <label for="set-{{ $key }}">{{ __($def['label']) }}</label>
                            <select class="a2-select" id="set-{{ $key }}" name="settings[{{ $key }}]" data-setting="{{ $key }}">
                                @foreach($def['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected($settings[$key] === $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            <small>{{ __($def['hint']) }}</small>
                        </div>
                    @endif
                </div>
            @endforeach

            <button class="a2-btn a2-btn-primary" type="submit" style="margin-top:12px">{{ __('حفظ الشكل') }}</button>
        </form>

        <div class="a2-card" style="margin-top:16px">
            <h3 class="a2-card-title">{{ __('الأنشطة على هذا الشكل') }} ({{ $assigned->count() }})</h3>
            @forelse($assigned as $t)
                <div class="bs-trade">
                    <span>{{ $t->name_ar }} <span class="bs-chip">{{ $patternLabels[$t->pattern] ?? $t->pattern }}</span></span>
                    <form method="POST" action="{{ route('admin.booking-shapes.unassign', $t->id) }}" onsubmit="return confirm('{{ __('إزالة الربط؟ يعود النشاط إلى شكله القديم.') }}')">
                        @csrf @method('DELETE')
                        <button class="a2-btn a2-btn-sm" type="submit">{{ __('إزالة') }}</button>
                    </form>
                </div>
            @empty
                <div class="a2-text-muted">{{ __('لا نشاط على هذا الشكل.') }}</div>
            @endforelse

            @if($others->isNotEmpty())
                <form method="POST" action="{{ route('admin.booking-shapes.assign', $current) }}" style="margin-top:14px">
                    @csrf
                    <label style="font-weight:700;display:block;margin-bottom:6px">{{ __('أضِف أنشطة إلى هذا الشكل') }}</label>
                    <select class="a2-select" name="child_ids[]" multiple size="8" style="width:100%">
                        @foreach($others as $t)
                            @php $on = $shapeById->get($assignment[$t->id] ?? 0); @endphp
                            <option value="{{ $t->id }}">{{ $t->name_ar }} — {{ $patternLabels[$t->pattern] ?? $t->pattern }}{{ $on ? ' (' . $name($on) . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <button class="a2-btn a2-btn-primary" type="submit" style="margin-top:8px">{{ __('اربطها بهذا الشكل') }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- the phone --}}
    <div>
        <div class="bs-phone-tabs" id="bs-tabs">
            <button type="button" class="is-on" data-screen="list">{{ __('صفحة الحجز') }}</button>
            <button type="button" data-screen="pick">{{ __('بعد اختيار الوحدة') }}</button>
            <button type="button" data-screen="stay">{{ __('أثناء الإقامة') }}</button>
        </div>
        <div class="bs-phone">
            {{-- 1. the booking page: kinds as sections, units under them --}}
            <div class="bs-screen" data-screen="list">
                <div class="bs-appbar">{{ __('فندق الأندلس') }}</div>
                <div class="bs-tabs"><span class="is-on">{{ __('الحجز') }}</span><span>{{ __('المنشورات') }}</span></div>
                <div class="bs-body">
                    @foreach([['الغرف الفردية', [['101', 'غرفة فردية', 1, 800, 800], ['206', 'إطلالة على المسبح', 1, 950, 800]]], ['الغرف المزدوجة', [['210', 'غرفة مزدوجة', 2, 1100, 1100]]]] as [$section, $units])
                        <div class="bs-sec-head" data-sec-head>{{ $section }} <i>{{ count($units) }}</i></div>
                        @foreach($units as [$code, $label, $cap, $withF, $withoutF])
                            <div class="bs-card">
                                <div class="bs-photo" data-show="show_photos"></div>
                                <div class="bs-card-main">
                                    <div class="bs-card-title">{{ __('غرفة') }} {{ $code }}<span class="bs-avail" data-show="show_availability">{{ __('متاحة') }}</span></div>
                                    <div class="bs-card-meta">{{ __($label) }}<span data-show="show_capacity"> · {{ __('السعة') }} {{ $cap }}</span></div>
                                    <div class="bs-price" data-show="show_price"><span data-price data-with="{{ $withF }}" data-without="{{ $withoutF }}">{{ $withF }}</span> EGP / {{ __('ليلة') }}</div>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- 2. after a unit: add-ons, dates, total — the order is a setting --}}
            <div class="bs-screen" data-screen="pick" hidden>
                <div class="bs-appbar">{{ __('الحجز') }}</div>
                <div class="bs-body bs-order" id="bs-order">
                    <div data-block="unit" style="order:1">
                        <div class="bs-sec-head">{{ __('الوحدة') }}</div>
                        <div class="bs-card"><div class="bs-photo" data-show="show_photos"></div><div class="bs-card-main"><div class="bs-card-title">{{ __('غرفة') }} 206</div><div class="bs-card-meta">{{ __('إطلالة على المسبح') }}</div><div class="bs-price" data-show="show_price"><span data-price data-with="950" data-without="800">950</span> EGP</div></div></div>
                        <div class="bs-btn is-ghost" data-show="offer_day_use" style="margin-top:0">Day use · 09:00–18:00 · 350</div>
                    </div>
                    <div data-block="addons" style="order:2">
                        <div class="bs-sec-head">{{ __('نظام الوجبات') }}</div>
                        <div class="bs-radio is-on"><b></b><span>{{ __('بدون') }}</span></div>
                        <div class="bs-radio"><b></b><span>{{ __('شامل الإفطار') }}</span><span>+100</span></div>
                        <div class="bs-radio"><b></b><span>{{ __('نصف إقامة') }}</span><span>+250</span></div>
                        <div class="bs-radio"><b></b><span>{{ __('إقامة كاملة') }}</span><span>+500</span></div>
                    </div>
                    <div data-block="dates" style="order:3">
                        <div class="bs-sec-head">{{ __('التواريخ') }}</div>
                        <div class="bs-row"><span>{{ __('من') }}</span><span>10/12</span></div>
                        <div class="bs-row"><span>{{ __('إلى') }}</span><span>10/14</span></div>
                        <div class="bs-row" data-show="ask_guest_counts"><span>{{ __('عدد النزلاء') }}</span><span>2</span></div>
                        <div class="bs-row" data-show="ask_children"><span>{{ __('عدد الأطفال') }}</span><span>0</span></div>
                    </div>
                    <div data-block="total" style="order:4">
                        <div class="bs-total"><span>{{ __('الإجمالي') }}</span><span><span data-total data-with="2400" data-without="2200">2400</span> EGP</span></div>
                        <div class="bs-btn">{{ __('احجز الآن') }}</div>
                    </div>
                </div>
            </div>

            {{-- 3. a running stay --}}
            <div class="bs-screen" data-screen="stay" hidden>
                <div class="bs-appbar">{{ __('حجزك') }}</div>
                <div class="bs-body">
                    <div class="bs-card"><div class="bs-card-main"><div class="bs-card-title">{{ __('غرفتك') }}: 206</div><div class="bs-card-meta">{{ __('جارية') }}</div></div></div>
                    <div data-show="in_stay_requests">
                        <div class="bs-btn is-ghost">{{ __('بلّغ عن مشكلة') }}</div>
                        <div class="bs-btn is-ghost">{{ __('اطلب خدمة') }}</div>
                    </div>
                    <div class="a2-text-muted" data-hide="in_stay_requests" style="margin-top:12px">{{ __('لا أزرار طلبات في هذا الشكل.') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('bs-form');
    var phone = document.querySelector('.bs-phone');
    function val(key) {
        var el = form.querySelector('[data-setting="' + key + '"]');
        if (!el) return null;
        return el.type === 'checkbox' ? el.checked : el.value;
    }
    function apply() {
        phone.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = !val(el.dataset.show); });
        phone.querySelectorAll('[data-hide]').forEach(function (el) { el.hidden = !!val(el.dataset.hide); });
        var sections = val('layout') === 'sections';
        phone.querySelectorAll('[data-sec-head]').forEach(function (el) { el.hidden = !sections; });
        var withF = !!val('price_includes_features');
        phone.querySelectorAll('[data-price],[data-total]').forEach(function (el) { el.textContent = withF ? el.dataset.with : el.dataset.without; });
        var unitFirst = val('pick_order') === 'unit_then_dates';
        // unit_then_dates: unit, add-ons, dates, total — dates_then_unit: dates, unit, add-ons, total
        var order = unitFirst ? {unit: 1, addons: 2, dates: 3, total: 4} : {dates: 1, unit: 2, addons: 3, total: 4};
        Object.keys(order).forEach(function (k) {
            var b = phone.querySelector('[data-block="' + k + '"]');
            if (b) b.style.order = order[k];
        });
    }
    form.addEventListener('change', apply);
    apply();

    document.querySelectorAll('#bs-tabs button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#bs-tabs button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
            phone.querySelectorAll('.bs-screen').forEach(function (s) { s.hidden = s.dataset.screen !== btn.dataset.screen; });
        });
    });
})();
</script>
@endif
@endsection
