<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title','Admin Auth')</title>

    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
</head>
<body>
    @yield('content')
</body>
</html>
