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
    // A trade with no `line` group of its own sells its modifiers — a timber
    // yard's product IS «زان». The help text below must not then call it
    // «غرفة نوم»، «شقة»: the example would name nothing he could pick.
    $promoted = $vocabulary['promoted'] ?? null;
    $selectedLine = (int) old('line_option_id', $lineId ?? 0);
    $selectedModifiers = collect(old('modifier_option_ids', ($modifierIds ?? collect())->all() ?? []))
        ->map(fn ($id) => (int) $id);

    /**
     * وسعرُ كل مُوصِّف. صفرٌ يعني «يوصِّف ولا يُسعِّر» — وهي الحال التي كانت
     * وحدها ممكنة قبل 2026-08-19.
     */
    $adjustValues = collect(old('modifier_adjust', $modifierAdjust ?? []));
    $adjustTypes = collect(old('modifier_adjust_type', $modifierAdjustType ?? []));

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
                    <small class="a2-help">
                        @if($promoted)
                            {{ __('الشيء الذي يشتريه العميل، من واقع خيارات نشاطك — «زان»، «MDF»، «بديل رخام».') }}
                        @else
                            {{ __('الشيء الذي يشتريه العميل — «غرفة نوم»، «شقة»، «كشف عظام».') }}
                        @endif
                    </small>
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
                                        <input type="number" step="0.01" class="bv-adjust"
                                               name="modifier_adjust[{{ $option->id }}]"
                                               value="{{ $adjustValues[$option->id] ?? '' }}"
                                               placeholder="0"
                                               title="{{ __('يُضاف إلى سعر الوحدة — اتركه فارغًا إن كان لا يغيّر السعر') }}">
                                        <select class="bv-adjust-type" name="modifier_adjust_type[{{ $option->id }}]">
                                            <option value="amount" @selected(($adjustTypes[$option->id] ?? 'amount') === 'amount')>{{ __('ج') }}</option>
                                            <option value="percent" @selected(($adjustTypes[$option->id] ?? '') === 'percent')>%</option>
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <small class="a2-help">
                        {{ __('لا يُباع وحده لكنه يغيّر السعر — «مودرن»، «إيجار»، «سوبر لوكس».') }}
                        {{ __('اكتب بجواره كم يزيد على سعر الوحدة الواحدة: «شاشة كبيرة +٢٠». اتركه فارغًا إن كان يصف فقط.') }}
                    </small>
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
        .bv-chip:has(input[type=checkbox]:checked) { border-color: currentColor; font-weight: 600; }
        /* خانةُ السعر لا تظهر إلا على مُوصِّفٍ مختار: صندوقٌ فارغ بجوار كل كلمة
           يحوّل قائمةً تُقرأ بلمحة إلى استمارة. */
        .bv-chip .bv-adjust, .bv-chip .bv-adjust-type { display: none; }
        .bv-chip:has(input[type=checkbox]:checked) .bv-adjust,
        .bv-chip:has(input[type=checkbox]:checked) .bv-adjust-type { display: inline-block; }
        .bv-adjust { width: 66px; padding: 1px 4px; font-size: 12px; border-radius: 6px;
                     border: 1px solid rgba(128,128,128,.35); }
        .bv-adjust-type { padding: 1px 2px; font-size: 12px; border-radius: 6px;
                          border: 1px solid rgba(128,128,128,.35); }
    </style>
    @endpush
@endif
