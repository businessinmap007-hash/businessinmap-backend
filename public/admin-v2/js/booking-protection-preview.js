(function () {
    function loadAdminFixesCss() {
        if (document.querySelector('link[data-admin-fixes="1"]')) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/admin-v2/css/admin-fixes.css';
        link.dataset.adminFixes = '1';
        document.head.appendChild(link);
    }

    function money(value, currency) {
        return Number(value || 0).toFixed(2) + ' ' + (currency || 'EGP');
    }

    function text(value, fallback) {
        return value === null || value === undefined || value === '' ? (fallback || '—') : String(value);
    }

    function methodLabel(method, status) {
        const labels = {
            trusted_client: 'عميل موثوق / VIP',
            guarantee: 'ضمان العميل',
            deposit: 'ديبوزت',
            pending_business_approval: 'ينتظر قرار البزنس',
            rejected: 'مرفوض'
        };

        if (status === 'blocked_by_business') {
            return 'محظور عند هذا البزنس';
        }

        return labels[method] || 'غير محدد';
    }

    function statusLabel(status) {
        const labels = {
            approved: 'مقبول بدون ضمان أو ديبوزت',
            covered: 'مغطى بالضمان',
            guarantee_below_required: 'الضمان أقل من المطلوب ويحتاج موافقة',
            deposit_required: 'يحتاج ديبوزت',
            no_protection_business_decision_required: 'لا توجد حماية ويحتاج قرار البزنس',
            blocked_by_business: 'العميل محظور من هذا البزنس'
        };

        return labels[status] || text(status);
    }

    function ensureCard() {
        let card = document.getElementById('booking_protection_card');

        if (card) {
            return card;
        }

        const side = document.querySelector('.bk-side');
        const showGrid = document.querySelector('.booking-show-hero-grid');

        if (!side && !showGrid) {
            return null;
        }

        card = document.createElement('div');
        card.id = 'booking_protection_card';
        card.className = side ? 'a2-card bk-card' : 'a2-card booking-show-section';
        card.innerHTML = `
            <div class="a2-title">حماية الحجز</div>
            <div class="a2-section-subtitle">قرار الحماية لا يلغي رسوم استخدام الخدمة لصالح التطبيق.</div>
            <div class="bk-kv-grid booking-show-kv-grid">
                <div class="bk-kv booking-show-kv"><span>طريقة الحماية</span><strong id="protection_method">—</strong></div>
                <div class="bk-kv booking-show-kv"><span>الحالة</span><strong id="protection_status">—</strong></div>
                <div class="bk-kv booking-show-kv"><span>الديبوزت مطلوب؟</span><strong id="protection_deposit">—</strong></div>
                <div class="bk-kv booking-show-kv"><span>الضمان مطلوب؟</span><strong id="protection_guarantee">—</strong></div>
                <div class="bk-kv booking-show-kv"><span>تغطية متاحة</span><strong id="protection_available">0.00 EGP</strong></div>
                <div class="bk-kv booking-show-kv"><span>رسوم المنصة</span><strong id="protection_fees">مطلوبة دائمًا</strong></div>
            </div>
            <div id="protection_note" class="a2-alert a2-alert-info" style="margin-top:10px;">اختر العميل والبزنس واحسب السعر لعرض قرار الحماية.</div>
        `;

        if (side) {
            const feeCardTitle = Array.from(side.querySelectorAll('.a2-title')).find(function (node) {
                return String(node.textContent || '').includes('رسوم التنفيذ');
            });
            const feeCard = feeCardTitle ? feeCardTitle.closest('.a2-card') : null;

            if (feeCard) {
                side.insertBefore(card, feeCard);
            } else {
                side.appendChild(card);
            }
            return card;
        }

        showGrid.insertAdjacentElement('afterend', card);
        return card;
    }

    function setCard(decision) {
        ensureCard();

        const method = document.getElementById('protection_method');
        const status = document.getElementById('protection_status');
        const deposit = document.getElementById('protection_deposit');
        const guarantee = document.getElementById('protection_guarantee');
        const available = document.getElementById('protection_available');
        const fees = document.getElementById('protection_fees');
        const note = document.getElementById('protection_note');

        if (!decision) {
            if (method) method.textContent = '—';
            if (status) status.textContent = '—';
            if (deposit) deposit.textContent = '—';
            if (guarantee) guarantee.textContent = '—';
            if (available) available.textContent = money(0);
            if (fees) fees.textContent = 'مطلوبة دائمًا';
            if (note) note.textContent = 'اختر العميل والبزنس واحسب السعر لعرض قرار الحماية.';
            return;
        }

        if (method) method.textContent = methodLabel(decision.method, decision.status);
        if (status) status.textContent = statusLabel(decision.status);
        if (deposit) deposit.textContent = decision.deposit_required ? 'نعم' : 'لا';
        if (guarantee) guarantee.textContent = decision.guarantee_required ? 'نعم' : 'لا';
        if (available) available.textContent = money(decision.available_coverage || 0);
        if (fees) fees.textContent = decision.platform_fees_required === false ? 'غير مطلوبة' : 'مطلوبة دائمًا';

        if (note) {
            if (decision.status === 'guarantee_below_required') {
                note.textContent = 'العميل لديه ضمان أقل من المطلوب. يمكن للبزنس قبول الضمان الأقل أو طلب ترقية/ديبوزت.';
                note.className = 'a2-alert a2-alert-warning';
            } else if (decision.status === 'no_protection_business_decision_required') {
                note.textContent = 'لا يوجد ضمان أو ديبوزت. القرار يرجع لصاحب البزنس بالقبول أو الرفض.';
                note.className = 'a2-alert a2-alert-warning';
            } else if (decision.status === 'blocked_by_business') {
                note.textContent = 'هذا العميل محظور عند هذا البزنس.';
                note.className = 'a2-alert a2-alert-danger';
            } else {
                note.textContent = decision.service_fees_note || 'رسوم استخدام الخدمة لصالح التطبيق تظل مطلوبة.';
                note.className = 'a2-alert a2-alert-info';
            }
        }
    }

    function value(id) {
        const el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function summaryNumber(id) {
        const el = document.getElementById(id);
        if (!el) return 0;
        const raw = String(el.textContent || '').replace(/[^0-9.\-]/g, '');
        return Number(raw || 0);
    }

    function currentBookingId() {
        const explicit = window.BIM_BOOKING_ID;
        if (explicit) return String(explicit);
        const match = window.location.pathname.match(/\/admin\/bookings\/(\d+)/);
        return match ? match[1] : '';
    }

    let timer = null;

    async function refresh() {
        const endpoint = window.BIM_BOOKING_PROTECTION_PREVIEW_URL;

        if (!endpoint) {
            return;
        }

        const url = new URL(endpoint, window.location.origin);
        const bookingId = currentBookingId();

        if (bookingId && document.body.classList.contains('admin-v2-booking-show')) {
            url.searchParams.set('booking_id', bookingId);
        } else {
            const userId = value('user_id');
            const businessId = value('business_id');

            if (!userId || !businessId) {
                setCard(null);
                return;
            }

            const amount = summaryNumber('summary_final_price');
            const depositAmount = summaryNumber('summary_deposit_amount');
            const depositRequired = (document.getElementById('summary_deposit_required')?.textContent || '').includes('نعم');

            url.searchParams.set('user_id', userId);
            url.searchParams.set('business_id', businessId);
            url.searchParams.set('amount', amount);
            url.searchParams.set('deposit_required', depositRequired ? '1' : '0');
            url.searchParams.set('deposit_amount', depositAmount);
        }

        try {
            const res = await fetch(url.toString(), {headers: {'Accept': 'application/json'}});
            const data = await res.json();
            setCard(data.protection || null);
        } catch (e) {
            console.error('Booking protection preview error:', e);
        }
    }

    function scheduleRefresh() {
        clearTimeout(timer);
        timer = setTimeout(refresh, 350);
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadAdminFixesCss();
        ensureCard();
        ['user_id', 'business_id', 'bookable_id', 'quantity'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', scheduleRefresh);
                el.addEventListener('input', scheduleRefresh);
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.closest('.requester-option') || event.target.closest('.business-option')) {
                scheduleRefresh();
            }
        });

        scheduleRefresh();
    });
})();
