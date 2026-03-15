@extends('admin-v2.layouts.auth')

@section('title', 'Admin Login')

@section('content')
<div class="a2-auth a2-auth--luxe">

    <div class="a2-auth-card a2-auth-card--luxe">

        <div class="a2-auth-top">
            <div class="a2-auth-brand-block">
                <div class="a2-auth-brand-mini">BIM Admin V2</div>
                <div class="a2-auth-brand-mini-sub">Business In Map Admin Panel</div>
            </div>

            <div class="a2-auth-logo-wrap">
                <img src="{{ asset('assets/images/logo.png') }}" alt="BIM Admin">
            </div>
        </div>

        <div class="a2-auth-head a2-auth-head--luxe">
            <h1 class="a2-auth-title a2-auth-title--luxe">تسجيل دخول الإدارة</h1>
            <p class="a2-auth-subtitle a2-auth-subtitle--luxe">
                الدخول إلى لوحة التحكم وإدارة النظام
            </p>
        </div>

        @if ($errors->any())
            <div class="a2-alert a2-alert-danger a2-auth-alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}" class="a2-auth-form a2-auth-form--luxe">
            @csrf

            <div class="a2-form-group">
                <label class="a2-label a2-auth-label">البريد الإلكتروني</label>
                <input
                    type="email"
                    name="email"
                    class="a2-input a2-auth-input a2-auth-input--luxe"
                    value="{{ old('email') }}"
                    placeholder="name@example.com"
                    required
                    autofocus
                    dir="ltr"
                >
            </div>

            <div class="a2-form-group">
                <label class="a2-label a2-auth-label">كلمة المرور</label>
                <input
                    type="password"
                    name="password"
                    class="a2-input a2-auth-input a2-auth-input--luxe"
                    placeholder="••••••••"
                    required
                >
            </div>

            <div class="a2-auth-row a2-auth-row--luxe">
                <label class="a2-auth-checkbox a2-auth-checkbox--luxe">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>تذكرني</span>
                </label>
            </div>

            <button type="submit" class="a2-btn a2-btn-primary a2-btn-block a2-auth-submit a2-auth-submit--luxe">
                دخول
            </button>
        </form>

        <div class="a2-auth-footer a2-auth-footer--luxe">
            Business In Map Admin Panel
        </div>

    </div>

</div>
@endsection
