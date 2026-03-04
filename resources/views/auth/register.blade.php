@extends('layouts.master')

@section('content')




    <!-- Begin products ads -->
    <section>
        <div class="container">
            <div class="row  justify-content-md-center">
                <div class="title col-12 text-center">
                    <h3>تسجيل جديد</h3>
                </div>
                <div class="col-12 col-md-6 form-card card">
                    <form class="needs-validation submission-form" novalidate method="post" action="{{ route('user.signup') }}">
                        {{ csrf_field() }}
                        <input hidden name="auth" value="{{ request('auth') }}" />
                        <div>
                            <input type="text" name="first_name" class="form-control"  placeholder="الإسم الأول "  required />
                        </div>
                        <div>
                            <input type="text" name="last_name" class="form-control"  placeholder="الإسم الأخير "  required />
                        </div>
                        <div>
                            <input type="email" name="email" class="form-control" placeholder=" البريد الإلكترونى "  required />
                        </div>
                        <div>
                            <input type="text" name="phone" class="form-control" placeholder="رقم الجوال"  required />
                        </div>
                        <div>
                            <input type="password" name="password" class="form-control" placeholder="كلمة المرور"  required />
                        </div>
                        <div>
                            <input type="password" name="confirm_password" class="form-control" placeholder="تأكيد كلمة المرور"  required />
                        </div>
                        <label for="submit" class="btn">تسجيل </label>
                        <input id="submit" class="btn btn-primary btn-lg btn-block" type="submit" />
                        <hr class="mb-4">
                    </form>
                    <div class="mt-3">هل تملك حساب .. ؟ <a href="login.html">تسجيل الدخول</a></div>
                </div>
            </div>
        </div>
    </section>
    <!-- End products -->



{{--<div class="container">--}}
    {{--<div class="row">--}}
        {{--<div class="col-md-8 col-md-offset-2">--}}
            {{--<div class="panel panel-default">--}}
                {{--<div class="panel-heading">Register</div>--}}

                {{--<div class="panel-body">--}}
                    {{--<form class="form-horizontal" method="POST" action="{{ route('register') }}">--}}
                        {{--{{ csrf_field() }}--}}

                        {{--<div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">--}}
                            {{--<label for="name" class="col-md-4 control-label">@lang('wtrans.name')</label>--}}

                            {{--<div class="col-md-6">--}}
                                {{--<input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" required autofocus>--}}

                                {{--@if ($errors->has('name'))--}}
                                    {{--<span class="help-block">--}}
                                        {{--<strong>{{ $errors->first('name') }}</strong>--}}
                                    {{--</span>--}}
                                {{--@endif--}}
                            {{--</div>--}}
                        {{--</div>--}}

                        {{--<div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">--}}
                            {{--<label for="email" class="col-md-4 control-label">@lang('wtrans.email')</label>--}}

                            {{--<div class="col-md-6">--}}
                                {{--<input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required>--}}

                                {{--@if ($errors->has('email'))--}}
                                    {{--<span class="help-block">--}}
                                        {{--<strong>{{ $errors->first('email') }}</strong>--}}
                                    {{--</span>--}}
                                {{--@endif--}}
                            {{--</div>--}}
                        {{--</div>--}}

                        {{--<div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">--}}
                            {{--<label for="password" class="col-md-4 control-label">@lang('wtrans.password')</label>--}}

                            {{--<div class="col-md-6">--}}
                                {{--<input id="password" type="password" class="form-control" name="password" required>--}}

                                {{--@if ($errors->has('password'))--}}
                                    {{--<span class="help-block">--}}
                                        {{--<strong>{{ $errors->first('password') }}</strong>--}}
                                    {{--</span>--}}
                                {{--@endif--}}
                            {{--</div>--}}
                        {{--</div>--}}

                        {{--<div class="form-group">--}}
                            {{--<label for="password-confirm" class="col-md-4 control-label">@lang('wtrans.confirm_pass')</label>--}}

                            {{--<div class="col-md-6">--}}
                                {{--<input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>--}}
                            {{--</div>--}}
                        {{--</div>--}}

                        {{--<div class="form-group">--}}
                            {{--<div class="col-md-6 col-md-offset-4">--}}
                                {{--<button type="submit" class="btn btn-primary">--}}
                                    {{--@lang('wtrans.register')--}}
                                {{--</button>--}}
                            {{--</div>--}}
                        {{--</div>--}}
                    {{--</form>--}}
                {{--</div>--}}
            {{--</div>--}}
        {{--</div>--}}
    {{--</div>--}}
{{--</div>--}}
@endsection
