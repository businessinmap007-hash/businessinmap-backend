@extends('business.layouts.master')

@section('title', __('إعدادات الخدمات'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إعدادات الخدمات') }}</h1>
        <div class="a2-page-subtitle">{{ __('دور كل مجموعة من كلماتك، ثم تفاصيل نمط الحجز. اترك أى حقل فارغاً ليعمل كما يعمل نمطك افتراضياً.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('وحداتي') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if(empty($patterns))
    <div class="a2-alert a2-alert-warning">
        {{ __('تصنيفك لا يقدّم خدمة الحجز، فلا شىء هنا لضبطه.') }}
    </div>
@else
<form method="POST" action="{{ route('business.booking-settings.update') }}">
    @csrf
    @method('PUT')

    {{-- ───────── دور كل مجموعة ─────────
         الحاجزُ بين «السطر» و«المُوصِّف» مفتوحٌ عمدًا، فكلُّ مجموعةٍ تصلح
         للخانتين — ولا شىءَ كان يقول أيُّها أساسُ السعر وأيُّها يزيد عليه
         وأيُّها يُسعَّر وحده. يُعلَن هنا مرّةً، فتقرؤه الشاشاتُ الثلاث. --}}
    @if(! empty($groups))
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('دور كل مجموعة') }}</div>
                <div class="a2-card-sub">
                    {{ __('كلماتك التي اخترتها لنفسك. قل لكل مجموعة ماذا تفعل بالسعر، فتظهر في شاشتها وحدها.') }}
                </div>
            </div>
        </div>

        {{-- المثالُ كاملًا، لأن الأدوارَ الثلاثة تُفهم معًا لا واحدًا واحدًا. --}}
        <div class="a2-alert a2-alert-info">
            <div>{{ __('«الغرف» أساس السعر — غرفة مزدوجة ٩٠٠.') }}</div>
            <div>{{ __('«إطلالة الوحدة» تزيد على سعر الوحدة — D117 المطلة على البحر ‎+٢٠٠ فتصير ١١٠٠.') }}</div>
            <div>{{ __('«نظام الوجبات» إضافة بسعر منفصل — إفطار ٥٠ × عدد الأفراد، يختاره النزيل أو يتركه.') }}</div>
        </div>

        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('المجموعة') }}</th>
                    <th>{{ __('كلماتها') }}</th>
                    <th style="width:220px">{{ __('دورها') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groups as $group)
                    @php $current = (string) old('group_roles.' . $group['id'], $groupRoles[$group['id']] ?? ''); @endphp
                    <tr>
                        <td><strong>{{ $group['name'] }}</strong></td>
                        <td class="a2-muted">{{ $group['options'] }}</td>
                        <td>
                            <select class="a2-select" name="group_roles[{{ $group['id'] }}]">
                                @foreach(\App\Services\BookingVocabularyRoles::labels() as $role => $label)
                                    <option value="{{ $role }}" @selected($current === (string) $role)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- مجموعةٌ بلا دورٍ تظهر فى الجميع: الإعلانُ يضيّق ولا يُشترط، فلا
             ينكسر تاجرٌ لم يفتح هذه الشاشة قطّ. --}}
        <small class="a2-help">{{ __('«بلا تحديد» تُبقي المجموعة ظاهرة في كل الشاشات، كما كانت.') }}</small>
    </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('نمط الحجز') }}</div>
                <div class="a2-card-sub">{{ __('ما الذى يحجزه عميلك عندك فعلاً.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                @if(count($patterns) === 1)
                    <div class="a2-static">{{ $patterns[0]->label() }}</div>
                    <input type="hidden" name="pattern" value="{{ $patterns[0]->value }}">
                @else
                    <label class="a2-label" for="pattern">{{ __('اختر النمط') }}</label>
                    <select id="pattern" name="pattern" class="a2-input">
                        @foreach($patterns as $pattern)
                            <option value="{{ $pattern->value }}" @selected(old('pattern', optional($chosen)->value) === $pattern->value)>
                                {{ $pattern->label() }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>
    </div>

    @if($chosen && $chosen->unit() === \App\Enums\BookingPattern::UNIT_OPTIONAL)
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('وحداتك') }}</div>
                <div class="a2-card-sub">{{ __('صالة بلايستيشن تؤجّر أجهزة، والجيم يبيع دخولاً فقط. أنت وحدك تعرف.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="uses_units">{{ __('هل يختار العميل وحدة بعينها؟') }}</label>
                <select id="uses_units" name="uses_units" class="a2-input">
                    <option value="" @selected(old('uses_units', $row->uses_units === null ? '' : (int) $row->uses_units) === '')>{{ __('لم أحدّد بعد') }}</option>
                    <option value="1" @selected((string) old('uses_units', $row->uses_units === null ? '' : (int) $row->uses_units) === '1')>{{ __('نعم — عندى وحدات يختار منها') }}</option>
                    <option value="0" @selected((string) old('uses_units', $row->uses_units === null ? '' : (int) $row->uses_units) === '0')>{{ __('لا — يحجز الوقت عندى مباشرة') }}</option>
                </select>
            </div>
        </div>
    </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('الوقت') }}</div>
                <div class="a2-card-sub">{{ __('طول الفترة الواحدة، وكم قبل الموعد يُقبل الحجز.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="slot_minutes">{{ __('طول الفترة (بالدقائق)') }}</label>
                <input type="number" min="5" max="1440" id="slot_minutes" name="slot_minutes"
                    class="a2-input @error('slot_minutes') a2-input-error @enderror"
                    value="{{ old('slot_minutes', $row->slot_minutes) }}">
                @error('slot_minutes')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="lead_time_minutes">{{ __('أقل مهلة قبل الموعد (بالدقائق)') }}</label>
                <input type="number" min="0" id="lead_time_minutes" name="lead_time_minutes"
                    class="a2-input @error('lead_time_minutes') a2-input-error @enderror"
                    value="{{ old('lead_time_minutes', $row->lead_time_minutes) }}">
                @error('lead_time_minutes')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>

            @if($chosen === \App\Enums\BookingPattern::STAY)
            <div class="a2-form-group">
                <label class="a2-label" for="min_nights">{{ __('أقل عدد ليالٍ') }}</label>
                <input type="number" min="1" max="255" id="min_nights" name="min_nights"
                    class="a2-input @error('min_nights') a2-input-error @enderror"
                    value="{{ old('min_nights', $row->min_nights) }}">
                @error('min_nights')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
            @endif
        </div>
    </div>

    @if($chosen === \App\Enums\BookingPattern::APPOINTMENT)
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('مكان الموعد') }}</div>
                <div class="a2-card-sub">{{ __('السبّاك يذهب إلى العميل، وتاجر الرخام يستقبله.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <select id="visit_mode" name="visit_mode" class="a2-input">
                    <option value="{{ \App\Models\BusinessBookingSetting::VISIT_AT_BUSINESS }}" @selected(old('visit_mode', $row->visit_mode) === \App\Models\BusinessBookingSetting::VISIT_AT_BUSINESS)>{{ __('عندى — يأتى العميل إلىّ') }}</option>
                    <option value="{{ \App\Models\BusinessBookingSetting::VISIT_AT_CUSTOMER }}" @selected(old('visit_mode', $row->visit_mode) === \App\Models\BusinessBookingSetting::VISIT_AT_CUSTOMER)>{{ __('عند العميل — أذهب إليه') }}</option>
                    <option value="{{ \App\Models\BusinessBookingSetting::VISIT_BOTH }}" @selected(old('visit_mode', $row->visit_mode) === \App\Models\BusinessBookingSetting::VISIT_BOTH)>{{ __('الاثنان — ويختار العميل') }}</option>
                </select>
            </div>
        </div>
    </div>
    @endif

    @if($chosen === \App\Enums\BookingPattern::CONSULTATION)
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('قنوات الاستشارة') }}</div>
                <div class="a2-card-sub">{{ __('اترك الاثنين إن كنت تقدّمهما.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            @foreach([\App\Models\BusinessBookingSetting::CHANNEL_IN_PERSON => __('حضورياً'), \App\Models\BusinessBookingSetting::CHANNEL_ONLINE => __('أونلاين')] as $value => $label)
            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="channels[]" value="{{ $value }}"
                        @checked(in_array($value, old('channels', $row->channels ?? []), true))>
                    <span>{{ $label }}</span>
                </label>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('حقل الملاحظات') }}</div>
                <div class="a2-card-sub">{{ __('ماذا تريد أن تسأل عميلك؟ مثال: سبب الزيارة، رقم الشقة.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <input type="text" maxlength="120" id="notes_label" name="notes_label"
                    class="a2-input @error('notes_label') a2-input-error @enderror"
                    value="{{ old('notes_label', $row->notes_label) }}"
                    placeholder="{{ __('ملاحظات') }}">
                @error('notes_label')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    @if($chosen && array_diff($chosen->asks(), $chosen->requires()))
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('ما لا أقبل الحجز بدونه') }}</div>
                <div class="a2-card-sub">{{ __('نمطك يضمن أن الحجز قابل للتنفيذ؛ وهذا ما تشترطه أنت فوقه.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            @foreach(array_diff($chosen->asks(), $chosen->requires()) as $field)
            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="requires[]" value="{{ $field }}"
                        @checked(in_array($field, old('requires', $row->requires ?? []), true))>
                    <span>{{ __('booking.field.' . $field) }}</span>
                </label>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($shape)
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('ما يراه عميلك') }}</div>
                <div class="a2-card-sub">{{ __('نتيجة نمطك وإعداداتك معاً.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                @foreach($shape['asks'] as $field)
                    <span class="a2-badge">{{ __('booking.field.' . $field) }}@if(in_array($field, $shape['requires'], true)) *@endif</span>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
    </div>
</form>
@endif
@endsection
