@php
    $isEdit = isset($row) && $row?->exists;
    $currentService = (int) old('service_id', $row->service_id ?? 0);
    $currentType = (string) old('item_type', $row->item_type ?? '');
    $currentLine = (int) old('line_option_id', $row->line_option_id ?? 0);
    $lineGroups = collect($lineOptions ?? []);
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if(($services ?? collect())->isEmpty())
    <div class="a2-alert a2-alert-warning">
        {{ __('لا توجد خدمات متاحة لنشاطك بعد. تواصل مع إدارة التطبيق لتفعيل الخدمات المناسبة لقسمك.') }}
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('بيانات الوحدة') }}</div>
                <div class="a2-card-sub">{{ __('اختر الخدمة والنوع، ثم عرّف الوحدة الفعلية (السعر يُضبط من شاشة الأسعار).') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="service_id">{{ __('الخدمة') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-service" id="service_id" name="service_id" required>
                    <option value="">{{ __('اختر الخدمة') }}</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($currentService === (int) $service->id)>
                            {{ $service->name_ar ?: ($service->name_en ?: $service->key) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="item_type">{{ __('نوع العنصر') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-type" id="item_type" name="item_type" required data-current-value="{{ $currentType }}">
                    <option value="">{{ __('اختر الخدمة أولًا') }}</option>
                </select>
                <div class="a2-hint a2-mt-8">{{ __('تظهر فقط الأنواع المسموحة لنشاطك.') }}</div>
            </div>

            @if($lineGroups->isNotEmpty())
                {{-- The kind, which the item type stopped carrying. «حجز إقامة» is
                     every room in the hotel; only this says WHICH, and so only this
                     lets room 101 and جناح س301 hold different prices. --}}
                <div class="a2-form-group">
                    <label class="a2-label" for="line_option_id">{{ __('نوع الوحدة') }}</label>
                    <select class="a2-select" id="line_option_id" name="line_option_id">
                        <option value="">{{ __('بدون تحديد — يأخذ السعر العام للنوع') }}</option>
                        @foreach($lineGroups as $groupName => $options)
                            <optgroup label="{{ $groupName }}">
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected($currentLine === (int) $option->id)>
                                        {{ $option->name_ar ?: $option->name_en }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="a2-hint a2-mt-8">{{ __('حدّده ليأخذ سعر هذا النوع من شاشة الأسعار بدل السعر العام.') }}</div>
                </div>
            @endif

            <div class="a2-form-group">
                <label class="a2-label" for="code">{{ __('الكود / رقم الوحدة') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="code" name="code" value="{{ old('code', $row->code ?? '') }}" placeholder="101 / A1 / Table-5" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="title">{{ __('اسم وصفي (اختياري)') }}</label>
                <input class="a2-input" id="title" name="title" value="{{ old('title', $row->title ?? '') }}" placeholder="{{ __('غرفة بإطلالة بحر') }}">
            </div>

            {{-- جمهوران، فحقلان. ما يقرؤه النزيل لا يُخلط بما تكتبه لموظّفك. --}}
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="description">{{ __('الوصف (يظهر للعميل)') }}</label>
                <textarea class="a2-input" id="description" name="description" rows="3"
                          placeholder="{{ __('إطلالة على النيل، الدور السادس، بلكونة.') }}">{{ old('description', $row->description ?? '') }}</textarea>
                <div class="a2-hint a2-mt-8">{{ __('يُعرض فى قائمة الوحدات داخل التطبيق بجانب السعر.') }}</div>
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="notes">{{ __('ملاحظة داخلية (لك ولموظفيك)') }}</label>
                <textarea class="a2-input" id="notes" name="notes" rows="2"
                          placeholder="{{ __('التكييف يحتاج صيانة.') }}">{{ old('notes', $row->notes ?? '') }}</textarea>
                <div class="a2-hint a2-mt-8">{{ __('لا تظهر للعميل أبدًا.') }}</div>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="capacity">{{ __('السعة / عدد الأفراد') }}</label>
                <input class="a2-input" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $row->capacity ?? '') }}" placeholder="2">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="quantity">{{ __('الكمية') }}</label>
                <input class="a2-input" id="quantity" name="quantity" type="number" min="1" value="{{ old('quantity', $row->quantity ?? 1) }}">
            </div>

            <div class="a2-form-group">
                <label class="a2-label">{{ __('الحالة') }}</label>
                <label class="a2-check" style="margin-top:10px;">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))>
                    <span>{{ __('مفعّلة للحجز') }}</span>
                </label>
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? __('تحديث') : __('حفظ') }}</button>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typesByService = @json($allowedTypesByService ?? []);
        const i18n = {
            pickServiceFirst: @json(__('اختر الخدمة أولًا')),
            noTypes: @json(__('لا توجد أنواع مسموحة لهذه الخدمة')),
            pickType: @json(__('اختر النوع')),
        };
        const serviceSelect = document.querySelector('.js-bi-service');
        const typeSelect = document.querySelector('.js-bi-type');
        if (!serviceSelect || !typeSelect) return;

        function rebuildTypes() {
            const serviceId = String(serviceSelect.value || '');
            const keep = String(typeSelect.dataset.currentValue || typeSelect.value || '');
            const list = (typesByService[serviceId] || []);

            typeSelect.innerHTML = '';

            if (!serviceId) {
                const o = document.createElement('option');
                o.value = ''; o.textContent = i18n.pickServiceFirst;
                typeSelect.appendChild(o);
                return;
            }

            if (!list.length) {
                const o = document.createElement('option');
                o.value = ''; o.textContent = i18n.noTypes;
                typeSelect.appendChild(o);
                return;
            }

            const empty = document.createElement('option');
            empty.value = ''; empty.textContent = i18n.pickType;
            typeSelect.appendChild(empty);

            list.forEach(function (t) {
                const o = document.createElement('option');
                o.value = String(t.key);
                o.textContent = String(t.label || t.key);
                if (String(t.key) === keep) o.selected = true;
                typeSelect.appendChild(o);
            });
        }

        serviceSelect.addEventListener('change', function () {
            typeSelect.dataset.currentValue = '';
            rebuildTypes();
        });

        rebuildTypes();
    });
    </script>
    @endpush
@endif
