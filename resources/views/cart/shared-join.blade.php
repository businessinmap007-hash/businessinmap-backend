<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>السلة الجماعية — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --ink: #1c2430; --muted: #6b7686;
            --line: #e6e9ef; --brand: #2f6bff; --brand-ink: #ffffff;
            --ok: #157347; --warn: #b54708; --danger: #b42318; --chip: #eef2ff;
            --radius: 14px; --shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.06);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font-family: "Segoe UI", Tahoma, system-ui, -apple-system, sans-serif;
            line-height: 1.5; -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 680px; margin: 0 auto; padding: 16px 16px 48px; }
        .topbar { display: flex; align-items: center; gap: 10px; padding: 8px 0 16px; }
        .topbar .dot { width: 30px; height: 30px; border-radius: 9px; background: var(--brand); }
        .topbar b { font-size: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px; margin-bottom: 14px; }
        .biz { display: flex; align-items: center; gap: 12px; }
        .biz img, .biz .ph { width: 52px; height: 52px; border-radius: 12px; object-fit: cover; background: var(--chip); flex: 0 0 auto; }
        .biz h1 { font-size: 18px; margin: 0; }
        .biz .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
        .muted { color: var(--muted); }
        .row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .sec-title { font-size: 14px; font-weight: 700; margin: 2px 0 12px; }
        .badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: var(--chip); color: var(--brand); }
        .badge.host { background: #fff4e5; color: var(--warn); }
        .person { padding: 12px 0; border-top: 1px solid var(--line); }
        .person:first-of-type { border-top: 0; }
        .person .name { font-weight: 600; }
        .person .lines { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; margin-top: 8px; font-size: 13px; color: var(--muted); }
        .person .lines .tot { color: var(--ink); font-weight: 700; }
        .item { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid var(--line); font-size: 14px; }
        .item:first-of-type { border-top: 0; }
        .item .opt { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .item .by { color: var(--brand); font-size: 12px; margin-top: 2px; }
        .item .ctl { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
        .stepper { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .stepper button { border: 0; background: #f8fafc; width: 32px; height: 32px; font-size: 17px; cursor: pointer; color: var(--ink); }
        .stepper button:active { background: #eef2f7; }
        .stepper .q { min-width: 30px; text-align: center; font-weight: 700; }
        .icon-btn { border: 1px solid var(--line); background: #fff; border-radius: 10px; height: 32px; padding: 0 10px; cursor: pointer; color: var(--danger); font-size: 13px; }
        .totals .row { padding: 6px 0; }
        .totals .grand { font-size: 18px; font-weight: 800; border-top: 1px solid var(--line); margin-top: 6px; padding-top: 12px; }
        .btn { appearance: none; border: 0; border-radius: 11px; padding: 13px 18px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; }
        .btn:disabled { opacity: .6; cursor: default; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .btn-ghost { background: transparent; color: var(--brand); border: 1px solid var(--line); }
        .btn-danger { background: #fff; color: var(--danger); border: 1px solid #fecdca; }
        .btn-ok { background: var(--ok); color: #fff; }
        .field { display: block; margin-top: 10px; }
        .field label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
        .field input, .field select { width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 11px; font: inherit; background: #fff; }
        .note { background: #f0f6ff; border: 1px solid #d6e4ff; color: #1d4ed8; border-radius: 11px; padding: 11px 13px; font-size: 13px; }
        .alert { border-radius: 11px; padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .alert-danger { background: #fef3f2; border: 1px solid #fecdca; color: var(--danger); }
        .hidden { display: none !important; }
        .center { text-align: center; }
        .spin { width: 22px; height: 22px; border: 3px solid var(--line); border-top-color: var(--brand); border-radius: 50%; display: inline-block; animation: sp .8s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
        .mrow { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; padding: 12px 0; border-top: 1px solid var(--line); }
        .mrow:first-of-type { border-top: 0; }
        .mrow .add-ctl { display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
        .mrow select { padding: 7px 9px; border: 1px solid var(--line); border-radius: 9px; font: inherit; background: #fff; }
        .mrow .extras { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; font-size: 12px; }
        .mrow .extras label { display: inline-flex; gap: 5px; align-items: center; color: var(--muted); }
        .grp-title { font-size: 13px; font-weight: 700; color: var(--muted); margin: 14px 0 2px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar"><span class="dot"></span><b>{{ config('app.name') }}</b></div>

    <div id="alert" class="alert alert-danger hidden"></div>

    {{-- Token gate: the page drives the sanctum API, so it needs the user's token. --}}
    <div id="gate" class="card hidden">
        <div class="sec-title">مطلوب تسجيل الدخول</div>
        <p class="muted" style="margin-top:0;font-size:13px;">للانضمام إلى السلة الجماعية أدخل رمز الدخول الخاص بحسابك (API token).</p>
        <div class="field">
            <label for="token-input">رمز الدخول</label>
            <input id="token-input" type="text" autocomplete="off" placeholder="ألصق الرمز هنا" dir="ltr">
        </div>
        <div style="margin-top:14px;"><button id="save-token" class="btn btn-primary">حفظ ومتابعة</button></div>
    </div>

    {{-- Intro / join call to action. --}}
    <div id="intro" class="card hidden">
        <div class="sec-title">دعوة للانضمام إلى سلة جماعية</div>
        <p class="muted" style="margin-top:0;">انضم إلى الطلب المشترك لتضيف أصنافك. الدفع نقداً عند الوصول، وكل شخص يدفع فاتورته الخاصة.</p>
        <button id="join-btn" class="btn btn-primary">انضمام وعرض السلة</button>
    </div>

    <div id="loading" class="card center hidden"><span class="spin"></span></div>

    {{-- Terminal state (left / cancelled / checked out). --}}
    <div id="terminal" class="card center hidden">
        <div id="terminal-title" class="sec-title" style="margin-bottom:6px;"></div>
        <div id="terminal-body" class="muted"></div>
    </div>

    {{-- Rendered cart + management. --}}
    <div id="cart" class="hidden">
        <div class="card">
            <div class="biz">
                <span id="biz-logo" class="ph"></span>
                <div>
                    <h1 id="biz-name">—</h1>
                    <div class="sub">سلة جماعية · <span id="my-role">مشارك</span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="row"><div class="sec-title" style="margin:0;">المشاركون وفواتيرهم</div><span id="ppl-count" class="badge"></span></div>
            <div id="participants" style="margin-top:6px;"></div>
        </div>

        <div class="card">
            <div class="sec-title">الأصناف</div>
            <div id="items"></div>
            <div id="items-empty" class="muted hidden" style="padding:8px 0;">لا توجد أصناف بعد.</div>
        </div>

        {{-- Add items: lazy-loaded menu browser. --}}
        <div class="card">
            <div class="row">
                <div class="sec-title" style="margin:0;">أضف أصنافك</div>
                <button id="toggle-menu" class="btn btn-ghost" style="width:auto;padding:8px 14px;font-size:13px;">تصفّح المنيو</button>
            </div>
            <div id="menu-loading" class="center hidden" style="padding:12px 0;"><span class="spin"></span></div>
            <div id="menu" class="hidden" style="margin-top:8px;"></div>
        </div>

        <div class="card totals">
            <div class="row"><span class="muted">إجمالي الأصناف</span><span id="t-sub">—</span></div>
            <div class="row"><span class="muted">رسوم الخدمة</span><span id="t-fee">—</span></div>
            <div class="row"><span class="muted">الضريبة</span><span id="t-tax">—</span></div>
            <div class="row grand"><span>الإجمالي الكلي</span><span id="t-grand">—</span></div>
            <div class="note" style="margin-top:14px;">الدفع نقداً عند الوصول · كل مشارك يدفع فاتورته.</div>
        </div>

        {{-- Role-based actions. --}}
        <div id="host-actions" class="card hidden">
            <div class="field" style="margin-top:0;">
                <label for="fulfillment">طريقة الاستلام</label>
                <select id="fulfillment">
                    <option value="dine_in">في المطعم</option>
                    <option value="pickup">استلام</option>
                    <option value="delivery">توصيل</option>
                </select>
            </div>

            {{-- Delivery only: a one-off GPS pin for this order (no saved address needed). --}}
            <div id="delivery-fields" class="hidden">
                <div class="field">
                    <label>موقع التوصيل</label>
                    <button id="use-location" type="button" class="btn btn-ghost" style="width:auto;padding:9px 14px;font-size:13px;">📍 استخدم موقعي الحالي</button>
                    <div id="loc-status" class="muted" style="font-size:12px;margin-top:8px;"></div>
                </div>
                <div class="field">
                    <label for="address-note">تفاصيل العنوان (اختياري)</label>
                    <input id="address-note" type="text" placeholder="مثال: بجوار المسجد، الدور الثالث">
                </div>
            </div>

            <div style="display:grid;gap:10px;margin-top:14px;">
                <button id="checkout-btn" class="btn btn-ok">إتمام الطلب</button>
                <button id="cancel-btn" class="btn btn-danger">إلغاء السلة</button>
            </div>
        </div>

        <div id="member-actions" class="card hidden">
            <button id="leave-btn" class="btn btn-danger">مغادرة السلة</button>
        </div>

        <div class="card">
            <div class="row"><span class="muted" style="font-size:12px;">شارك هذا الرابط مع أصدقائك</span><button id="copy-link" class="btn btn-ghost" style="width:auto;padding:8px 14px;font-size:13px;">نسخ الرابط</button></div>
        </div>
    </div>
</div>

<script>
(function () {
    const TOKEN = @json($token);
    const TOKEN_KEY = 'bim_api_token';
    const JOIN_URL = '/api/v2/cart/join/' + encodeURIComponent(TOKEN);
    const CUR = 'ج.م';

    const state = { cart: null, menuLoaded: false, pin: null };

    const $ = (id) => document.getElementById(id);
    const show = (id) => $(id).classList.remove('hidden');
    const hide = (id) => $(id).classList.add('hidden');
    const money = (n) => (Number(n) || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + CUR;
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

    function fail(msg) { const a = $('alert'); a.textContent = msg; show('alert'); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function clearFail() { hide('alert'); }
    function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
    function setToken(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch (e) {} }
    function roleLabel(r) { return r === 'host' ? 'المضيف' : 'عضو'; }

    // Thin API wrapper: attaches the bearer token, normalises errors.
    async function api(method, url, body) {
        const token = getToken();
        if (!token) { showGate(); return { handled: true }; }
        const opts = { method, headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' } };
        if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
        let res;
        try { res = await fetch(url, opts); }
        catch (e) { fail('تعذّر الاتصال بالخادم.'); return { handled: true }; }
        if (res.status === 401) { setToken(''); fail('انتهت صلاحية رمز الدخول. أدخله من جديد.'); showGate(); return { handled: true }; }
        let json = null; try { json = await res.json(); } catch (e) {}
        return { res, json };
    }

    function editable(it) {
        const v = state.cart && state.cart.viewer;
        return !!(v && (v.is_host || (it.added_by && it.added_by.id === v.user_id)));
    }

    function renderCart(cart) {
        state.cart = cart;

        $('biz-name').textContent = cart.business ? cart.business.name : '—';
        if (cart.business && cart.business.logo) {
            const img = document.createElement('img');
            img.src = cart.business.logo; img.alt = ''; img.width = 52; img.height = 52;
            img.style.borderRadius = '12px'; img.style.objectFit = 'cover';
            const cur = $('biz-logo'); cur.replaceWith(img); img.id = 'biz-logo';
        }
        $('my-role').textContent = cart.viewer ? roleLabel(cart.viewer.role) : 'مشارك';

        const parts = cart.participants || [];
        $('ppl-count').textContent = parts.length + ' مشارك';
        $('participants').innerHTML = parts.map((p) => `
            <div class="person">
                <div class="row">
                    <span class="name">${esc(p.name) || '—'} <span class="badge ${p.role === 'host' ? 'host' : ''}">${roleLabel(p.role)}</span></span>
                    <span class="muted">${p.items_count} صنف</span>
                </div>
                <div class="lines">
                    <span>الأصناف</span><span>${money(p.items_subtotal)}</span>
                    <span>رسوم الخدمة${p.service_included ? ' (شاملة)' : ''}</span><span>${money(p.service_fee)}</span>
                    <span>الضريبة${p.tax_included ? ' (شاملة)' : ''}</span><span>${money(p.tax)}</span>
                    <span class="tot">فاتورته</span><span class="tot">${money(p.total)}</span>
                </div>
            </div>`).join('');

        const items = cart.items || [];
        if (items.length === 0) { show('items-empty'); $('items').innerHTML = ''; }
        else {
            hide('items-empty');
            $('items').innerHTML = items.map((it) => {
                const opts = [];
                if (it.options && it.options.size) opts.push(esc(it.options.size));
                if (it.options && it.options.extras && it.options.extras.length) opts.push(it.options.extras.map(esc).join('، '));
                const canEdit = editable(it);
                const ctl = canEdit ? `
                    <div class="ctl">
                        <span class="stepper">
                            <button data-act="dec" data-id="${it.id}" data-qty="${it.qty}">−</button>
                            <span class="q">${it.qty}</span>
                            <button data-act="inc" data-id="${it.id}" data-qty="${it.qty}">＋</button>
                        </span>
                        <button class="icon-btn" data-act="del" data-id="${it.id}">حذف</button>
                    </div>` : '';
                return `
                <div class="item">
                    <div>
                        <div>${esc(it.name)} <span class="muted">×${it.qty}</span></div>
                        ${opts.length ? `<div class="opt">${opts.join(' · ')}</div>` : ''}
                        <div class="by">أضافه: ${esc(it.added_by && it.added_by.name) || '—'}</div>
                        ${ctl}
                    </div>
                    <div style="white-space:nowrap;font-weight:600;">${money(it.total_price)}</div>
                </div>`;
            }).join('');
        }

        const t = cart.totals || {};
        $('t-sub').textContent = money(t.items_subtotal);
        $('t-fee').textContent = money(t.service_fee);
        $('t-tax').textContent = money(t.tax);
        $('t-grand').textContent = money(t.grand_total);

        // Role-based action cards.
        const isHost = cart.viewer && cart.viewer.is_host;
        (isHost ? show : hide)('host-actions');
        (!isHost && cart.viewer ? show : hide)('member-actions');
        if (isHost) toggleDeliveryFields();

        hide('intro'); hide('loading'); hide('gate'); hide('terminal'); clearFail();
        show('cart');
    }

    function terminal(title, body) {
        hide('cart'); hide('loading'); hide('intro'); clearFail();
        $('terminal-title').textContent = title;
        $('terminal-body').textContent = body;
        show('terminal');
    }

    async function join() {
        if (!getToken()) { showGate(); return; }
        hide('intro'); hide('gate'); clearFail(); show('loading');
        const { res, json, handled } = await api('POST', JOIN_URL);
        if (handled) { hide('loading'); return; }
        if (res.status === 404) { hide('loading'); fail('رابط السلة غير صالح أو تم إتمام الطلب.'); return; }
        if (!res.ok) { hide('loading'); fail('تعذّر الانضمام إلى السلة.'); show('intro'); return; }
        const cart = json && json.data && json.data.cart;
        if (!cart) { hide('loading'); fail('استجابة غير متوقعة من الخادم.'); show('intro'); return; }
        renderCart(cart);
    }

    // ── Line operations ──
    async function changeQty(itemId, qty) {
        const oid = state.cart.id;
        const { res, json, handled } = await api('PATCH', `/api/v2/cart/shared/${oid}/items/${itemId}`, { qty });
        if (handled) return;
        if (!res.ok) { fail('تعذّر تعديل الكمية.'); return; }
        renderCart(json.data.cart);
    }
    async function removeLine(itemId) {
        const oid = state.cart.id;
        const { res, json, handled } = await api('DELETE', `/api/v2/cart/shared/${oid}/items/${itemId}`);
        if (handled) return;
        if (!res.ok) { fail('تعذّر حذف الصنف.'); return; }
        renderCart(json.data.cart);
    }

    // ── Add items (menu browser) ──
    async function loadMenu() {
        if (state.menuLoaded) { $('menu').classList.toggle('hidden'); return; }
        const bizId = state.cart.business && state.cart.business.id;
        if (!bizId) return;
        show('menu-loading');
        let json = null;
        try { json = await (await fetch('/api/v2/discovery/menu/' + bizId, { headers: { 'Accept': 'application/json' } })).json(); }
        catch (e) { hide('menu-loading'); fail('تعذّر تحميل المنيو.'); return; }
        hide('menu-loading');
        const sections = (json && json.data && json.data.sections) || [];
        if (!sections.length) { $('menu').innerHTML = '<div class="muted" style="padding:8px 0;">لا يوجد منيو متاح.</div>'; }
        else { $('menu').innerHTML = sections.map(renderSection).join(''); }
        state.menuLoaded = true;
        show('menu');
    }
    function renderSection(sec) {
        return `<div class="grp-title">${esc(sec.name)}</div>` + (sec.items || []).map(renderMenuItem).join('');
    }
    function renderMenuItem(it) {
        const sizeSel = (it.variants && it.variants.length) ? `
            <select data-role="size" data-item="${it.id}">
                <option value="">الحجم الافتراضي</option>
                ${it.variants.map((v) => `<option value="${v.id}">${esc(v.name)} · ${money(v.price)}</option>`).join('')}
            </select>` : '';
        const extras = (it.extras && it.extras.length) ? `
            <div class="extras">${it.extras.map((e) => `<label><input type="checkbox" data-role="extra" data-item="${it.id}" value="${e.id}"> ${esc(e.name)} (+${money(e.price)})</label>`).join('')}</div>` : '';
        return `
        <div class="mrow" data-mitem="${it.id}">
            <div style="flex:1;">
                <div>${esc(it.name)}</div>
                <div class="muted" style="font-size:12px;">${money(it.base_price)}</div>
                ${extras}
            </div>
            <div style="text-align:left;">
                <div class="add-ctl">
                    ${sizeSel}
                    <span class="stepper">
                        <button data-role="mdec" data-item="${it.id}">−</button>
                        <span class="q" data-role="mqty" data-item="${it.id}">1</span>
                        <button data-role="minc" data-item="${it.id}">＋</button>
                    </span>
                    <button class="btn btn-primary" style="width:auto;padding:8px 14px;font-size:13px;" data-role="add" data-item="${it.id}">أضف</button>
                </div>
            </div>
        </div>`;
    }
    function menuQtyEl(itemId) { return $('menu').querySelector(`[data-role="mqty"][data-item="${itemId}"]`); }
    async function addMenuItem(itemId) {
        const qtyEl = menuQtyEl(itemId);
        const qty = Math.max(1, parseInt(qtyEl ? qtyEl.textContent : '1', 10) || 1);
        const sizeEl = $('menu').querySelector(`[data-role="size"][data-item="${itemId}"]`);
        const sizeId = sizeEl && sizeEl.value ? parseInt(sizeEl.value, 10) : null;
        const extras = Array.from($('menu').querySelectorAll(`[data-role="extra"][data-item="${itemId}"]:checked`)).map((c) => parseInt(c.value, 10));
        const oid = state.cart.id;
        const payload = { kind: 'menu', offering_id: itemId, qty };
        if (sizeId) payload.size_id = sizeId;
        if (extras.length) payload.extras = extras;
        const { res, json, handled } = await api('POST', `/api/v2/cart/shared/${oid}/items`, payload);
        if (handled) return;
        if (!res.ok) { fail('تعذّر إضافة الصنف.'); return; }
        renderCart(json.data.cart);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Delivery location (a one-off GPS pin) ──
    function toggleDeliveryFields() {
        const isDelivery = $('fulfillment').value === 'delivery';
        (isDelivery ? show : hide)('delivery-fields');
    }

    // Uses the browser's built-in Geolocation (no map provider); the pin is
    // resolved to one of our own cities for display via /locations/nearest.
    function useMyLocation() {
        if (!navigator.geolocation) { $('loc-status').textContent = 'المتصفح لا يدعم تحديد الموقع.'; return; }
        $('loc-status').textContent = 'جارٍ تحديد موقعك…';
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            state.pin = { lat, lng };
            $('loc-status').textContent = '📍 تم تحديد موقعك. جارٍ التعرّف على المدينة…';
            try {
                const r = await fetch(`/api/v2/locations/nearest?lat=${lat}&lng=${lng}`, { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                const m = j && j.data && j.data.match;
                const city = m && m.city ? (m.city.name_ar || m.city.name_en) : null;
                $('loc-status').textContent = city ? `📍 موقعك الحالي: ${city}` : '📍 تم تحديد موقعك على الخريطة.';
            } catch (e) {
                $('loc-status').textContent = '📍 تم تحديد موقعك على الخريطة.';
            }
        }, (err) => {
            state.pin = null;
            $('loc-status').textContent = err.code === err.PERMISSION_DENIED
                ? 'تم رفض إذن الموقع. فعّله أو اكتب العنوان يدوياً.'
                : 'تعذّر تحديد موقعك. حاول مجدداً أو اكتب العنوان.';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    }

    // ── Lifecycle ──
    async function checkout() {
        if (!confirm('تأكيد إتمام الطلب؟')) return;
        const oid = state.cart.id;
        const payload = { fulfillment_type: $('fulfillment').value };
        if (payload.fulfillment_type === 'delivery') {
            const note = $('address-note').value.trim();
            if (note) payload.address = note;
            if (state.pin) { payload.lat = state.pin.lat; payload.lng = state.pin.lng; }
        }
        const { res, handled } = await api('POST', `/api/v2/cart/shared/${oid}/checkout`, payload);
        if (handled) return;
        if (!res.ok) { fail('تعذّر إتمام الطلب.'); return; }
        terminal('تم إرسال الطلب ✓', 'استلم المطعم طلبكم. الدفع نقداً عند الوصول، وكل مشارك يدفع فاتورته.');
    }
    async function cancelCart() {
        if (!confirm('إلغاء السلة الجماعية نهائياً؟')) return;
        const oid = state.cart.id;
        const { res, handled } = await api('DELETE', `/api/v2/cart/shared/${oid}`);
        if (handled) return;
        if (!res.ok) { fail('تعذّر إلغاء السلة.'); return; }
        terminal('أُلغيت السلة', 'تم إلغاء السلة الجماعية. تم إخطار المشاركين.');
    }
    async function leaveCart() {
        if (!confirm('مغادرة السلة؟ ستُحذف أصنافك.')) return;
        const oid = state.cart.id;
        const { res, handled } = await api('POST', `/api/v2/cart/shared/${oid}/leave`);
        if (handled) return;
        if (!res.ok) { fail('تعذّر مغادرة السلة.'); return; }
        terminal('غادرت السلة', 'تمت مغادرتك للسلة الجماعية وحُذفت أصنافك.');
    }

    function showGate() { hide('intro'); hide('cart'); hide('loading'); hide('terminal'); show('gate'); }

    // ── Event wiring (delegated) ──
    $('items').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-act]'); if (!b) return;
        const id = parseInt(b.dataset.id, 10);
        if (b.dataset.act === 'del') return removeLine(id);
        const qty = parseInt(b.dataset.qty, 10) || 1;
        if (b.dataset.act === 'inc') return changeQty(id, qty + 1);
        if (b.dataset.act === 'dec') return changeQty(id, qty - 1); // qty 0 removes server-side
    });
    $('menu').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-role]'); if (!b) return;
        const id = parseInt(b.dataset.item, 10);
        const qtyEl = menuQtyEl(id);
        if (b.dataset.role === 'add') return addMenuItem(id);
        if (!qtyEl) return;
        let q = parseInt(qtyEl.textContent, 10) || 1;
        if (b.dataset.role === 'minc') qtyEl.textContent = q + 1;
        if (b.dataset.role === 'mdec') qtyEl.textContent = Math.max(1, q - 1);
    });
    $('toggle-menu').addEventListener('click', loadMenu);
    $('checkout-btn').addEventListener('click', checkout);
    $('cancel-btn').addEventListener('click', cancelCart);
    $('fulfillment').addEventListener('change', toggleDeliveryFields);
    $('use-location').addEventListener('click', useMyLocation);
    $('leave-btn').addEventListener('click', leaveCart);
    $('save-token').addEventListener('click', () => {
        const v = $('token-input').value.trim();
        if (!v) { $('token-input').focus(); return; }
        setToken(v); join();
    });
    $('join-btn').addEventListener('click', join);
    $('copy-link').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(window.location.href); $('copy-link').textContent = 'تم النسخ ✓'; setTimeout(() => $('copy-link').textContent = 'نسخ الرابط', 1600); }
        catch (e) { fail('تعذّر نسخ الرابط.'); }
    });

    if (getToken()) join(); else show('intro');
})();
</script>
</body>
</html>
