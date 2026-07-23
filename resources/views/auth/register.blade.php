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

                    {{-- Path chooser: pick which kind of account to create. The
                         choice sets the hidden `auth` field and reveals the
                         business-only fields. Defaults to the value already in
                         the URL (?auth=business) if present. --}}
                    @php $preselect = request('auth') === 'business' || request('auth') === 'vendor' ? 'business' : 'client'; @endphp
                    <div class="account-path btn-group btn-group-toggle d-flex mb-4" role="group">
                        <button type="button" class="btn btn-outline-primary flex-fill {{ $preselect === 'client' ? 'active' : '' }}" data-path="client">
                            حساب مستخدم
                        </button>
                        <button type="button" class="btn btn-outline-primary flex-fill {{ $preselect === 'business' ? 'active' : '' }}" data-path="business">
                            حساب بزنس
                        </button>
                    </div>

                    <form class="needs-validation submission-form" novalidate method="post" action="{{ route('user.signup') }}">
                        {{ csrf_field() }}
                        <input type="hidden" name="auth" id="auth-path" value="{{ $preselect }}" />
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

                        {{-- Business-only: sector → business type. category_child_id
                             is what makes the merchant account work (its service
                             catalog), so it is required on this path. --}}
                        <div class="business-fields" style="{{ $preselect === 'business' ? '' : 'display:none' }}">
                            <div>
                                <select name="category_id" id="sector-select" class="form-control">
                                    <option value="">اختر القطاع</option>
                                    @foreach($sectors as $sector)
                                        <option value="{{ $sector->id }}">{{ $sector->name_ar ?: $sector->name_en }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select name="category_child_id" id="child-select" class="form-control">
                                    <option value="">اختر نوع النشاط</option>
                                </select>
                            </div>
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
                    <div class="mt-3">هل تملك حساب .. ؟ <a href="{{ route('get.user.login') }}">تسجيل الدخول</a></div>
                </div>

                @php
                    $sectorChildren = $sectors->mapWithKeys(fn($s) => [
                        $s->id => $s->children->map(fn($c) => ['id' => $c->id, 'name' => $c->name_ar ?: $c->name_en])->values(),
                    ]);
                @endphp
                <script>
                    (function () {
                        var childrenBySector = @json($sectorChildren);
                        var authPath   = document.getElementById('auth-path');
                        var bizFields  = document.querySelector('.business-fields');
                        var sectorSel  = document.getElementById('sector-select');
                        var childSel   = document.getElementById('child-select');

                        function setPath(path) {
                            var isBiz = path === 'business';
                            authPath.value = path;
                            bizFields.style.display = isBiz ? '' : 'none';
                            // Only require the business selects while on that path.
                            sectorSel.required = isBiz;
                            childSel.required = isBiz;
                            document.querySelectorAll('.account-path [data-path]').forEach(function (b) {
                                b.classList.toggle('active', b.getAttribute('data-path') === path);
                            });
                        }

                        document.querySelectorAll('.account-path [data-path]').forEach(function (btn) {
                            btn.addEventListener('click', function () { setPath(btn.getAttribute('data-path')); });
                        });

                        sectorSel.addEventListener('change', function () {
                            var list = childrenBySector[sectorSel.value] || [];
                            childSel.innerHTML = '<option value="">اختر نوع النشاط</option>';
                            list.forEach(function (c) {
                                var o = document.createElement('option');
                                o.value = c.id; o.textContent = c.name;
                                childSel.appendChild(o);
                            });
                        });

                        // Honour the initial path (e.g. ?auth=business).
                        setPath(authPath.value === 'business' ? 'business' : 'client');
                    })();
                </script>
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
