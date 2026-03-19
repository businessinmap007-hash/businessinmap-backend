@props([
    'name' => '',
    'label' => '',
    'value' => '',
    'required' => false,
    'min' => null,
    'max' => null,
    'hint' => null,
])

<div class="a2-form-group">
    @if($label !== '')
        <label class="a2-label">
            {{ $label }}
            @if($required)
                <span class="a2-danger">*</span>
            @endif
        </label>
    @endif

    <input
        class="a2-input"
        type="date"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        @if($min) min="{{ $min }}" @endif
        @if($max) max="{{ $max }}" @endif
    >

    @if($hint)
        <div class="a2-section-subtitle" style="margin-top:8px;margin-bottom:0;">
            {{ $hint }}
        </div>
    @endif

    @error($name)
        <div class="a2-error">{{ $message }}</div>
    @enderror
</div>