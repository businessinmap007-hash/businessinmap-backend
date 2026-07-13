<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مشاركة السلة الجماعية — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --ink: #1c2430; --muted: #6b7686;
            --line: #e6e9ef; --brand: #2f6bff; --brand-ink: #ffffff;
            --danger: #b42318; --chip: #eef2ff;
            --radius: 14px; --shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.06);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font-family: "Segoe UI", Tahoma, system-ui, -apple-system, sans-serif;
            line-height: 1.5; -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 560px; margin: 0 auto; padding: 16px 16px 48px; }
        .topbar { display: flex; align-items: center; gap: 10px; padding: 8px 0 16px; }
        .topbar .dot { width: 30px; height: 30px; border-radius: 9px; background: var(--brand); }
        .topbar b { font-size: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px; margin-bottom: 14px; }
        .center { text-align: center; }
        .muted { color: var(--muted); }
        .sec-title { font-size: 14px; font-weight: 700; margin: 2px 0 12px; }
        .row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .btn { appearance: none; border: 0; border-radius: 11px; padding: 13px 18px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .btn-ghost { background: transparent; color: var(--brand); border: 1px solid var(--line); }
        .field { display: block; margin-top: 10px; }
        .field label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
        .field input { width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 11px; font: inherit; background: #fff; }
        .qr-box { display: flex; justify-content: center; padding: 8px 0 4px; }
        .qr-box img { width: 240px; height: 240px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .link-row { display: flex; gap: 8px; margin-top: 6px; }
        .link-row input { flex: 1; padding: 11px 12px; border: 1px solid var(--line); border-radius: 11px; font: inherit; background: #f8fafc; color: var(--ink); direction: ltr; text-align: left; }
        .link-row button { flex: 0 0 auto; white-space: nowrap; width: auto; padding: 11px 14px; font-size: 13px; }
        .actions { display: grid; gap: 10px; margin-top: 4px; }
        .note { background: #f0f6ff; border: 1px solid #d6e4ff; color: #1d4ed8; border-radius: 11px; padding: 11px 13px; font-size: 13px; }
        .alert { border-radius: 11px; padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .alert-danger { background: #fef3f2; border: 1px solid #fecdca; color: var(--danger); }
        .hidden { display: none !important; }
        .spin { width: 22px; height: 22px; border: 3px solid var(--line); border-top-color: var(--brand); border-radius: 50%; display: inline-block; animation: sp .8s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar"><span class="dot"></span><b>{{ config('app.name') }}</b></div>

    <div id="alert" class="alert alert-danger hidden"></div>

    {{-- Token gate: the page drives the sanctum API, so it needs the host's token. --}}
    <div id="gate" class="card hidden">
        <div class="sec-title">مطلوب تسجيل الدخول</div>
        <p class="muted" style="margin-top:0;font-size:13px;">لمشاركة سلتك أدخل رمز الدخول الخاص بحسابك (API token).</p>
        <div class="field">
            <label for="token-input">رمز الدخول</label>
            <input id="token-input" type="text" autocomplete="off" placeholder="ألصق الرمز هنا" dir="ltr">
        </div>
        <div style="margin-top:14px;"><button id="save-token" class="btn btn-primary">حفظ ومتابعة</button></div>
    </div>

    <div id="intro" class="card hidden">
        <div class="sec-title">شارك سلتك مع أصدقائك</div>
        <p class="muted" style="margin-top:0;">افتح سلتك للمشاركة ليحصل أصدقاؤك على رابط ورمز QR للانضمام وإضافة أصنافهم. الدفع نقداً عند الوصول، وكل شخص يدفع فاتورته.</p>
        <button id="share-btn" class="btn btn-primary">إنشاء رابط المشاركة</button>
    </div>

    <div id="loading" class="card center hidden"><span class="spin"></span></div>

    {{-- The share result: QR + copyable link + actions. --}}
    <div id="result" class="hidden">
        <div class="card center">
            <div class="sec-title">امسح الرمز للانضمام</div>
            <div class="qr-box"><img id="qr-img" alt="رمز الانضمام" width="240" height="240"></div>
            <div class="muted" style="font-size:12px;margin-top:6px;">وجّه كاميرا صديقك إلى الرمز للانضمام فوراً.</div>
        </div>

        <div class="card">
            <div class="sec-title">أو انسخ الرابط</div>
            <div class="link-row">
                <input id="join-link" type="text" readonly>
                <button id="copy-link" class="btn btn-ghost">نسخ</button>
            </div>
            <div class="actions" style="margin-top:14px;">
                <button id="native-share" class="btn btn-primary hidden">مشاركة الرابط</button>
                <a id="open-cart" class="btn btn-ghost center" style="text-decoration:none;display:block;">فتح صفحة السلة</a>
            </div>
            <div class="note" style="margin-top:14px;">الدفع نقداً عند الوصول · كل مشارك يدفع فاتورته.</div>
        </div>
    </div>
</div>

<script>
(function () {
    const BUSINESS_ID = @json($businessId);
    const TOKEN_KEY = 'bim_api_token';
    const SHARE_URL = '/api/v2/cart/' + encodeURIComponent(BUSINESS_ID) + '/share';

    const $ = (id) => document.getElementById(id);
    const show = (id) => $(id).classList.remove('hidden');
    const hide = (id) => $(id).classList.add('hidden');

    function fail(msg) { const a = $('alert'); a.textContent = msg; show('alert'); }
    function clearFail() { hide('alert'); }
    function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
    function setToken(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch (e) {} }

    function render(token) {
        // Build the join URL on the current origin (always matches this host).
        const joinUrl = window.location.origin + '/cart/join/' + encodeURIComponent(token);
        $('join-link').value = joinUrl;
        $('qr-img').src = '/cart/join/' + encodeURIComponent(token) + '/qr';
        $('open-cart').href = '/cart/join/' + encodeURIComponent(token);

        if (navigator.share) {
            const btn = $('native-share');
            show('native-share');
            btn.onclick = () => navigator.share({ title: 'انضم إلى سلتنا الجماعية', url: joinUrl }).catch(() => {});
        }

        hide('intro'); hide('gate'); hide('loading'); clearFail();
        show('result');
    }

    async function share() {
        const token = getToken();
        if (!token) { showGate(); return; }

        hide('intro'); hide('gate'); clearFail(); show('loading');
        try {
            const res = await fetch(SHARE_URL, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
            });

            if (res.status === 401) { hide('loading'); setToken(''); fail('انتهت صلاحية رمز الدخول. أدخله من جديد.'); showGate(); return; }
            if (!res.ok) { hide('loading'); fail('تعذّر إنشاء رابط المشاركة. حاول مرة أخرى.'); show('intro'); return; }

            const body = await res.json();
            const shareToken = body && body.data && body.data.share_token;
            if (!shareToken) { hide('loading'); fail('استجابة غير متوقعة من الخادم.'); show('intro'); return; }
            render(shareToken);
        } catch (e) {
            hide('loading'); fail('تعذّر الاتصال بالخادم.'); show('intro');
        }
    }

    function showGate() { hide('intro'); hide('result'); hide('loading'); show('gate'); }

    $('save-token').addEventListener('click', () => {
        const v = $('token-input').value.trim();
        if (!v) { $('token-input').focus(); return; }
        setToken(v); share();
    });
    $('share-btn').addEventListener('click', share);
    $('copy-link').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText($('join-link').value); $('copy-link').textContent = 'تم ✓'; setTimeout(() => $('copy-link').textContent = 'نسخ', 1600); }
        catch (e) { $('join-link').select(); }
    });

    // Boot: with a token, open the share immediately; otherwise show the intro.
    if (getToken()) share(); else show('intro');
})();
</script>
</body>
</html>
