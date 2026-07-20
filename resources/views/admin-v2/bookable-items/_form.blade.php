@php
    $isEdit = isset($row) && $row->exists;
    $defaultServiceId = old('service_id', $row->service_id ?? '');
    $defaultType = old('item_type', $row->item_type ?? '');
    $typeOptions = $allowedItemTypes ?? [];
    $itemTypeLabels = $itemTypeLabels ?? [];
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">{{ __('تنظيم عناصر الحجز الفعلية') }}</div>
    <div class="a2-section-subtitle">
        {{ __('Bookable Item هو العنصر الحقيقي الذي يختاره العميل في الحجز. نوع العنصر هنا لا يأتي من كتابة حرة، بل من تقاطع:') }}
        <span dir="ltr">Platform Service Item Types</span> + <span dir="ltr">Service Catalog Matrix</span> {{ __('+ القسم الفرعي للبزنس.') }}
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('البزنس والخدمة') }}</div>
            <div class="a2-card-sub">{{ __('بعد اختيار البزنس والخدمة سيتم تضييق نوع العنصر حسب إعدادات الأدمن للقسم الفرعي.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label" for="business_id">{{ __('البزنس') }}</label>
            <select id="business_id" name="business_id" class="a2-select js-bookable-business js-bookable-search-select" required data-placeholder="{{ __('اكتب اسم البزنس') }}" data-remote-url="{{ route('admin.bookable-items.business-lookup', [], false) }}">
                <option value="">{{ __('اختر البزنس') }}</option>
                @if($selectedBusiness ?? null)
                    <option value="{{ $selectedBusiness->id }}" selected>{{ $selectedBusiness->name }}</option>
                @endif
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="service_id">{{ __('الخدمة') }}</label>
            <select id="service_id" name="service_id" class="a2-select js-bookable-service js-bookable-search-select" required data-placeholder="{{ __('اكتب اسم الخدمة') }}">
                <option value="">{{ __('اختر الخدمة') }}</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((string) $defaultServiceId === (string) $s->id)>{{ $s->name_ar ?: ($s->name_en ?: $s->key) }}</option>
                @endforeach
            </select>
        </div>

        @if($isEdit)
            <div class="a2-form-group">
                <label class="a2-label" for="item_type">{{ __('نوع العنصر') }}</label>
                <select id="item_type" name="item_type" class="a2-select js-bookable-type js-bookable-search-select" required data-current-value="{{ $defaultType }}" data-placeholder="{{ __('اختر نوع العنصر') }}">
                    <option value="">{{ __('اختر النوع') }}</option>
                    @foreach($typeOptions as $type)
                        <option value="{{ $type }}" @selected((string) $defaultType === (string) $type)>{{ $itemTypeLabels[$type] ?? $type }}</option>
                    @endforeach
                </select>
                <div class="a2-hint a2-mt-8 js-bookable-type-hint">{{ __('يتم تحديث القائمة حسب البزنس والخدمة.') }}</div>
            </div>
        @endif
    </div>
</div>

@if($isEdit)
    <div class="a2-form-grid">
        <div class="a2-card">
            <div class="a2-card-head"><h3>{{ __('بيانات العنصر') }}</h3></div>
            <div class="a2-card-body">
                <div class="a2-form-group">
                    <label>{{ __('الكود / رقم الغرفة') }}</label>
                    <input type="text" name="code" class="a2-input" value="{{ old('code', $row->code ?? '') }}" required placeholder="{{ __('مثال: 101 / A1') }}">
                </div>
                <div class="a2-form-group">
                    <label>{{ __('السعة / عدد الغرف') }}</label>
                    <input type="number" name="capacity" class="a2-input" value="{{ old('capacity', $row->capacity ?? '') }}" min="1">
                </div>
                <div class="a2-form-group">
                    <label>{{ __('الكمية') }}</label>
                    <input type="number" name="quantity" class="a2-input" value="{{ old('quantity', $row->quantity ?? 1) }}" min="1">
                </div>
            </div>
        </div>
        <div class="a2-card">
            <div class="a2-card-head"><h3>{{ __('الحالة') }}</h3></div>
            <div class="a2-card-body">
                <label class="a2-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? 1))> <span>{{ __('مفعل') }}</span></label>
                <div class="a2-hint a2-mt-8">{{ __('السعر والديبوزت يُضبطان لكل نوع من شاشة أسعار خدمات البزنس، وليس على الوحدة.') }}</div>
            </div>
        </div>
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('عناصر الحجز المتاحة للعميل') }}</div>
                <div class="a2-card-sub">{{ __('أضف كل عنصر فعلي كسطر مستقل: غرفة 101، غرفة 102، شقة A1، طاولة 5.') }}</div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table" id="bookableItemsTable">
                <thead>
                    <tr>
                        <th>{{ __('نوع العنصر') }}</th>
                        <th>{{ __('الكود / رقم الغرفة') }}</th>
                        <th>{{ __('السعة / عدد الغرف') }}</th>
                        <th>{{ __('الكمية') }}</th>
                        <th>{{ __('مفعل') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < 8; $i++)
                        <tr>
                            <td>
                                <select id="item-type-{{ $i }}" name="items[{{ $i }}][item_type]" class="a2-select js-bookable-type js-bookable-row-type js-bookable-search-select" data-placeholder="{{ __('اختر النوع') }}">
                                    <option value="">{{ __('اختر البزنس والخدمة أولًا') }}</option>
                                </select>
                            </td>
                            <td><input name="items[{{ $i }}][code]" class="a2-input" placeholder="101 / A1 / Table-5"></td>
                            <td><input name="items[{{ $i }}][capacity]" class="a2-input" type="number" min="1" placeholder="{{ __('مثال: 2') }}"></td>
                            <td><input name="items[{{ $i }}][quantity]" class="a2-input" type="number" min="1" value="1"></td>
                            <td><input type="checkbox" name="items[{{ $i }}][is_active]" value="1" checked></td>
                            <td><button type="button" class="a2-btn a2-btn-ghost js-clear-row">{{ __('مسح') }}</button></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <div class="a2-alert a2-alert-info a2-mt-16 js-bookable-type-hint">
            {{ __('اختر البزنس والخدمة أولًا. ستظهر فقط أنواع العناصر المسموحة من Service Catalog Matrix لهذا category_child.') }}
        </div>
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('السعر والديبوزت والخصم') }}</div>
            <div class="a2-card-sub">{{ __('لا تُضبط على الوحدة. السعر والديبوزت والخصم يُحدَّدون لكل نوع عنصر من شاشة أسعار خدمات البزنس.') }}</div>
        </div>
    </div>

    <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn a2-btn-ghost">{{ __('إدارة أسعار وخصومات الخدمة') }}</a>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head"><div class="a2-card-title">Meta JSON</div></div>
    <textarea name="meta" class="a2-textarea" rows="4" placeholder='{"floor":"1", "view":"sea"}'>{{ old('meta', isset($row->meta) ? json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
</div>

<div class="a2-actions">
    <button class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'إنشاء العناصر' }}</button>
    <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn">{{ __('رجوع') }}</a>
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
    const lookupUrl = @json(route('admin.bookable-items.item-types-lookup', [], false));
    const businessSelect = document.querySelector('.js-bookable-business');
    const serviceSelect = document.querySelector('.js-bookable-service');
    const hintNodes = document.querySelectorAll('.js-bookable-type-hint');
    let requestSeq = 0;

    function initTom(select) {
        if (!window.TomSelect || select.tomselect) return;

        const remoteUrl = select.dataset.remoteUrl;

        if (remoteUrl) {
            // Business select: search-as-you-type instead of embedding every
            // business (1,700+) as static <option> tags on every page load.
            new TomSelect(select, {
                create: false,
                maxOptions: 30,
                placeholder: select.dataset.placeholder || 'ابحث هنا',
                dropdownParent: 'body',
                shouldLoad: function (query) { return query.length >= 1; },
                load: function (query, callback) {
                    const url = new URL(remoteUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    fetch(url.toString(), {headers: {'Accept': 'application/json'}})
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            const rows = (data && data.ok && Array.isArray(data.businesses)) ? data.businesses : [];
                            callback(rows.map(function (b) { return {value: String(b.id), text: b.name}; }));
                        })
                        .catch(function () { callback(); });
                },
            });
            return;
        }

        new TomSelect(select, {
            create: false,
            allowEmptyOption: true,
            maxOptions: 500,
            placeholder: select.dataset.placeholder || 'ابحث هنا',
            sortField: {field: 'text', direction: 'asc'},
            dropdownParent: 'body'
        });
    }

    function setHint(message) {
        hintNodes.forEach(function (node) {
            node.textContent = message;
        });
    }

    function fillSelect(select, options, keepValue) {
        // Rebuild from scratch instead of patching the live TomSelect instance -
        // incremental clearOptions()/addOption() calls left stale rendered rows
        // behind after repeated business/service changes.
        if (select.tomselect) {
            select.tomselect.destroy();
        }

        select.innerHTML = '';

        if (!options.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = businessSelect?.value && serviceSelect?.value
                ? 'لا توجد أنواع مسموحة لهذا الاختيار'
                : 'اختر البزنس والخدمة أولًا';
            select.appendChild(option);
            initTom(select);
            return;
        }

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'اختر النوع';
        select.appendChild(empty);

        options.forEach(function (item) {
            const value = String(item.key || '');
            const label = String(item.label || value);
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            option.selected = keepValue && value === keepValue;
            select.appendChild(option);
        });

        initTom(select);

        if (keepValue && select.tomselect) {
            select.tomselect.setValue(keepValue, true);
        }
    }

    function applyOptions(options) {
        document.querySelectorAll('.js-bookable-type').forEach(function (select) {
            const keepValue = String(select.dataset.currentValue || select.value || '');
            try {
                fillSelect(select, options, keepValue);
            } catch (e) {
                console.error('fillSelect failed for', select, e);
            }
        });
    }

    function refreshTypeOptions() {
        const businessId = String(businessSelect?.value || '');
        const serviceId = String(serviceSelect?.value || '');

        if (!businessId || !serviceId) {
            applyOptions([]);
            setHint('اختر البزنس والخدمة أولًا.');
            return;
        }

        // Fetched on demand instead of a giant precomputed business x service
        // matrix - that used to be embedded for every business (1700+) times
        // every service on every page load, which made this page extremely
        // slow. Sequence guard drops stale responses if the user changes the
        // business/service again before the previous lookup finishes.
        const seq = ++requestSeq;
        const url = new URL(lookupUrl, window.location.origin);
        url.searchParams.set('business_id', businessId);
        url.searchParams.set('service_id', serviceId);

        setHint('جاري تحميل أنواع العناصر...');

        fetch(url.toString(), {headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (seq !== requestSeq) return;
                const options = (data && data.ok && Array.isArray(data.items)) ? data.items : [];
                applyOptions(options);
                setHint(options.length
                    ? 'تم عرض أنواع العناصر المسموحة فقط لهذا category_child والخدمة.'
                    : 'لا توجد أنواع عناصر مسموحة لهذا البزنس مع هذه الخدمة. راجع Platform Service Item Types و Service Catalog Matrix.');
            })
            .catch(function () {
                if (seq !== requestSeq) return;
                applyOptions([]);
                setHint('تعذر تحميل أنواع العناصر. حاول مرة أخرى.');
            });
    }

    document.querySelectorAll('.js-bookable-search-select').forEach(initTom);

    businessSelect?.addEventListener('change', refreshTypeOptions);
    serviceSelect?.addEventListener('change', refreshTypeOptions);

    refreshTypeOptions();
});
</script>
@endpush
