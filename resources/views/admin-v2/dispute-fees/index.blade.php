@extends('admin-v2.layouts.master')

@section('title','Dispute fees')
@section('body_class','admin-v2-dispute-fees')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('رسوم جلسات التحكيم') }}</div>
        <div class="a2-hint" style="margin-bottom:14px;">
            {{ __('سعر واحد للجلسة لكل خدمة — لا سعر للعميل وآخر للنشاط. يتحمله الطرف الخاسر وحده، ويُعلَن للطرفين قبل قبول الجلسة. الحكم لا يحدده.') }}
        </div>

        <form method="POST" action="{{ route('admin.dispute-fees.update') }}">
            @csrf
            @method('PUT')

            <div class="a2-form-grid">
                <div class="a2-form-group">
                    <label class="a2-label" for="fee-default">{{ __('السعر الافتراضي (لأي خدمة بلا سعر خاص)') }}</label>
                    <input class="a2-input" id="fee-default" type="number" step="1" min="0" name="default" required
                           value="{{ old('default', $fees->get('default')?->amount ?? 0) }}">
                    <div class="a2-hint" style="margin-top:6px;">
                        {{ __('يمنع أن تكون خدمة أُضيفت لاحقًا مجانية بصمت — وجلسة بلا ثمن جلسة لا يتردد أحد في طلبها.') }}
                    </div>
                </div>
            </div>

            <div style="overflow-x:auto;margin-top:16px;">
                <table class="a2-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>{{ __('الخدمة') }}</th>
                            <th>{{ __('سعر الجلسة') }}</th>
                            <th>{{ __('الساري') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($services as $service)
                            @php $own = $fees->get($service->id); @endphp
                            <tr>
                                <td style="font-weight:800;">
                                    {{ $service->name_ar ?: $service->name_en }}
                                    <div class="a2-hint">{{ $service->key }}</div>
                                </td>
                                <td>
                                    <input class="a2-input" type="number" step="1" min="0"
                                           name="services[{{ $service->id }}]"
                                           value="{{ old("services.$service->id", $own?->amount) }}"
                                           placeholder="{{ __('اتركه فارغًا لاستخدام الافتراضي') }}">
                                </td>
                                <td style="font-weight:800;">
                                    {{ \App\Models\DisputeFee::amountFor($service->id) }}
                                    @unless($own)
                                        <span class="a2-hint">({{ __('افتراضي') }})</span>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button class="a2-btn a2-btn-primary" style="margin-top:14px;" type="submit">{{ __('حفظ') }}</button>
        </form>
    </div>
</div>
@endsection
