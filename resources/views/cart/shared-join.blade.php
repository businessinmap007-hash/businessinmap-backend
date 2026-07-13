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
        .item { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-top: 1px solid var(--line); font-size: 14px; }
        .item:first-of-type { border-top: 0; }
        .item .opt { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .item .by { color: var(--brand); font-size: 12px; margin-top: 2px; }
        .totals .row { padding: 6px 0; }
        .totals .grand { font-size: 18px; font-weight: 800; border-top: 1px solid var(--line); margin-top: 6px; padding-top: 12px; }
        .btn { appearance: none; border: 0; border-radius: 11px; padding: 13px 18px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .btn-primary:disabled { opacity: .6; cursor: default; }
        .btn-ghost { background: transparent; color: var(--brand); border: 1px solid var(--line); }
        .field { display: block; margin-top: 10px; }
        .field label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
        .field input { width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 11px; font: inherit; background: #fff; }
        .note { background: #f0f6ff; border: 1px solid #d6e4ff; color: #1d4ed8; border-radius: 11px; padding: 11px 13px; font-size: 13px; }
        .alert { border-radius: 11px; padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .alert-danger { background: #fef3f2; border: 1px solid #fecdca; color: var(--danger); }
        .hidden { display: none !important; }
        .center { text-align: center; }
        .spin { width: 22px; height: 22px; border: 3px solid var(--line); border-top-color: var(--brand); border-radius: 50%; display: inline-block; animation: sp .8s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
        .copy { font-size: 12px; }
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

    {{-- Rendered cart. --}}
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

        <div class="card totals">
            <div class="row"><span class="muted">إجمالي الأصناف</span><span id="t-sub">—</span></div>
            <div class="row"><span class="muted">رسوم الخدمة</span><span id="t-fee">—</span></div>
            <div class="row"><span class="muted">الضريبة</span><span id="t-tax">—</span></div>
            <div class="row grand"><span>الإجمالي الكلي</span><span id="t-grand">—</span></div>
            <div class="note" style="margin-top:14px;">الدفع نقداً عند الوصول · كل مشارك يدفع فاتورته.</div>
        </div>

        <div class="card">
            <div class="row"><span class="muted copy">شارك هذا الرابط مع أصدقائك</span><button id="copy-link" class="btn btn-ghost" style="width:auto;padding:8px 14px;font-size:13px;">نسخ الرابط</button></div>
        </div>
    </div>
</div>

<script>
(function () {
    const TOKEN = @json($token);
    const TOKEN_KEY = 'bim_api_token';
    const JOIN_URL = '/api/v2/cart/join/' + encodeURIComponent(TOKEN);
    const CUR = 'ج.م';

    const $ = (id) => document.getElementById(id);
    const show = (id) => $(id).classList.remove('hidden');
    const hide = (id) => $(id).classList.add('hidden');
    const money = (n) => (Number(n) || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + CUR;
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

    function fail(msg) { const a = $('alert'); a.textContent = msg; show('alert'); }
    function clearFail() { hide('alert'); }
    function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
    function setToken(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch (e) {} }

    function roleLabel(r) { return r === 'host' ? 'المضيف' : 'عضو'; }

    function renderCart(cart) {
        // Business
        $('biz-name').textContent = cart.business ? cart.business.name : '—';
        if (cart.business && cart.business.logo) {
            const img = document.createElement('img');
            img.src = cart.business.logo; img.alt = ''; img.className = '';
            img.width = 52; img.height = 52; img.style.borderRadius = '12px'; img.style.objectFit = 'cover';
            $('biz-logo').replaceWith(img); img.id = 'biz-logo';
        }

        // Participants
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

        // Items
        const items = cart.items || [];
        if (items.length === 0) { show('items-empty'); $('items').innerHTML = ''; }
        else {
            hide('items-empty');
            $('items').innerHTML = items.map((it) => {
                const opts = [];
                if (it.options && it.options.size) opts.push(esc(it.options.size));
                if (it.options && it.options.extras && it.options.extras.length) opts.push(it.options.extras.map(esc).join('، '));
                return `
                <div class="item">
                    <div>
                        <div>${esc(it.name)} <span class="muted">×${it.qty}</span></div>
                        ${opts.length ? `<div class="opt">${opts.join(' · ')}</div>` : ''}
                        <div class="by">أضافه: ${esc(it.added_by && it.added_by.name) || '—'}</div>
                    </div>
                    <div style="white-space:nowrap;font-weight:600;">${money(it.total_price)}</div>
                </div>`;
            }).join('');
        }

        // Totals
        const t = cart.totals || {};
        $('t-sub').textContent = money(t.items_subtotal);
        $('t-fee').textContent = money(t.service_fee);
        $('t-tax').textContent = money(t.tax);
        $('t-grand').textContent = money(t.grand_total);

        hide('intro'); hide('loading'); hide('gate'); clearFail();
        show('cart');
    }

    async function join() {
        const token = getToken();
        if (!token) { showGate(); return; }

        hide('intro'); hide('gate'); clearFail(); show('loading');
        try {
            const res = await fetch(JOIN_URL, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
            });

            if (res.status === 401) { hide('loading'); setToken(''); fail('انتهت صلاحية رمز الدخول. أدخله من جديد.'); showGate(); return; }
            if (res.status === 404) { hide('loading'); fail('رابط السلة غير صالح أو تم إتمام الطلب.'); return; }
            if (!res.ok) { hide('loading'); fail('تعذّر الانضمام إلى السلة. حاول مرة أخرى.'); show('intro'); return; }

            const body = await res.json();
            const cart = body && body.data && body.data.cart;
            if (!cart) { hide('loading'); fail('استجابة غير متوقعة من الخادم.'); show('intro'); return; }
            renderCart(cart);
        } catch (e) {
            hide('loading'); fail('تعذّر الاتصال بالخادم.'); show('intro');
        }
    }

    function showGate() { hide('intro'); hide('cart'); hide('loading'); show('gate'); }

    // Wire up.
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

    // Boot: if we already have a token, join immediately; otherwise show intro.
    if (getToken()) join(); else show('intro');
})();
</script>
</body>
</html>
