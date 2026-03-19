@props([
    'startName' => 'start_date',
    'endName' => 'end_date',
    'startLabel' => 'Start Date',
    'endLabel' => 'End Date',
    'startValue' => '',
    'endValue' => '',
    'required' => false,
    'min' => null,
    'max' => null,
    'hint' => null,
])

<div class="a2-form-grid">
    <div class="a2-form-group">
        <label class="a2-label">
            {{ $startLabel }}
            @if($required)
                <span class="a2-danger">*</span>
            @endif
        </label>

        <input
            class="a2-input js-a2-date-range-start"
            type="date"
            name="{{ $startName }}"
            value="{{ old($startName, $startValue) }}"
            @if($required) required @endif
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
        >

        @error($startName)
            <div class="a2-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="a2-form-group">
        <label class="a2-label">
            {{ $endLabel }}
            @if($required)
                <span class="a2-danger">*</span>
            @endif
        </label>

        <input
            class="a2-input js-a2-date-range-end"
            type="date"
            name="{{ $endName }}"
            value="{{ old($endName, $endValue) }}"
            @if($required) required @endif
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
        >

        @error($endName)
            <div class="a2-error">{{ $message }}</div>
        @enderror
    </div>
</div>

@if($hint)
    <div class="a2-section-subtitle" style="margin-top:8px;margin-bottom:0;">
        {{ $hint }}
    </div>
@endif