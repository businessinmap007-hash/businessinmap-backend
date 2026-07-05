@php
    $isEdit = isset($row) && $row?->exists;
    $currentService = (int) old('service_id', $row->service_id ?? 0);
    $currentType = (string) old('item_type', $row->item_type ?? '');
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
        لا توجد خدمات متاحة لنشاطك بعد. تواصل مع إدارة التطبيق لتفعيل الخدمات المناسبة لقسمك.
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">بيانات الوحدة</div>
                <div class="a2-card-sub">اختر الخدمة والنوع، ثم عرّف الوحدة الفعلية (السعر يُضبط من شاشة الأسعار).</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="service_id">الخدمة <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-service" id="service_id" name="service_id" required>
                    <option value="">اختر الخدمة</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($currentService === (int) $service->id)>
                            {{ $service->name_ar ?: ($service->name_en ?: $service->key) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="item_type">نوع العنصر <span class="a2-danger">*</span></label>
                <select class="a2-select js-bi-type" id="item_type" name="item_type" required data-current-value="{{ $currentType }}">
                    <option value="">اختر الخدمة أولًا</option>
                </select>
                <div class="a2-hint a2-mt-8">تظهر فقط الأنواع المسموحة لنشاطك.</div>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="code">الكود / رقم الوحدة <span class="a2-danger">*</span></label>
                <input class="a2-input" id="code" name="code" value="{{ old('code', $row->code ?? '') }}" placeholder="101 / A1 / Table-5" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="title">اسم وصفي (اختياري)</label>
                <input class="a2-input" id="title" name="title" value="{{ old('title', $row->title ?? '') }}" placeholder="غرفة بإطلالة بحر">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="capacity">السعة / عدد الأفراد</label>
                <input class="a2-input" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $row->capacity ?? '') }}" placeholder="2">
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="quantity">الكمية</label>
                <input class="a2-input" id="quantity" name="quantity" type="number" min="1" value="{{ old('quantity', $row->quantity ?? 1) }}">
            </div>

            <div class="a2-form-group">
                <label class="a2-label">الحالة</label>
                <label class="a2-check" style="margin-top:10px;">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))>
                    <span>مفعّلة للحجز</span>
                </label>
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'حفظ' }}</button>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typesByService = @json($allowedTypesByService ?? []);
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
                o.value = ''; o.textContent = 'اختر الخدمة أولًا';
                typeSelect.appendChild(o);
                return;
            }

            if (!list.length) {
                const o = document.createElement('option');
                o.value = ''; o.textContent = 'لا توجد أنواع مسموحة لهذه الخدمة';
                typeSelect.appendChild(o);
                return;
            }

            const empty = document.createElement('option');
            empty.value = ''; empty.textContent = 'اختر النوع';
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
