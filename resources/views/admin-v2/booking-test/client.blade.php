@extends('admin-v2.layouts.master')

@section('title', 'تجربة الحجز كعميل')
@section('body_class', 'admin-v2 admin-v2-booking-test-client')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تجربة الحجز كعميل</h1>
            <div class="a2-page-subtitle">
                صفحة اختبارية خفيفة لمحاكاة ما سيراه العميل لاحقًا في الموقع أو تطبيق الهاتف.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                العودة للحجوزات
            </a>
        </div>
    </div>

    @if(!$bookingService)
        <div class="a2-alert a2-alert-warning">
            لم يتم العثور على خدمة booking داخل platform_services. تأكد أن لديك خدمة key/code/slug = booking.
        </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-flex-between a2-mb-16">
            <div>
                <h2 class="a2-section-title a2-mb-0">خطوات الحجز</h2>
                <div class="a2-section-subtitle">
                    اختر القسم، مقدم الخدمة، الموعد، ثم عاين السعر قبل إنشاء الحجز.
                </div>
            </div>

            <span class="a2-pill a2-pill-warning" id="bookingStatusPill">
                لم يتم إنشاء حجز بعد
            </span>
        </div>

        <div class="a2-card-grid-4 a2-mb-16">
            <div class="a2-kv-box">
                <span>الخطوة 1</span>
                <strong>اختيار الخدمة والقسم</strong>
            </div>
            <div class="a2-kv-box">
                <span>الخطوة 2</span>
                <strong>اختيار مقدم الخدمة</strong>
            </div>
            <div class="a2-kv-box">
                <span>الخطوة 3</span>
                <strong>اختيار الموعد</strong>
            </div>
            <div class="a2-kv-box">
                <span>الخطوة 4</span>
                <strong>معاينة السعر والتأكيد</strong>
            </div>
        </div>

        <form id="bookingTestForm" class="a2-stack">
            @csrf

            <input type="hidden" name="service_id" id="service_id" value="{{ optional($bookingService)->id }}">

            <div class="a2-card a2-card--soft">
                <h3 class="a2-section-title">1) الخدمة والقسم</h3>

                <div class="a2-form-grid">
                    <div class="a2-form-group">
                        <label class="a2-label">خدمة المنصة</label>
                        <div class="a2-view-field">
                            {{ $bookingService ? (($bookingService->name_ar ?? $bookingService->name_en ?? $bookingService->name ?? $bookingService->key) . ' #' . $bookingService->id) : 'Booking غير موجودة' }}
                        </div>
                        <div class="a2-help-block">
                            النسخة الأولى من صفحة الاختبار تعتمد على خدمة booking فقط.
                        </div>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">القسم الرئيسي</label>
                        <select name="root_id" id="root_id" class="a2-select">
                            <option value="">اختر القسم الرئيسي</option>
                            @foreach($roots as $root)
                                <option value="{{ $root->id }}">
                                    {{ $root->name_ar ?? $root->name_en ?? $root->name ?? ('#' . $root->id) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">القسم الفرعي</label>
                        <select name="child_id" id="child_id" class="a2-select" disabled>
                            <option value="">اختر القسم الفرعي</option>
                        </select>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">عدد / كمية</label>
                        <input type="number" name="quantity" id="quantity" class="a2-input" value="1" min="1">
                    </div>
                </div>
            </div>

            <div class="a2-card a2-card--soft">
                <h3 class="a2-section-title">2) مقدم الخدمة والعنصر</h3>

                <div class="a2-form-grid">
                    <div class="a2-form-group">
                        <label class="a2-label">مقدم الخدمة</label>
                        <select name="business_id" id="business_id" class="a2-select" disabled>
                            <option value="">اختر مقدم الخدمة</option>
                        </select>
                        <div class="a2-help-block">
                            يظهر هنا البزنس الذي لديه سعر أو تفعيل لخدمة booking.
                        </div>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">العنصر القابل للحجز</label>
                        <select name="bookable_item_id" id="bookable_item_id" class="a2-select" disabled>
                            <option value="">بدون عنصر / أو اختر عنصر</option>
                        </select>
                        <div class="a2-help-block">
                            مثل غرفة، قاعة، سيارة، موعد محدد. إذا لم توجد عناصر يمكن تركه فارغًا.
                        </div>
                    </div>
                </div>
            </div>

            <div class="a2-card a2-card--soft">
    <h3 class="a2-section-title">3) التاريخ والوقت</h3>

    @include('admin-v2.components.datetime-range-24', [
        'startName' => 'start_at',
        'endName' => 'end_at',
        'startValue' => old('start_at'),
        'endValue' => old('end_at'),
        'labelStart' => 'تاريخ / وقت البداية',
        'labelEnd' => 'تاريخ / وقت النهاية',
        'minuteStep' => 15,
        'required' => false,
    ])

    <div class="a2-form-grid a2-mt-16">
        <div class="a2-form-group">
            <label class="a2-label">عدد الضيوف / الأشخاص</label>
            <input type="number" name="guest_count" id="guest_count" class="a2-input" min="1" placeholder="اختياري">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">ملاحظات العميل</label>
            <input type="text" name="notes" id="notes" class="a2-input" placeholder="مثال: أريد موعد صباحي إن أمكن">
        </div>
    </div>
</div>

            <div class="a2-card a2-card--soft">
                <div class="a2-flex-between">
                    <div>
                        <h3 class="a2-section-title a2-mb-0">4) معاينة السعر</h3>
                        <div class="a2-section-subtitle">
                            اضغط معاينة السعر قبل إنشاء الحجز.
                        </div>
                    </div>

                    <div class="a2-inline-actions">
                        <button type="button" id="previewBtn" class="a2-btn a2-btn-primary">
                            معاينة السعر
                        </button>

                        <button type="button" id="storeBtn" class="a2-btn a2-btn-success" disabled>
                            تأكيد وإنشاء حجز اختباري
                        </button>
                    </div>
                </div>

                <div id="messageBox" class="a2-alert a2-alert-info a2-hidden"></div>

                <div id="previewBox" class="a2-card a2-card-muted a2-mt-16 a2-hidden">
                    <div class="a2-kv-grid-4">
                        <div class="a2-kv-grid a2-mb-12">
                            <div class="a2-kv-box">
                                <span>مدة الحجز</span>
                                <strong id="pvDuration">—</strong>
                            </div>

                            <div class="a2-kv-box">
                                <span>الأيام المحاسبية</span>
                                <strong id="pvBillableDays">—</strong>
                            </div>
                        </div>
                        
                        <div class="a2-kv-box">
                            <span>سعر الوحدة</span>
                            <strong id="pvUnit">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>السعر الأساسي</span>
                            <strong id="pvBase">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>رسوم المنصة</span>
                            <strong id="pvFee">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>الإجمالي</span>
                            <strong id="pvTotal">0</strong>
                        </div>
                    </div>

                    <div class="a2-kv-grid a2-mt-12">
                        <div class="a2-kv-box">
                            <span>العربون / الضمان</span>
                            <strong id="pvDeposit">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>ملاحظة العربون</span>
                            <strong id="pvDepositNote">—</strong>
                        </div>
                    </div>
                    <div class="a2-kv-grid-4 a2-mt-12">
                        <div class="a2-kv-box">
                            <span>رسوم العميل الأصلية</span>
                            <strong id="pvOriginalClientFee">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>رسوم العميل بعد العرض</span>
                            <strong id="pvFinalClientFee">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>رسوم البزنس الأصلية</span>
                            <strong id="pvOriginalBusinessFee">0</strong>
                        </div>

                        <div class="a2-kv-box">
                            <span>رسوم البزنس بعد العرض</span>
                            <strong id="pvFinalBusinessFee">0</strong>
                        </div>
                    </div>

                    <div id="promotionBox" class="a2-alert a2-alert-warning a2-mt-12 a2-hidden"></div>
                </div>

                <div id="createdBox" class="a2-alert a2-alert-success a2-hidden"></div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const urls = {
        children: @json(route('admin.booking-test.client.children')),
        businesses: @json(route('admin.booking-test.client.businesses')),
        items: @json(route('admin.booking-test.client.bookable-items')),
        preview: @json(route('admin.booking-test.client.pricing-preview')),
        store: @json(route('admin.booking-test.client.store')),
    };

    const csrf = @json(csrf_token());

    const rootSelect = document.getElementById('root_id');
    const childSelect = document.getElementById('child_id');
    const businessSelect = document.getElementById('business_id');
    const itemSelect = document.getElementById('bookable_item_id');
    const previewBtn = document.getElementById('previewBtn');
    const storeBtn = document.getElementById('storeBtn');
    const previewBox = document.getElementById('previewBox');
    const createdBox = document.getElementById('createdBox');
    const messageBox = document.getElementById('messageBox');
    const statusPill = document.getElementById('bookingStatusPill');

    function setMessage(text, type = 'info') {
        messageBox.className = 'a2-alert a2-alert-' + type;
        messageBox.textContent = text;
        messageBox.classList.remove('a2-hidden');
    }

    function clearMessage() {
        messageBox.classList.add('a2-hidden');
        messageBox.textContent = '';
    }

    function resetSelect(select, placeholder, disabled = true) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
        select.disabled = disabled;
    }

    function addOptions(select, items, placeholder) {
        resetSelect(select, placeholder, false);

        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;

            let label = item.name || ('#' + item.id);

            if (item.email) {
                label += ' - ' + item.email;
            }

            if (item.price && Number(item.price) > 0) {
                label += ' - السعر: ' + item.price;
            }

            if (item.capacity) {
                label += ' - السعة: ' + item.capacity;
            }

            select.appendChild(option);
            option.textContent = label;
        });

        if (!items.length) {
            resetSelect(select, 'لا توجد نتائج متاحة', true);
        }
    }

    async function getJson(url) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            }
        });

        return await res.json();
    }

    async function postJson(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (!res.ok) {
            throw json;
        }

        return json;
    }

    function fieldValue(nameOrId, fallback = null) {
    const form = document.getElementById('bookingTestForm');

    let el = null;

    if (form) {
        el = form.querySelector('[name="' + nameOrId + '"]');
    }

    if (!el) {
        el = document.getElementById(nameOrId);
    }

    if (!el) {
        return fallback;
    }

    return el.value || fallback;
}

    function formData() {
        return {
            root_id: fieldValue('root_id', null),
            child_id: fieldValue('child_id', null),
            service_id: fieldValue('service_id', null),
            business_id: fieldValue('business_id', null),
            bookable_item_id: fieldValue('bookable_item_id', null),

            start_at: fieldValue('start_at', null),
            end_at: fieldValue('end_at', null),

            quantity: fieldValue('quantity', 1),
            guest_count: fieldValue('guest_count', null),
            notes: fieldValue('notes', null),
        };
    }

    rootSelect.addEventListener('change', async function () {
        clearMessage();
        storeBtn.disabled = true;
        previewBox.classList.add('a2-hidden');
        createdBox.classList.add('a2-hidden');

        resetSelect(childSelect, 'جاري تحميل الأقسام الفرعية...', true);
        resetSelect(businessSelect, 'اختر مقدم الخدمة', true);
        resetSelect(itemSelect, 'بدون عنصر / أو اختر عنصر', true);

        if (!this.value) {
            resetSelect(childSelect, 'اختر القسم الفرعي', true);
            return;
        }

        try {
            const json = await getJson(urls.children + '?root_id=' + encodeURIComponent(this.value));
            addOptions(childSelect, json.items || [], 'اختر القسم الفرعي');
        } catch (e) {
            setMessage('تعذر تحميل الأقسام الفرعية.', 'danger');
            resetSelect(childSelect, 'خطأ في التحميل', true);
        }
    });

    childSelect.addEventListener('change', async function () {
        clearMessage();
        storeBtn.disabled = true;
        previewBox.classList.add('a2-hidden');
        createdBox.classList.add('a2-hidden');

        resetSelect(businessSelect, 'جاري تحميل مقدمي الخدمة...', true);
        resetSelect(itemSelect, 'بدون عنصر / أو اختر عنصر', true);

        const childId = this.value;
        const serviceId = document.getElementById('service_id').value;

        if (!childId || !serviceId) {
            resetSelect(businessSelect, 'اختر مقدم الخدمة', true);
            return;
        }

        try {
            const json = await getJson(
                urls.businesses
                + '?child_id=' + encodeURIComponent(childId)
                + '&service_id=' + encodeURIComponent(serviceId)
            );

            addOptions(businessSelect, json.items || [], 'اختر مقدم الخدمة');
        } catch (e) {
            setMessage('تعذر تحميل مقدمي الخدمة.', 'danger');
            resetSelect(businessSelect, 'خطأ في التحميل', true);
        }
    });

    businessSelect.addEventListener('change', async function () {
        clearMessage();
        storeBtn.disabled = true;
        previewBox.classList.add('a2-hidden');
        createdBox.classList.add('a2-hidden');

        resetSelect(itemSelect, 'جاري تحميل العناصر...', true);

        const businessId = this.value;
        const serviceId = document.getElementById('service_id').value;
        const childId = document.getElementById('child_id').value;

        if (!businessId || !serviceId) {
            resetSelect(itemSelect, 'بدون عنصر / أو اختر عنصر', true);
            return;
        }

        try {
            const json = await getJson(
                urls.items
                + '?business_id=' + encodeURIComponent(businessId)
                + '&service_id=' + encodeURIComponent(serviceId)
                + '&child_id=' + encodeURIComponent(childId)
            );

            const items = json.items || [];

            resetSelect(itemSelect, 'بدون عنصر / أو اختر عنصر', false);

            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;

                let label = item.name || ('#' + item.id);

                if (item.type) {
                    label += ' - ' + item.type;
                }

                if (item.capacity) {
                    label += ' - السعة: ' + item.capacity;
                }

                if (item.price && Number(item.price) > 0) {
                    label += ' - السعر: ' + item.price;
                }

                option.textContent = label;
                itemSelect.appendChild(option);
            });
        } catch (e) {
            setMessage('تعذر تحميل العناصر القابلة للحجز.', 'danger');
            resetSelect(itemSelect, 'بدون عنصر / أو اختر عنصر', false);
        }
    });

    previewBtn.addEventListener('click', async function () {
        clearMessage();
        createdBox.classList.add('a2-hidden');
        storeBtn.disabled = true;

        const data = formData();

        if (!data.child_id || !data.service_id || !data.business_id) {
            setMessage('اختر القسم الفرعي ومقدم الخدمة أولًا.', 'warning');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.textContent = 'جاري الحساب...';

        try {
            const json = await postJson(urls.preview, data);
            const pv = json.preview || {};

            const duration = pv.duration || {};

            if (document.getElementById('pvDuration')) {
                document.getElementById('pvDuration').textContent =
                    duration.label || '—';
            }

            if (document.getElementById('pvBillableDays')) {
                document.getElementById('pvBillableDays').textContent =
                    duration.billing_label || ((duration.billable_days || pv.billable_units || 1) + ' يوم');
            }

            document.getElementById('pvUnit').textContent = (pv.unit_price || 0) + ' ' + (pv.currency || 'EGP');


            document.getElementById('pvUnit').textContent = (pv.unit_price || 0) + ' ' + (pv.currency || 'EGP');
            document.getElementById('pvBase').textContent = (pv.base_price || 0) + ' ' + (pv.currency || 'EGP');
            document.getElementById('pvFee').textContent = (pv.platform_fee || 0) + ' ' + (pv.currency || 'EGP');
            document.getElementById('pvTotal').textContent = (pv.total || 0) + ' ' + (pv.currency || 'EGP');
            document.getElementById('pvDeposit').textContent = (pv.deposit_amount || 0) + ' ' + (pv.currency || 'EGP');
            document.getElementById('pvDepositNote').textContent = pv.deposit_note || '—';
            const fees = pv.fees || {};

            document.getElementById('pvOriginalClientFee').textContent =
                (fees.original_client_fee || 0) + ' ' + (pv.currency || 'EGP');

            document.getElementById('pvFinalClientFee').textContent =
                (fees.final_client_fee || 0) + ' ' + (pv.currency || 'EGP');

            document.getElementById('pvOriginalBusinessFee').textContent =
                (fees.original_business_fee || 0) + ' ' + (pv.currency || 'EGP');

            document.getElementById('pvFinalBusinessFee').textContent =
                (fees.final_business_fee || 0) + ' ' + (pv.currency || 'EGP');

            const promotionBox = document.getElementById('promotionBox');

            if (fees.platform_promotion_applied && fees.platform_promotion) {
                promotionBox.classList.remove('a2-hidden');
                promotionBox.textContent =
                    'عرض منصة فعال: '
                    + fees.platform_promotion.name
                    + ' | النوع: '
                    + fees.platform_promotion.discount_type
                    + ' | الطرف: '
                    + fees.platform_promotion.target_party;
            } else {
                promotionBox.classList.add('a2-hidden');
                promotionBox.textContent = '';
            }

            previewBox.classList.remove('a2-hidden');
            storeBtn.disabled = false;

            setMessage('تم حساب السعر بنجاح. يمكنك الآن إنشاء حجز اختباري.', 'success');
        } catch (e) {
            const msg = e.message || 'تعذر حساب السعر. راجع البيانات المختارة.';
            setMessage(msg, 'danger');
        } finally {
            previewBtn.disabled = false;
            previewBtn.textContent = 'معاينة السعر';
        }
    });

    storeBtn.addEventListener('click', async function () {
        clearMessage();
        createdBox.classList.add('a2-hidden');

        const data = formData();

        if (!data.child_id || !data.service_id || !data.business_id) {
            setMessage('اختر القسم الفرعي ومقدم الخدمة أولًا.', 'warning');
            return;
        }

        storeBtn.disabled = true;
        storeBtn.textContent = 'جاري إنشاء الحجز...';

        try {
            const json = await postJson(urls.store, data);

            createdBox.textContent = 'تم إنشاء الحجز الاختباري بنجاح. رقم الحجز: #' + json.booking_id + ' - الحالة: ' + json.status;
            createdBox.classList.remove('a2-hidden');

            statusPill.className = 'a2-pill a2-pill-success';
            statusPill.textContent = 'تم إنشاء حجز #' + json.booking_id;
        } catch (e) {
            const msg = e.message || 'تعذر إنشاء الحجز. تأكد من توافق أعمدة جدول bookings.';
            setMessage(msg, 'danger');
            storeBtn.disabled = false;
        } finally {
            storeBtn.textContent = 'تأكيد وإنشاء حجز اختباري';
        }
    });
})();
</script>
@endsection