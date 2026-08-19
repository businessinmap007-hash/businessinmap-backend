@extends('business.layouts.master')

@section('title', __('ملف النشاط'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('ملف النشاط') }}</h1>
        <div class="a2-page-subtitle">{{ __('من أنت، وكيف تُرى فى التطبيق، ومتى تفتح.') }}</div>
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

{{-- استمارةٌ واحدة بالملفات: تزوير المِنهاج يجعل الطلب POST فعليًّا، فالملفاتُ
     تصل مع PUT ولا تحتاج بابًا ثانيًا لرفعها. --}}
<form method="POST" action="{{ route('business.profile.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('الهوية') }}</div>
                <div class="a2-card-sub">{{ __('الاسم والنبذة كما يقرؤهما العميل فى صفحتك.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="name">{{ __('اسم النشاط') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="name" name="name" value="{{ old('name', $row->name) }}" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="name_en">{{ __('الاسم بالإنجليزية') }}</label>
                <input class="a2-input" id="name_en" name="name_en" value="{{ old('name_en', $row->name_en) }}">
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="about">{{ __('نبذة عن النشاط') }}</label>
                <textarea class="a2-input" id="about" name="about" rows="4"
                          placeholder="{{ __('فندق على كورنيش النيل، ٤٠ غرفة، مطعم وكافيه ومواقف خاصة.') }}">{{ old('about', $row->about) }}</textarea>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section" style="margin-top:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('الصور') }}</div>
                <div class="a2-card-sub">{{ __('الشعار يظهر بجانب اسمك، والغلاف فى أعلى صفحتك.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            @foreach([
                'logo' => __('الشعار'),
                'cover' => __('صورة الغلاف'),
                'image' => __('صورة إضافية'),
            ] as $slot => $label)
                <div class="a2-form-group">
                    <label class="a2-label" for="{{ $slot }}">{{ $label }}</label>

                    @if($row->{$slot})
                        <img src="{{ asset($row->{$slot}) }}" alt=""
                             style="width:140px;height:100px;object-fit:cover;border-radius:8px;display:block;margin-bottom:8px;">
                        <label class="a2-check" style="margin-bottom:8px;">
                            <input type="checkbox" name="remove[]" value="{{ $slot }}">
                            <span>{{ __('احذفها عند الحفظ') }}</span>
                        </label>
                    @else
                        <div class="a2-muted" style="margin-bottom:8px;">{{ __('لا صورة بعد.') }}</div>
                    @endif

                    <input class="a2-input" id="{{ $slot }}" type="file" name="{{ $slot }}" accept="image/*">
                </div>
            @endforeach
        </div>
    </div>

    <div class="a2-card a2-card--section" style="margin-top:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('التواصل والموقع') }}</div>
                <div class="a2-card-sub">{{ __('الإحداثيات تضعك على الخريطة وتدخلك فى البحث «الأقرب إلىّ».') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="phone">{{ __('الهاتف') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="phone" name="phone" value="{{ old('phone', $row->phone) }}" required>
            </div>

            {{-- البريدُ هويةُ الدخول لا بيانَ عرض: يُقرأ هنا ويُغيَّر من باب له تحقّقُه. --}}
            <div class="a2-form-group">
                <label class="a2-label">{{ __('البريد الإلكتروني') }}</label>
                <input class="a2-input" value="{{ $row->email }}" disabled>
                <small class="a2-help">{{ __('بريد الدخول — لا يُغيَّر من هنا.') }}</small>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="latitude">{{ __('خط العرض') }}</label>
                <input class="a2-input" id="latitude" name="latitude" inputmode="decimal"
                       value="{{ old('latitude', $row->latitude) }}" placeholder="30.0444">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="longitude">{{ __('خط الطول') }}</label>
                <input class="a2-input" id="longitude" name="longitude" inputmode="decimal"
                       value="{{ old('longitude', $row->longitude) }}" placeholder="31.2357">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="timezone">{{ __('المنطقة الزمنية') }}</label>
                <select class="a2-select" id="timezone" name="timezone">
                    <option value="">{{ __('الافتراضية') }}</option>
                    @foreach($timezones as $tz)
                        <option value="{{ $tz }}" @selected(old('timezone', $row->timezone) === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
                <small class="a2-help">{{ __('تُقاس بها مواعيد عملك و«مفتوح الآن».') }}</small>
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
    </div>
</form>

{{-- ───────── مواعيد العمل ─────────
     استمارةٌ ثانية عن قصد: حفظُ الاسم لا يجوز أن يعيد كتابة الأسبوع، وحفظُ
     الأسبوع لا يجوز أن يطلب صورةً من جديد. --}}
<form method="POST" action="{{ route('business.profile.hours') }}" style="margin-top:16px;">
    @csrf
    @method('PUT')

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('مواعيد العمل') }}</div>
                <div class="a2-card-sub">{{ __('يومٌ بلا مواعيد يعني «غير محدد» — لا يخفيك من البحث ولا يظهرك مفتوحًا.') }}</div>
            </div>
        </div>

        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('اليوم') }}</th>
                    <th>{{ __('مغلق') }}</th>
                    <th>{{ __('من') }}</th>
                    <th>{{ __('إلى') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($week as $i => $day)
                    <tr>
                        <td>
                            {{ __($day['name']) }}
                            <input type="hidden" name="days[{{ $i }}][day]" value="{{ $day['day'] }}">
                        </td>
                        <td>
                            <input type="checkbox" name="days[{{ $i }}][is_closed]" value="1" @checked($day['is_closed'])>
                        </td>
                        <td><input class="a2-input" type="time" name="days[{{ $i }}][open]" value="{{ $day['open'] }}" style="width:120px"></td>
                        <td><input class="a2-input" type="time" name="days[{{ $i }}][close]" value="{{ $day['close'] }}" style="width:120px"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:12px;">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ المواعيد') }}</button>
        </div>
    </div>
</form>
@endsection
