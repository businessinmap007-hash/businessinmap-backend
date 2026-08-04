@php
    /**
     * What this offering IS, in the platform's own words: one line option and
     * any number of modifiers. The lists are already narrowed to what THIS
     * merchant declared about itself, so a hospital sees its own specialties
     * rather than all forty-one.
     */
    $lines = collect($vocabulary['lines'] ?? []);
    $modifiers = collect($vocabulary['modifiers'] ?? []);
    $narrowed = (bool) ($vocabulary['narrowed'] ?? false);
    $selectedLine = (int) old('line_option_id', $lineId ?? 0);
    $selectedModifiers = collect(old('modifier_option_ids', ($modifierIds ?? collect())->all() ?? []))
        ->map(fn ($id) => (int) $id);

    // the panel is bilingual, and these rows carry both names
    $say = function ($ar, $en) {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : ($ar ?: $en);
    };
@endphp

@if($lines->isNotEmpty() || $modifiers->isNotEmpty())
    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('ما الذي تبيعه هنا؟') }}</div>
                <div class="a2-card-sub">
                    @if($narrowed)
                        {{ __('القوائم من واقع ما حددته في خيارات نشاطك — لن تحتاج لكتابته من جديد.') }}
                    @else
                        {{ __('حدّد خيارات نشاطك من صفحة الخيارات لتقصر هذه القوائم على ما تعمل به فعلًا.') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="a2-form-grid">
            @if($lines->isNotEmpty())
                <div class="a2-form-group">
                    <label class="a2-label" for="line_option_id">{{ __('النوع') }}</label>
                    <select class="a2-input" id="line_option_id" name="line_option_id">
                        <option value="">{{ __('— بدون تحديد —') }}</option>
                        @foreach($lines as $groupName => $options)
                            <optgroup label="{{ $groupName }}">
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected($selectedLine === (int) $option->id)>{{ $say($option->name_ar, $option->name_en) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <small class="a2-help">{{ __('الشيء الذي يشتريه العميل — «غرفة نوم»، «شقة»، «كشف عظام».') }}</small>
                </div>
            @endif

            @if($modifiers->isNotEmpty())
                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">{{ __('ما يميّزه') }}</label>

                    @foreach($modifiers as $groupName => $options)
                        <div class="bv-group">
                            <div class="bv-group-head">{{ $groupName }}</div>
                            <div class="bv-chips">
                                @foreach($options as $option)
                                    <label class="bv-chip">
                                        <input type="checkbox" name="modifier_option_ids[]" value="{{ $option->id }}"
                                               @checked($selectedModifiers->contains((int) $option->id))>
                                        <span>{{ $say($option->name_ar, $option->name_en) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <small class="a2-help">{{ __('لا يُباع وحده لكنه يغيّر السعر — «مودرن»، «إيجار»، «سوبر لوكس».') }}</small>
                </div>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .bv-group { margin-bottom: 10px; }
        .bv-group-head { font-size: 12px; opacity: .7; margin-bottom: 4px; }
        .bv-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .bv-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
                   border: 1px solid rgba(128,128,128,.35); border-radius: 999px;
                   font-size: 13px; cursor: pointer; }
        .bv-chip:has(input:checked) { border-color: currentColor; font-weight: 600; }
    </style>
    @endpush
@endif
