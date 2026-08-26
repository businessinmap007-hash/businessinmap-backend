@extends('business.layouts.master')

@section('title', __('تعبئة الرفوف'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('تعبئة الرفوف') }}</h1>
        <div class="a2-page-subtitle">
            {{ __('كل قسم هو مجموعة خيارات، وكل صنف تحته سطرها. اكتب السعر لتظهره في منيوك — الصف بلا سعر يبقى كما هو.') }}
        </div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('الأصناف') }}</a>
        <a href="{{ route('business.menu.review') }}" class="a2-btn a2-btn-ghost">{{ __('مراجعة القائمة') }}</a>
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

<form method="POST" action="{{ route('business.menu.catalog.update') }}">
    @csrf
    @method('PUT')

    @forelse($groups as $group)
        <details class="a2-card a2-mb-16" @if($group['filled'] > 0) open @endif>
            <summary class="a2-card-head" style="cursor:pointer;">
                <div>
                    <div class="a2-card-title">{{ $group['name'] }}</div>
                    <div class="a2-card-sub">
                        {{ __(':filled من :total معبّى', ['filled' => $group['filled'], 'total' => count($group['rows'])]) }}
                    </div>
                </div>
            </summary>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>{{ __('الصنف') }}</th>
                            <th>{{ __('الكمية') }}</th>
                            <th>{{ __('سعر التوريد') }}</th>
                            <th>{{ __('سعر البيع') }}</th>
                            <th>{{ __('الوحدة') }}</th>
                            <th>{{ __('الشركة / الماركة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['rows'] as $row)
                            @php $item = $row['item']; @endphp
                            <tr>
                                <td class="a2-fw-900">{{ $row['name'] }}</td>
                                <td>
                                    <input class="a2-input" type="number" min="0" style="min-width:90px;"
                                           name="rows[{{ $row['option_id'] }}][quantity]"
                                           value="{{ old('rows.' . $row['option_id'] . '.quantity', $item->available_quantity ?? '') }}">
                                </td>
                                <td>
                                    <input class="a2-input" inputmode="decimal" style="min-width:100px;"
                                           name="rows[{{ $row['option_id'] }}][supply_price]"
                                           placeholder="{{ __('اختياري') }}"
                                           value="{{ old('rows.' . $row['option_id'] . '.supply_price', $item->supply_price ?? '') }}">
                                </td>
                                <td>
                                    <input class="a2-input" inputmode="decimal" style="min-width:100px;"
                                           name="rows[{{ $row['option_id'] }}][base_price]"
                                           placeholder="0.00"
                                           value="{{ old('rows.' . $row['option_id'] . '.base_price', $item->base_price ?? '') }}">
                                </td>
                                <td>
                                    <select class="a2-select" name="rows[{{ $row['option_id'] }}][sale_unit]">
                                        <option value="">{{ __('— بالقطعة —') }}</option>
                                        @foreach($saleUnits as $code => $label)
                                            <option value="{{ $code }}" @selected((string) old('rows.' . $row['option_id'] . '.sale_unit', $item->sale_unit ?? '') === (string) $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input class="a2-input" style="min-width:120px;"
                                           name="rows[{{ $row['option_id'] }}][brand_name]"
                                           placeholder="{{ __('اختياري') }}"
                                           value="{{ old('rows.' . $row['option_id'] . '.brand_name', $item->brand_name ?? '') }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @empty
        <div class="a2-card a2-empty">{{ __('لا مفردات متاحة لنشاطك بعد.') }}</div>
    @endforelse

    @if(count($groups) > 0)
        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:8px;">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ الكل') }}</button>
        </div>
    @endif
</form>
@endsection
