@php
    $isEdit = isset($row) && $row?->exists;
    $currentService = (int) old('service_id', $row->service_id ?? 0);
    $currentType = (string) old('bookable_item_type', $row->bookable_item_type ?? '');

    $supportsDepositByService = ($services ?? collect())
        ->mapWithKeys(fn ($s) => [(int) $s->id => (bool) ($s->supports_deposit ?? false)])
        ->all();
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
        {{ __('لا توجد خدمات متاحة لنشاطك بعد.') }}
    </div>
@else
    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('السعر لكل نوع') }}</div>
                <div class="a2-card-sub">{{ __('حدّد سعر كل نوع تقدّمه. الوحدات الفعلية تأخذ سعرها من هنا حسب نوعها.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="service_id">{{ __('الخدمة') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bp-service" id="service_id" name="service_id" required>
                    <option value="">{{ __('اختر الخدمة') }}</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected($currentService === (int) $service->id)>
                            {{ $service->name_ar ?: ($service->name_en ?: $service->key) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="bookable_item_type">{{ __('نوع العنصر') }} <span class="a2-danger">*</span></label>
                <select class="a2-select js-bp-type" id="bookable_item_type" name="bookable_item_type" required data-current-value="{{ $currentType }}">
                    <option value="">{{ __('اختر الخدمة أولًا') }}</option>
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="price">{{ __('السعر') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="price" name="price" value="{{ old('price', $row->price ?? 0) }}" inputmode="decimal" placeholder="0.00" required>
            </div>

            <div class="a2-form-group">
                <label class="a2-label" for="currency">{{ __('العملة') }}</label>
                <input class="a2-input" id="currency" name="currency" value="{{ old('currency', $row->currency ?? 'EGP') }}" dir="ltr" maxlength="3" style="text-transform:uppercase;">
            </div>

            <div class="a2-form-group">
                <label class="a2-label">{{ __('الحالة') }}</label>
                <label class="a2-check" style="margin-top:10px;">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))>
                    <span>{{ __('السعر مفعّل') }}</span>
                </label>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('طريقة احتساب الوحدة') }}</div>
                <div class="a2-card-sub">{{ __('للطاولات: مجانية / رسوم حجز / حد أدنى للطلب. للغرف والملاعب اتركها "سعر عادي".') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="charge_mode">{{ __('الطريقة') }}</label>
                <select class="a2-select js-bp-charge-mode" id="charge_mode" name="charge_mode">
                    @php $cm = old('charge_mode', $row->charge_mode ?? 'standard'); @endphp
                    <option value="standard" @selected($cm === 'standard')>{{ __('سعر عادي (السعر أعلاه)') }}</option>
                    <option value="free" @selected($cm === 'free')>{{ __('مجانية — يُحتسب الأكل فقط') }}</option>
                    <option value="reservation_fee" @selected($cm === 'reservation_fee')>{{ __('رسوم حجز ثابتة') }}</option>
                    <option value="minimum_charge" @selected($cm === 'minimum_charge')>{{ __('حد أدنى للطلب') }}</option>
                </select>
            </div>

            <div class="a2-form-group js-bp-charge-amount-wrap">
                <label class="a2-label" for="charge_amount">{{ __('قيمة الرسوم / الحد الأدنى') }}</label>
                <input class="a2-input" id="charge_amount" name="charge_amount" value="{{ old('charge_amount', $row->charge_amount ?? 0) }}" inputmode="decimal" placeholder="0.00">
                <div class="a2-hint a2-mt-8 js-bp-charge-hint"></div>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('الخصم') }}</div>
                <div class="a2-card-sub">{{ __('التأمين يُدار مركزيًا من سياسة التأمين، لا من هنا.') }}</div>
            </div>
        </div>

        <div class="a2-check-grid" style="margin-bottom:16px;">
            <label class="a2-check-card">
                <input type="checkbox" name="discount_enabled" id="discount_enabled" value="1" @checked((bool) old('discount_enabled', (int) ($row->discount_enabled ?? 0)))>
                <span>{{ __('تفعيل الخصم') }}</span>
            </label>
        </div>

        <div class="a2-form-grid-3">
            <div class="a2-form-group">
                <label class="a2-label" for="discount_percent">{{ __('نسبة الخصم %') }}</label>
                <input class="a2-input" id="discount_percent" name="discount_percent" value="{{ old('discount_percent', (int) ($row->discount_percent ?? 0)) }}" inputmode="numeric" placeholder="0">
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? __('تحديث') : __('حفظ') }}</button>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typesByService = @json($allowedTypesByService ?? []);
        const i18n = {
            pickServiceFirst: @json(__('اختر الخدمة أولًا')),
            noTypes: @json(__('لا توجد أنواع مسموحة')),
            pickType: @json(__('اختر النوع')),
            reservationFee: @json(__('رسوم حجز ثابتة تُضاف على الفاتورة.')),
            minimumCharge: @json(__('أقل مبلغ يجب إنفاقه؛ لو قلّ الطلب عنه يُكمَّل إليه.')),
        };
        const serviceSelect = document.querySelector('.js-bp-service');
        const typeSelect = document.querySelector('.js-bp-type');

        const discountEnabled = document.getElementById('discount_enabled');
        const discountPercent = document.getElementById('discount_percent');

        function rebuildTypes() {
            if (!serviceSelect || !typeSelect) return;
            const serviceId = String(serviceSelect.value || '');
            const keep = String(typeSelect.dataset.currentValue || typeSelect.value || '');
            const list = (typesByService[serviceId] || []);
            typeSelect.innerHTML = '';

            if (!serviceId) {
                const o = document.createElement('option'); o.value = ''; o.textContent = i18n.pickServiceFirst;
                typeSelect.appendChild(o); return;
            }
            if (!list.length) {
                const o = document.createElement('option'); o.value = ''; o.textContent = i18n.noTypes;
                typeSelect.appendChild(o); return;
            }
            const empty = document.createElement('option'); empty.value = ''; empty.textContent = i18n.pickType;
            typeSelect.appendChild(empty);
            list.forEach(function (t) {
                const o = document.createElement('option');
                o.value = String(t.key); o.textContent = String(t.label || t.key);
                if (String(t.key) === keep) o.selected = true;
                typeSelect.appendChild(o);
            });
        }

        function refreshDiscount() {
            if (!discountEnabled || !discountPercent) return;
            discountPercent.disabled = !discountEnabled.checked;
            if (!discountEnabled.checked) discountPercent.value = 0;
        }

        const chargeMode = document.querySelector('.js-bp-charge-mode');
        const chargeAmountWrap = document.querySelector('.js-bp-charge-amount-wrap');
        const chargeHint = document.querySelector('.js-bp-charge-hint');
        const hints = {
            reservation_fee: i18n.reservationFee,
            minimum_charge: i18n.minimumCharge,
        };

        function refreshCharge() {
            if (!chargeMode || !chargeAmountWrap) return;
            const mode = String(chargeMode.value || 'standard');
            const needsAmount = (mode === 'reservation_fee' || mode === 'minimum_charge');
            chargeAmountWrap.style.display = needsAmount ? '' : 'none';
            if (chargeHint) chargeHint.textContent = hints[mode] || '';
        }

        serviceSelect?.addEventListener('change', function () {
            typeSelect.dataset.currentValue = '';
            rebuildTypes();
        });
        discountEnabled?.addEventListener('change', refreshDiscount);
        chargeMode?.addEventListener('change', refreshCharge);

        rebuildTypes();
        refreshDiscount();
        refreshCharge();
    });
    </script>
    @endpush
@endif
