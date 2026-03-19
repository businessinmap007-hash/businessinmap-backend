@props([
    'startName' => 'starts_at',
    'endName' => 'ends_at',
    'startLabel' => 'Starts At',
    'endLabel' => 'Ends At',
    'startValue' => '',
    'endValue' => '',
    'required' => false,
    'hint' => null,
])

<div class="a2-form-grid a2-datetime-range-grid">
    <div class="a2-form-group">
        <label class="a2-label">
            {{ $startLabel }}
            @if($required)
                <span class="a2-danger">*</span>
            @endif
        </label>

        <input
            class="a2-input a2-datetime-input a2-datetime-input--sm js-a2-datetime-range-start"
            type="datetime-local"
            name="{{ $startName }}"
            value="{{ old($startName, $startValue) }}"
            dir="ltr"
            @if($required) required @endif
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
            class="a2-input a2-datetime-input a2-datetime-input--sm js-a2-datetime-range-end"
            type="datetime-local"
            name="{{ $endName }}"
            value="{{ old($endName, $endValue) }}"
            dir="ltr"
            @if($required) required @endif
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