@php
    // Standalone page (no master layout / panel middleware here), so resolve the
    // panel language ourselves — default Arabic, honouring the toggle if set.
    $__locale = session('panel_locale', 'ar');
    if (! in_array($__locale, config('app.supported_locales', ['ar', 'en']), true)) { $__locale = 'ar'; }
    app()->setLocale($__locale);
    $__other = $__locale === 'ar' ? 'en' : 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $__locale }}" dir="{{ $__locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('دخول النشاط التجاري') }}</title>
    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
</head>
<body class="admin-v2">
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div class="a2-card a2-card--section" style="width:100%;max-width:420px;">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ __('دخول لوحة النشاط التجاري') }}</div>
                    <div class="a2-card-sub">{{ __('سجّل الدخول لإدارة وحداتك وأسعارك وحجوزاتك.') }}</div>
                </div>
                <a href="{{ route('business.locale.switch', $__other) }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ $__locale === 'ar' ? 'EN' : 'ع' }}</a>
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
                    <label class="a2-label" for="email">{{ __('البريد الإلكتروني') }}</label>
                    <input class="a2-input" id="email" type="email" name="email" value="{{ old('email') }}" dir="ltr" required autofocus>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label" for="password">{{ __('كلمة المرور') }}</label>
                    <input class="a2-input" id="password" type="password" name="password" required>
                </div>

                <label class="a2-check a2-mt-8">
                    <input type="checkbox" name="remember" value="1">
                    <span>{{ __('تذكّرني') }}</span>
                </label>

                <div class="a2-page-actions" style="margin-top:16px;">
                    <button type="submit" class="a2-btn a2-btn-primary" style="width:100%;">{{ __('دخول') }}</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
