<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Auth')</title>

    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">

    @yield('head')
    @stack('styles')
</head>

<body class="admin-v2 admin-v2-auth @yield('body_class')">
    @yield('content')

    @stack('scripts')
</body>
</html>