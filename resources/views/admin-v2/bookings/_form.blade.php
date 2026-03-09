@php
    /** @var \App\Models\Booking|null $booking */
    $booking = $booking ?? null;

    $statusOptions = $statusOptions ?? (\App\Models\Booking::statusOptions());

    $val = fn($key, $default = null) => old($key, data_get($booking, $key, $default));

    $selectedBookableId = (int) $val('bookable_id', 0);
    $selectedBookableType = (string) $val('bookable_type', '');
    $bookableSelectedLabel = '';

    if ($booking && $booking->relationLoaded('bookable') && $booking->bookable) {
        $bookableSelectedLabel =
            ($booking->bookable->title ?? 'Item')
            . (!empty($booking->bookable->code) ? ' — '.$booking->bookable->code : '');
    }

    if (!$bookableSelectedLabel && !empty(old('bookable_label'))) {
        $bookableSelectedLabel = old('bookable_label');
    }
@endphp

<div class="a2-card" style="padding:14px;">
    <div class="a2-header" style="margin-bottom:10px;">
        <div>
            <div class="a2-title" style="font-size:16px;">بيانات الحجز</div>
            <div class="a2-hint">الخدمة + البزنس + العنصر القابل للحجز + حساب الرسوم والديبوزت تلقائيًا</div>
        </div>
    </div>

    <div class="a2-alert a2-alert-info" style="margin-bottom:12px;">
        ملاحظة:
        يتم حساب السعر تلقائيًا من
        <b>العنصر القابل للحجز</b>
        إن وجد، وإلا من
        <b>Business Service Prices</b>.
        كما يتم تحديد الديبوزت حسب:
        <b>Platform Services</b>
        +
        <b>Business Service Prices</b>
        +
        <b>Bookable Item</b> إن وُجد Override.
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">

        {{-- Client --}}
        <div>
            <label class="a2-label">العميل</label>
            <div class="a2-searchable-wrap">
                <input type="text" class="a2-input a2-searchable-input" placeholder="ابحث عن العميل..." data-target="#user_id_select">
                <select class="a2-input js-a2-searchable" id="user_id_select" name="user_id" required>
                    <option value="">-- اختر العميل --</option>
                    @foreach($clients ?? [] as $client)
                        <option value="{{ $client->id }}" @selected((int)$val('user_id') === (int)$client->id)>
                            {{ $client->name }} — #{{ $client->id }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Business --}}
        <div>
            <label class="a2-label">البزنس</label>
            <div class="a2-searchable-wrap">
                <input type="text" class="a2-input a2-searchable-input" placeholder="ابحث عن البزنس..." data-target="#business_id_select">
                <select class="a2-input js-a2-searchable" id="business_id_select" name="business_id" required>
                    <option value="">-- اختر البزنس --</option>
                    @foreach($businesses ?? [] as $business)
                        <option value="{{ $business->id }}" @selected((int)$val('business_id') === (int)$business->id)>
                            {{ $business->name }} — #{{ $business->id }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Service --}}
        <div>
            <label class="a2-label">الخدمة</label>
            <div class="a2-searchable-wrap">
                <input type="text" class="a2-input a2-searchable-input" placeholder="ابحث عن الخدمة..." data-target="#service_id_select">
                <select class="a2-input js-a2-searchable" id="service_id_select" name="service_id" required>
                    <option value="">-- اختر الخدمة --</option>
                    @foreach($services ?? [] as $service)
                        <option value="{{ $service->id }}"
                                data-key="{{ $service->key }}"
                                data-supports-deposit="{{ (int)$service->supports_deposit }}"
                                data-max-deposit-percent="{{ (int)$service->max_deposit_percent }}"
                                @selected((int)$val('service_id') === (int)$service->id)>
                            {{ $service->name_ar ?? $service->name_en ?? $service->key }} ({{ $service->key }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Bookable Item --}}
        <div>
            <label class="a2-label">العنصر القابل للحجز</label>

            <input type="hidden" name="bookable_type" id="bookable_type" value="{{ $selectedBookableType ?: \App\Models\BookableItem::class }}">
            <input type="hidden" name="bookable_id" id="bookable_id" value="{{ $selectedBookableId }}">
            <input type="hidden" name="bookable_label" id="bookable_label" value="{{ $bookableSelectedLabel }}">

            <div class="a2-searchable-ajax-wrap" id="bookable_picker">
                <input
                    type="text"
                    class="a2-input"
                    id="bookable_search"
                    placeholder="ابحث عن غرفة / شقة / ملعب..."
                    value="{{ $bookableSelectedLabel }}"
                    autocomplete="off"
                >
                <div class="a2-searchable-results" id="bookable_results" style="display:none;"></div>
            </div>

            <div class="a2-hint" id="bookable_hint" style="margin-top:6px;">
                اختر البزنس والخدمة أولًا، ثم ابحث عن العنصر القابل للحجز.
            </div>
        </div>

        {{-- Date --}}
        <div>
            <label class="a2-label">Date</label>
            <input class="a2-input" name="date" type="date" value="{{ $val('date') }}" required>
        </div>

        {{-- Time --}}
        <div>
            <label class="a2-label">Time</label>
            <input class="a2-input" name="time" type="time" value="{{ $val('time') }}" required>
        </div>

        {{-- Starts at --}}
        <div>
            <label class="a2-label">Starts at</label>
            <input class="a2-input" name="starts_at" type="datetime-local"
                   value="{{ $val('starts_at') ? \Carbon\Carbon::parse($val('starts_at'))->format('Y-m-d\TH:i') : '' }}">
        </div>

        {{-- Ends at --}}
        <div>
            <label class="a2-label">Ends at</label>
            <input class="a2-input" name="ends_at" type="datetime-local"
                   value="{{ $val('ends_at') ? \Carbon\Carbon::parse($val('ends_at'))->format('Y-m-d\TH:i') : '' }}">
        </div>

        <div>
            <label class="a2-label">Duration value</label>
            <input class="a2-input" name="duration_value" type="number" min="1" value="{{ $val('duration_value') }}">
        </div>

        <div>
            <label class="a2-label">Duration unit</label>
            @php
                $units = ['minute'=>'minute','hour'=>'hour','day'=>'day','week'=>'week','month'=>'month','year'=>'year'];
                $sel = (string) $val('duration_unit', '');
            @endphp
            <select class="a2-input" name="duration_unit">
                <option value="">—</option>
                @foreach($units as $k => $label)
                    <option value="{{ $k }}" @selected($sel === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="a2-label">Quantity</label>
            <input class="a2-input" name="quantity" type="number" min="1" value="{{ $val('quantity', 1) }}">
        </div>

        <div>
            <label class="a2-label">Party size</label>
            <input class="a2-input" name="party_size" type="number" min="1" value="{{ $val('party_size') }}">
        </div>

        <div>
            <label class="a2-label">All day</label>
            @php $allDay = (string)($val('all_day', 0)); @endphp
            <select class="a2-input" name="all_day">
                <option value="0" @selected($allDay === '0')>No</option>
                <option value="1" @selected($allDay === '1')>Yes</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Timezone</label>
            <input class="a2-input" name="timezone" type="text" value="{{ $val('timezone', 'Africa/Cairo') }}" placeholder="Africa/Cairo">
        </div>

        <div style="grid-column:1 / -1;">
            <label class="a2-label">Status</label>
            @php $status = (string)$val('status', \App\Models\Booking::STATUS_PENDING); @endphp
            <select class="a2-input" name="status" required>
                @foreach($statusOptions as $k => $label)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div style="grid-column:1 / -1;">
            <label class="a2-label">Notes</label>
            <textarea class="a2-input" name="notes" rows="4">{{ $val('notes') }}</textarea>
        </div>
    </div>

    <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
        <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'حفظ' }}</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // =========================
    // 1) Searchable native selects
    // =========================
    function normalize(str) {
        return (str || '').toString().toLowerCase().trim();
    }

    document.querySelectorAll('.a2-searchable-input').forEach(function (input) {
        const targetSelector = input.getAttribute('data-target');
        const select = document.querySelector(targetSelector);
        if (!select) return;

        const originalOptions = Array.from(select.options).map(function (opt) {
            return {
                value: opt.value,
                text: opt.text,
                selected: opt.selected,
                dataset: Object.assign({}, opt.dataset || {}),
            };
        });

        input.addEventListener('input', function () {
            const term = normalize(input.value);
            const currentValue = select.value;

            select.innerHTML = '';

            originalOptions.forEach(function (item, index) {
                if (index === 0 || normalize(item.text).includes(term)) {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.text = item.text;
                    Object.keys(item.dataset).forEach(function (k) {
                        option.dataset[k] = item.dataset[k];
                    });

                    if (item.value === currentValue) {
                        option.selected = true;
                    }

                    select.appendChild(option);
                }
            });
        });
    });

    // =========================
    // 2) AJAX Bookable Item picker
    // =========================
    const businessSelect = document.getElementById('business_id_select');
    const serviceSelect = document.getElementById('service_id_select');

    const bookableSearch = document.getElementById('bookable_search');
    const bookableResults = document.getElementById('bookable_results');
    const bookableHint = document.getElementById('bookable_hint');

    const bookableIdInput = document.getElementById('bookable_id');
    const bookableTypeInput = document.getElementById('bookable_type');
    const bookableLabelInput = document.getElementById('bookable_label');

    let debounceTimer = null;

    function clearBookableSelection(clearText = false) {
        bookableIdInput.value = '';
        bookableTypeInput.value = '{{ \App\Models\BookableItem::class }}';
        bookableLabelInput.value = '';
        if (clearText) {
            bookableSearch.value = '';
        }
    }

    function hideBookableResults() {
        bookableResults.style.display = 'none';
        bookableResults.innerHTML = '';
    }

    function renderBookableResults(items) {
        if (!items || !items.length) {
            bookableResults.innerHTML = '<div class="a2-searchable-empty">لا توجد عناصر مطابقة</div>';
            bookableResults.style.display = 'block';
            return;
        }

        bookableResults.innerHTML = items.map(function (item) {
            const title = item.title || 'Item';
            const code = item.code ? ' — ' + item.code : '';
            const type = item.item_type ? ' (' + item.item_type + ')' : '';
            const price = item.price ? ' — Price: ' + item.price : '';
            return `
                <button type="button"
                        class="a2-searchable-result-item"
                        data-id="${item.id}"
                        data-label="${title}${code}"
                        data-type="{{ \App\Models\BookableItem::class }}">
                    <div style="font-weight:700;">${title}${code}</div>
                    <div class="a2-hint">${type}${price}</div>
                </button>
            `;
        }).join('');

        bookableResults.style.display = 'block';
    }

    async function fetchBookableItems(term = '') {
        const businessId = businessSelect ? businessSelect.value : '';
        const serviceId = serviceSelect ? serviceSelect.value : '';

        if (!businessId || !serviceId) {
            hideBookableResults();
            bookableHint.textContent = 'اختر البزنس والخدمة أولًا.';
            return;
        }

        const params = new URLSearchParams({
            q: term || '',
            business_id: businessId,
            service_id: serviceId
        });

        const url = "{{ route('admin.bookings.bookable-items.lookup') }}" + '?' + params.toString();

        try {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            const data = await res.json();

            if (!data || data.ok !== true) {
                renderBookableResults([]);
                return;
            }

            renderBookableResults(data.items || []);
            bookableHint.textContent = 'اختر العنصر المناسب من النتائج.';
        } catch (e) {
            console.error(e);
            renderBookableResults([]);
        }
    }

    bookableSearch?.addEventListener('input', function () {
        clearBookableSelection(false);

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            fetchBookableItems(bookableSearch.value || '');
        }, 250);
    });

    businessSelect?.addEventListener('change', function () {
        clearBookableSelection(true);
        hideBookableResults();
    });

    serviceSelect?.addEventListener('change', function () {
        clearBookableSelection(true);
        hideBookableResults();
    });

    bookableResults?.addEventListener('click', function (e) {
        const btn = e.target.closest('.a2-searchable-result-item');
        if (!btn) return;

        const id = btn.getAttribute('data-id') || '';
        const label = btn.getAttribute('data-label') || '';
        const type = btn.getAttribute('data-type') || '';

        bookableIdInput.value = id;
        bookableTypeInput.value = type;
        bookableLabelInput.value = label;
        bookableSearch.value = label;

        hideBookableResults();
        bookableHint.textContent = 'تم اختيار العنصر القابل للحجز.';
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#bookable_picker')) {
            hideBookableResults();
        }
    });
})();
</script>

<style>
.a2-searchable-wrap {
    display: grid;
    gap: 6px;
}

.a2-searchable-ajax-wrap {
    position: relative;
}

.a2-searchable-results {
    position: absolute;
    inset-inline: 0;
    top: calc(100% + 6px);
    background: #fff;
    border: 1px solid #dbe2ea;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,.10);
    z-index: 30;
    max-height: 280px;
    overflow: auto;
    padding: 6px;
}

.a2-searchable-result-item {
    width: 100%;
    text-align: start;
    background: transparent;
    border: 0;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
}

.a2-searchable-result-item:hover {
    background: #f5f7fb;
}

.a2-searchable-empty {
    padding: 10px 12px;
    color: #667085;
}
</style>
@endpush