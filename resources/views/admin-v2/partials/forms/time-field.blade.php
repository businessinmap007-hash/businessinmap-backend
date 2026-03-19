@props([
    'name' => '',
    'label' => '',
    'value' => '',
    'required' => false,
    'min' => null,
    'max' => null,
    'step' => '60',
])

<div class="a2-form-group">

    @if($label)
        <label class="a2-label">
            {{ $label }}
            @if($required)
                <span class="a2-danger">*</span>
            @endif
        </label>
    @endif

    <input
        type="time"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        class="a2-input a2-time-input"
        dir="ltr"
        step="{{ $step }}"
        @if($required) required @endif
        @if($min) min="{{ $min }}" @endif
        @if($max) max="{{ $max }}" @endif
    >

    @error($name)
        <div class="a2-error">{{ $message }}</div>
    @enderror

</div>