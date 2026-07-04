@php
    $isEdit = isset($row) && $row->exists;
    $defaultBusinessId = old('business_id', $row->business_id ?? '');
    $defaultServiceId = old('service_id', $row->service_id ?? '');
    $defaultType = old('item_type', $row->item_type ?? '');
    $typeOptions = $allowedItemTypes ?? [];
    $itemTypeLabels = $itemTypeLabels ?? [];
    $itemTypesByBusinessService = is_array($itemTypesByBusinessService ?? null) ? $itemTypesByBusinessService : [];
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">تنظيم عناصر الحجز الفعلية</div>
    <div class="a2-section-subtitle">
        Bookable Item هو العنصر الحقيقي الذي يختاره العميل في الحجز. نوع العنصر هنا لا يأتي من كتابة حرة، بل من تقاطع:
        <span dir="ltr">Platform Service Item Types</span> + <span dir="ltr">Service Catalog Matrix</span> + القسم الفرعي للبزنس.
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">البزنس والخدمة</div>
            <div class="a2-card-sub">بعد اختيار البزنس والخدمة سيتم تضييق نوع العنصر حسب إعدادات الأدمن للقسم الفرعي.</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">البزنس</label>
            <select name="business_id" class="a2-select js-bookable-business js-bookable-search-select" required data-placeholder="اكتب اسم البزنس">
                <option value="">اختر البزنس</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @selected((string) $defaultBusinessId === (string) $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الخدمة</label>
            <select name="service_id" class="a2-select js-bookable-service js-bookable-search-select" required data-placeholder="اكتب اسم الخدمة">
                <option value="">اختر الخدمة</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((string) $defaultServiceId === (string) $s->id)>{{ $s->name_ar ?? $s->name_en ?? $s->key }}</option>
                @endforeach
            </select>
        </div>

        @if($isEdit)
            <div class="a2-form-group">
                <label class="a2-label">نوع العنصر</label>
                <select name="item_type" class="a2-select js-bookable-type js-bookable-search-select" required data-current-value="{{ $defaultType }}" data-placeholder="اختر نوع العنصر">
                    <option value="">اختر النوع</option>
                    @foreach($typeOptions as $type)
                        <option value="{{ $type }}" @selected((string) $defaultType === (string) $type)>{{ $itemTypeLabels[$type] ?? $type }}</option>
                    @endforeach
                </select>
                <div class="a2-hint a2-mt-8 js-bookable-type-hint">يتم تحديث القائمة حسب البزنس والخدمة.</div>
            </div>
        @endif
    </div>
</div>

@if($isEdit)
    <div class="a2-form-grid">
        <div class="a2-card">
            <div class="a2-card-head"><h3>بيانات العنصر</h3></div>
            <div class="a2-card-body">
                <div class="a2-form-group">
                    <label>الكود / رقم الغرفة</label>
                    <input type="text" name="code" class="a2-input" value="{{ old('code', $row->code ?? '') }}" required placeholder="مثال: 101 / A1">
                </div>
                <div class="a2-form-group">
                    <label>السعة / عدد الغرف</label>
                    <input type="number" name="capacity" class="a2-input" value="{{ old('capacity', $row->capacity ?? '') }}" min="1">
                </div>
                <div class="a2-form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" class="a2-input" value="{{ old('quantity', $row->quantity ?? 1) }}" min="1">
                </div>
            </div>
        </div>
        <div class="a2-card">
            <div class="a2-card-head"><h3>السعر والحالة</h3></div>
            <div class="a2-card-body">
                <div class="a2-form-group">
                    <label>السعر</label>
                    <input type="number" step="0.01" name="price" class="a2-input" value="{{ old('price', $row->price ?? 0) }}" required>
                </div>
                <label class="a2-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? 1))> <span>مفعل</span></label>
            </div>
        </div>
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">عناصر الحجز المتاحة للعميل</div>
                <div class="a2-card-sub">أضف كل عنصر فعلي كسطر مستقل: غرفة 101، غرفة 102، شقة A1، طاولة 5.</div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table" id="bookableItemsTable">
                <thead>
                    <tr>
                        <th>نوع العنصر</th>
                        <th>الكود / رقم الغرفة</th>
                        <th>السعة / عدد الغرف</th>
                        <th>الكمية</th>
                        <th>السعر</th>
                        <th>مفعل</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < 8; $i++)
                        <tr>
                            <td>
                                <select name="items[{{ $i }}][item_type]" class="a2-select js-bookable-type js-bookable-row-type js-bookable-search-select" data-placeholder="اختر النوع">
                                    <option value="">اختر البزنس والخدمة أولًا</option>
                                </select>
                            </td>
                            <td><input name="items[{{ $i }}][code]" class="a2-input" placeholder="101 / A1 / Table-5"></td>
                            <td><input name="items[{{ $i }}][capacity]" class="a2-input" type="number" min="1" placeholder="مثال: 2"></td>
                            <td><input name="items[{{ $i }}][quantity]" class="a2-input" type="number" min="1" value="1"></td>
                            <td><input name="items[{{ $i }}][price]" class="a2-input" type="number" step="0.01" min="0" placeholder="0.00"></td>
                            <td><input type="checkbox" name="items[{{ $i }}][is_active]" value="1" checked></td>
                            <td><button type="button" class="a2-btn a2-btn-ghost js-clear-row">مسح</button></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <div class="a2-alert a2-alert-info a2-mt-16 js-bookable-type-hint">
            اختر البزنس والخدمة أولًا. ستظهر فقط أنواع العناصر المسموحة من Service Catalog Matrix لهذا category_child.
        </div>
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الديبوزت والخصم على المجموعة</div>
            <div class="a2-card-sub">الديبوزت هنا ضمان/حجز فقط. إلغاء الديبوزت لا يلغي رسوم استخدام الخدمة لصالح التطبيق، ورسوم الخدمة مكانها Service Fees.</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <label class="a2-check-card">
            <input type="checkbox" name="deposit_enabled" value="1" id="deposit_enabled" @checked(old('deposit_enabled', $row->deposit_enabled ?? 0))>
            <span>تفعيل الديبوزت</span>
        </label>

        <div class="a2-form-group">
            <label class="a2-label">نسبة الديبوزت %</label>
            <input type="number" name="deposit_percent" id="deposit_percent" class="a2-input" value="{{ old('deposit_percent', $row->deposit_percent ?? 0) }}" min="0" max="100">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">خصم الخدمة</label>
            <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn a2-btn-ghost">إدارة أسعار وخصومات الخدمة</a>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head"><div class="a2-card-title">Meta JSON</div></div>
    <textarea name="meta" class="a2-textarea" rows="4" placeholder='{"floor":"1", "view":"sea"}'>{{ old('meta', isset($row->meta) ? json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
</div>

<div class="a2-actions">
    <button class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'إنشاء العناصر' }}</button>
    <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn">رجوع</a>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.js-clear-row');
    if (!btn) return;
    const row = btn.closest('tr');
    row.querySelectorAll('input, select').forEach(function (input) {
        if (input.type === 'checkbox') input.checked = false;
        else input.value = '';
        if (input.tomselect) input.tomselect.clear(true);
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const typeMatrix = @json($itemTypesByBusinessService);
    const businessSelect = document.querySelector('.js-bookable-business');
    const serviceSelect = document.querySelector('.js-bookable-service');
    const hintNodes = document.querySelectorAll('.js-bookable-type-hint');

    function initTom(select) {
        if (window.TomSelect && !select.tomselect) {
            new TomSelect(select, {
                create: false,
                allowEmptyOption: true,
                maxOptions: 500,
                placeholder: select.dataset.placeholder || 'ابحث هنا',
                sortField: {field: 'text', direction: 'asc'},
                dropdownParent: 'body'
            });
        }
    }

    function setHint(message) {
        hintNodes.forEach(function (node) {
            node.textContent = message;
        });
    }

    function fillSelect(select, options, keepValue) {
        const ts = select.tomselect || null;
        if (ts) {
            ts.clear(true);
            ts.clearOptions();
        }

        select.innerHTML = '';

        if (!options.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = businessSelect?.value && serviceSelect?.value
                ? 'لا توجد أنواع مسموحة لهذا الاختيار'
                : 'اختر البزنس والخدمة أولًا';
            select.appendChild(option);
            if (ts) ts.addOption({value: '', text: option.textContent});
            return;
        }

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'اختر النوع';
        select.appendChild(empty);
        if (ts) ts.addOption({value: '', text: empty.textContent});

        options.forEach(function (item) {
            const value = String(item.key || '');
            const label = String(item.label || value);
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            option.selected = keepValue && value === keepValue;
            select.appendChild(option);
            if (ts) ts.addOption({value: value, text: label});
        });

        if (ts) {
            ts.refreshOptions(false);
            if (keepValue) ts.setValue(keepValue, true);
        }
    }

    function refreshTypeOptions() {
        const businessId = String(businessSelect?.value || '');
        const serviceId = String(serviceSelect?.value || '');
        const options = (typeMatrix[businessId] && typeMatrix[businessId][serviceId]) ? typeMatrix[businessId][serviceId] : [];

        document.querySelectorAll('.js-bookable-type').forEach(function (select) {
            const keepValue = String(select.dataset.currentValue || select.value || '');
            fillSelect(select, options, keepValue);
        });

        if (!businessId || !serviceId) {
            setHint('اختر البزنس والخدمة أولًا.');
        } else if (!options.length) {
            setHint('لا توجد أنواع عناصر مسموحة لهذا البزنس مع هذه الخدمة. راجع Platform Service Item Types و Service Catalog Matrix.');
        } else {
            setHint('تم عرض أنواع العناصر المسموحة فقط لهذا category_child والخدمة.');
        }
    }

    document.querySelectorAll('.js-bookable-search-select').forEach(initTom);

    businessSelect?.addEventListener('change', refreshTypeOptions);
    serviceSelect?.addEventListener('change', refreshTypeOptions);

    const depositCheckbox = document.getElementById('deposit_enabled');
    const depositInput = document.getElementById('deposit_percent');
    function toggleDeposit() {
        if (!depositCheckbox || !depositInput) return;
        depositInput.disabled = !depositCheckbox.checked;
        if (!depositCheckbox.checked) depositInput.value = 0;
    }
    depositCheckbox?.addEventListener('change', toggleDeposit);

    refreshTypeOptions();
    toggleDeposit();
});
</script>
@endpush
