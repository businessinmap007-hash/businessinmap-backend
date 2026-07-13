<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رموز الطاولات — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #111; font-family: "Segoe UI", Tahoma, system-ui, sans-serif; padding: 16px; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button { border: 0; background: #2f6bff; color: #fff; border-radius: 10px; padding: 10px 18px; font: inherit; font-weight: 700; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .tile { border: 1px solid #d7dbe3; border-radius: 14px; padding: 18px; text-align: center; break-inside: avoid; }
        .tile img { width: 180px; height: 180px; }
        .tile .label { font-size: 18px; font-weight: 800; margin-top: 10px; }
        .tile .hint { font-size: 12px; color: #6b7686; margin-top: 4px; }
        .empty { text-align: center; color: #6b7686; padding: 40px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">طباعة</button></div>

    @if($rows->isEmpty())
        <div class="empty">لا طاولات نشطة للطباعة.</div>
    @else
        <div class="grid">
            @foreach($rows as $row)
                <div class="tile">
                    <img src="{{ route('table.qr', $row->token, false) }}" alt="QR {{ $row->label }}">
                    <div class="label">{{ $row->label }}</div>
                    <div class="hint">امسح الرمز لبدء طلبك</div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
