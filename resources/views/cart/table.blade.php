<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>طاولة المطعم — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --ink: #1c2430; --muted: #6b7686;
            --line: #e6e9ef; --brand: #2f6bff; --brand-ink: #ffffff; --danger: #b42318;
            --radius: 14px; --shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.06);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: "Segoe UI", Tahoma, system-ui, -apple-system, sans-serif; line-height: 1.5; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px 16px 48px; }
        .topbar { display: flex; align-items: center; gap: 10px; padding: 8px 0 16px; }
        .topbar .dot { width: 30px; height: 30px; border-radius: 9px; background: var(--brand); }
        .topbar b { font-size: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px; margin-bottom: 14px; }
        .center { text-align: center; }
        .muted { color: var(--muted); }
        .sec-title { font-size: 14px; font-weight: 700; margin: 2px 0 12px; }
        .btn { appearance: none; border: 0; border-radius: 11px; padding: 13px 18px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .field { display: block; margin-top: 10px; }
        .field label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; }
        .field input { width: 100%; padding: 12px; border: 1px solid var(--line); border-radius: 11px; font: inherit; background: #fff; }
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

    {{-- Token gate: scanning must be authenticated to join as a participant. --}}
    <div id="gate" class="card hidden">
        <div class="sec-title">مطلوب تسجيل الدخول</div>
        <p class="muted" style="margin-top:0;font-size:13px;">للانضمام إلى طلب الطاولة أدخل رمز الدخول الخاص بحسابك (API token).</p>
        <div class="field">
            <label for="token-input">رمز الدخول</label>
            <input id="token-input" type="text" autocomplete="off" placeholder="ألصق الرمز هنا" dir="ltr">
        </div>
        <div style="margin-top:14px;"><button id="save-token" class="btn btn-primary">حفظ ومتابعة</button></div>
    </div>

    <div id="intro" class="card hidden">
        <div class="sec-title">طلب الطاولة</div>
        <p class="muted" style="margin-top:0;">انضم إلى طلب طاولتك لتضيف أصنافك. الدفع نقداً عند الوصول، وكل شخص يدفع فاتورته.</p>
        <button id="scan-btn" class="btn btn-primary">فتح طلب الطاولة</button>
    </div>

    <div id="loading" class="card center hidden"><span class="spin"></span><div class="muted" style="margin-top:8px;">جارٍ فتح طلب الطاولة…</div></div>
</div>

<script>
(function () {
    const TOKEN = @json($token);
    const TOKEN_KEY = 'bim_api_token';
    const SCAN_URL = '/api/v2/table/' + encodeURIComponent(TOKEN) + '/scan';

    const $ = (id) => document.getElementById(id);
    const show = (id) => $(id).classList.remove('hidden');
    const hide = (id) => $(id).classList.add('hidden');
    function fail(msg) { const a = $('alert'); a.textContent = msg; show('alert'); }
    function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
    function setToken(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch (e) {} }
    function showGate() { hide('intro'); hide('loading'); show('gate'); }

    async function scan() {
        const token = getToken();
        if (!token) { showGate(); return; }
        hide('intro'); hide('gate'); hide('alert'); show('loading');
        let res;
        try { res = await fetch(SCAN_URL, { method: 'POST', headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' } }); }
        catch (e) { hide('loading'); fail('تعذّر الاتصال بالخادم.'); show('intro'); return; }
        if (res.status === 401) { hide('loading'); setToken(''); fail('انتهت صلاحية رمز الدخول. أدخله من جديد.'); showGate(); return; }
        if (res.status === 404) { hide('loading'); fail('هذه الطاولة غير موجودة أو غير مفعّلة.'); return; }
        if (!res.ok) { hide('loading'); fail('تعذّر فتح طلب الطاولة.'); show('intro'); return; }
        let body = null; try { body = await res.json(); } catch (e) {}
        const shareToken = body && body.data && body.data.share_token;
        if (!shareToken) { hide('loading'); fail('استجابة غير متوقعة من الخادم.'); show('intro'); return; }
        // Hand off to the shared-cart management page (join is idempotent).
        window.location.replace('/cart/join/' + encodeURIComponent(shareToken));
    }

    $('save-token').addEventListener('click', () => {
        const v = $('token-input').value.trim();
        if (!v) { $('token-input').focus(); return; }
        setToken(v); scan();
    });
    $('scan-btn').addEventListener('click', scan);

    if (getToken()) scan(); else show('intro');
})();
</script>
</body>
</html>
