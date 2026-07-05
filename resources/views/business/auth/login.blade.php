<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دخول النشاط التجاري</title>
    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
</head>
<body class="admin-v2">
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div class="a2-card a2-card--section" style="width:100%;max-width:420px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">دخول لوحة النشاط التجاري</div>
                    <div class="a2-card-sub">سجّل الدخول لإدارة وحداتك وأسعارك وحجوزاتك.</div>
                </div>
            </div>

            @if($errors->any())
                <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
            @endif

            @if(session('success'))
                <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('business.login.post') }}">
                @csrf

                <div class="a2-form-group">
                    <label class="a2-label" for="email">البريد الإلكتروني</label>
                    <input class="a2-input" id="email" type="email" name="email" value="{{ old('email') }}" dir="ltr" required autofocus>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label" for="password">كلمة المرور</label>
                    <input class="a2-input" id="password" type="password" name="password" required>
                </div>

                <label class="a2-check a2-mt-8">
                    <input type="checkbox" name="remember" value="1">
                    <span>تذكّرني</span>
                </label>

                <div class="a2-page-actions" style="margin-top:16px;">
                    <button type="submit" class="a2-btn a2-btn-primary" style="width:100%;">دخول</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
