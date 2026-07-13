<!doctype html>
<html lang="ar" dir="rtl">
@php
    $hasGeo = is_numeric($biz->latitude ?? null) && is_numeric($biz->longitude ?? null)
        && (float) $biz->latitude !== 0.0 && (float) $biz->longitude !== 0.0;
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $biz->name }} — {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --ink: #1c2430; --muted: #6b7686;
            --line: #e6e9ef; --brand: #2f6bff; --brand-ink: #ffffff;
            --radius: 14px; --shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.06);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: "Segoe UI", Tahoma, system-ui, -apple-system, sans-serif; line-height: 1.5; }
        .wrap { max-width: 620px; margin: 0 auto; padding: 0 0 48px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px; margin: 0 16px 14px; }
        .cover { height: 180px; background: #dfe4ec center/cover no-repeat; }
        .head { display: flex; align-items: flex-end; gap: 14px; margin: -46px 16px 0; position: relative; }
        .head img.logo { width: 88px; height: 88px; border-radius: 18px; object-fit: cover; border: 3px solid #fff; background: #fff; box-shadow: var(--shadow); }
        .head .name { padding-bottom: 6px; }
        .head .name h1 { margin: 0; font-size: 22px; }
        .muted { color: var(--muted); }
        .sec-title { font-size: 13px; font-weight: 700; color: var(--muted); margin: 0 0 8px; }
        .about { white-space: pre-line; }
        .btn { display: block; text-align: center; text-decoration: none; border: 0; border-radius: 11px; padding: 13px 18px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: var(--brand); color: var(--brand-ink); }
        .btn-ghost { background: transparent; color: var(--brand); border: 1px solid var(--line); }
        .actions { display: grid; gap: 10px; }
        .row { display: flex; align-items: center; gap: 8px; }
        .topbar { display: flex; align-items: center; gap: 10px; padding: 14px 16px; }
        .topbar .dot { width: 26px; height: 26px; border-radius: 8px; background: var(--brand); }
        .topbar b { font-size: 15px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar"><span class="dot"></span><b>{{ config('app.name') }}</b></div>

    <div class="cover" @if($biz->cover) style="background-image:url('{{ $biz->cover }}')" @endif></div>

    <div class="head">
        <img class="logo" src="{{ $biz->logo ?: '/assets/images/avatarempty.png' }}" alt="">
        <div class="name"><h1>{{ $biz->name }}</h1></div>
    </div>

    @if(trim((string) $biz->about) !== '')
        <div class="card">
            <div class="sec-title">نبذة</div>
            <div class="about">{{ $biz->about }}</div>
        </div>
    @endif

    <div class="card actions">
        @if($biz->phone)
            <a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $biz->phone) }}">اتصال: {{ $biz->phone }}</a>
        @endif
        @if($hasGeo)
            <a class="btn btn-ghost" target="_blank" rel="noopener"
               href="https://www.google.com/maps/search/?api=1&query={{ $biz->latitude }},{{ $biz->longitude }}">الموقع على الخريطة</a>
        @endif
        <button class="btn btn-primary" id="share-btn" type="button">مشاركة المتجر</button>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('share-btn');
    btn.addEventListener('click', async () => {
        const data = { title: @json($biz->name), url: window.location.href };
        if (navigator.share) { try { await navigator.share(data); } catch (e) {} return; }
        try { await navigator.clipboard.writeText(window.location.href); btn.textContent = 'تم نسخ الرابط ✓'; setTimeout(() => btn.textContent = 'مشاركة المتجر', 1600); }
        catch (e) {}
    });
})();
</script>
</body>
</html>
