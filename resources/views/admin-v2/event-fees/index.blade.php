@extends('admin-v2.layouts.master')

@section('title','Event fees')
@section('body_class','admin-v2-event-fees')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('رسوم الأحداث') }}</div>
        <div class="a2-hint" style="margin-bottom:14px;">
            {{ __('رسوم المنصة على ما لا يدخل في حجز أو توصيل: تبادل أرقام الهاتف على إعلان من المالك، وتغيير البرنامج التدريبي أو الغذائي. تُحدَّد لكل طرف على حدة، مبلغًا ثابتًا أو نسبة بحد أدنى وأعلى، ولا تُحصَّل إلا إذا كان رصيد كل طرف عليه رسم يكفيها. الرسم المعطَّل لا يُحصَّل.') }}
        </div>

        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.event-fees.update') }}">
            @csrf
            @method('PUT')

            @foreach($events as $key => $event)
                <div class="a2-title" style="font-size:14px;margin:18px 0 4px;">{{ $event[0] }}</div>
                <div class="a2-hint" style="margin-bottom:8px;">{{ __('النسبة تُحسب من') }}: {{ $event[2] }}</div>

                <div class="a2-table-wrap">
                    <table class="a2-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>{{ __('الطرف') }}</th>
                                <th>{{ __('مفعّل') }}</th>
                                <th>{{ __('النوع') }}</th>
                                <th>{{ __('القيمة') }}</th>
                                <th>{{ __('حد أدنى') }}</th>
                                <th>{{ __('حد أعلى') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($event[3] as $role => $names)
                                @php
                                    $row = optional($rows->get($key))->firstWhere('role', $role);
                                    $n = "fees[$key][$role]";
                                    $type = old("fees.$key.$role.calc_type", $row?->calc_type ?? 'fixed');
                                @endphp
                                <tr>
                                    <td style="font-weight:800;">{{ $names[0] }}</td>
                                    <td>
                                        <input type="hidden" name="{{ $n }}[enabled]" value="0">
                                        <input type="checkbox" name="{{ $n }}[enabled]" value="1" @checked(old("fees.$key.$role.enabled", $row?->enabled))>
                                    </td>
                                    <td>
                                        <select class="a2-input" name="{{ $n }}[calc_type]">
                                            <option value="fixed" @selected($type === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                                            <option value="percent" @selected($type === 'percent')>{{ __('نسبة %') }}</option>
                                        </select>
                                    </td>
                                    <td><input class="a2-input" type="number" step="0.01" min="0" name="{{ $n }}[amount]" required value="{{ old("fees.$key.$role.amount", $row?->amount ?? 0) }}"></td>
                                    <td><input class="a2-input" type="number" step="0.01" min="0" name="{{ $n }}[min_amount]" value="{{ old("fees.$key.$role.min_amount", $row?->min_amount) }}"></td>
                                    <td><input class="a2-input" type="number" step="0.01" min="0" name="{{ $n }}[max_amount]" value="{{ old("fees.$key.$role.max_amount", $row?->max_amount) }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            <button class="a2-btn a2-btn-primary" style="margin-top:14px;" type="submit">{{ __('حفظ') }}</button>
        </form>
    </div>
</div>
@endsection
