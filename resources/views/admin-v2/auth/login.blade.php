@extends('admin-v2.layouts.auth')

@section('title', 'Admin Login')

@section('content')
<div class="a2-auth">

    <div class="a2-auth-card">
        
            <div class="a2-auth-logo">
                <img src="{{ asset('assets/images/logo.png') }}" alt="BIM Admin">
            </div>
        <h2 class="a2-auth-title">تسجيل دخول الإدارة</h2>

        @if ($errors->any())
            <div class="a2-alert a2-alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}" class="a2-auth-form">
            @csrf

            <div class="a2-form-group">
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="البريد الإلكتروني"
                    required
                    autofocus>
            </div>

            <div class="a2-form-group">
                <input
                    type="password"
                    name="password"
                    placeholder="كلمة المرور"
                    required>
            </div>

            <label class="a2-checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>تذكرني</span>
            </label>

            <button type="submit" class="a2-btn a2-btn--primary a2-btn--block">
                دخول
            </button>
        </form>
    </div>

</div>
@endsection
