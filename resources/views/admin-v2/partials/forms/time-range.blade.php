@props([
    'startName' => 'start_time',
    'endName' => 'end_time',
    'startLabel' => 'Start Time',
    'endLabel' => 'End Time',
    'startValue' => '',
    'endValue' => '',
    'required' => false,
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
            type="time"
            name="{{ $startName }}"
            value="{{ old($startName, $startValue) }}"
            class="a2-input a2-time-input js-a2-time-start"
            dir="ltr"
            step="60"
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
            type="time"
            name="{{ $endName }}"
            value="{{ old($endName, $endValue) }}"
            class="a2-input a2-time-input js-a2-time-end"
            dir="ltr"
            step="60"
            @if($required) required @endif
        >

        @error($endName)
            <div class="a2-error">{{ $message }}</div>
        @enderror

    </div>

</div>