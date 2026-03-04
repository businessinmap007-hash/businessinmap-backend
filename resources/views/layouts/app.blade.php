<html lang="@if(app()->getLocale() == 'ar') ar @else en @endif"
      dir="@if(app()->getLocale() == 'ar') rtl @else ltr @endif">
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>wonder</title>
    <meta name="description" content="">

    @include('layouts._partials.styles')

</head>
<body>

@yield('content')

@include('layouts._partials.scripts')
@include('layouts._partials.footer')



</body>
</html>