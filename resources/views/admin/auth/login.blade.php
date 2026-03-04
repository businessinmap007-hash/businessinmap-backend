@extends('admin.layouts.login')


@section('styles')
    <style>
        .wrapper-page {
            margin: 3% auto;
            position: relative;
            width: 420px;
        }

        .error_validation {
            font-size: 13px;
            color: #ff5757;
        }
    </style>

@endsection
@section('content')




    <div class="m-t-40 card-box">
        <div class="text-center">
            <h4 class="text-uppercase font-bold m-b-0">   @Lang('institutioncp.login')</h4>
        </div>
        <div class="panel-body">


            @if(session()->has('errorSuspend'))
                <div class="alert alert-danger alert-dismissable">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                    <strong> {{ session()->get('errorSuspend') }}</strong>
                </div>


            @else

                @if(session()->has('error'))
                    <div class="alert alert-danger alert-dismissable">
                        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                        <strong> {{ session()->get('error') }}</strong>
                    </div>
                @endif
            @endif

            <form class="form-horizontal m-t-20" method="POST" action="{{ route('admin.login') }}">
                {{ csrf_field() }}


                <div class="form-group">
                    <div class="col-xs-12">
                        <input id="email" type="text" class="form-control" name="provider"
                               value="{{ old('provider') }}" autofocus
                               placeholder="@Lang('institutioncp.user_or_email') ...">

                        @if ($errors->has('provider'))
                            <span class="help-block error_validation" style=" font-size: 13px;color: #ff5757;">
                                <strong>{{ $errors->first('provider') }}</strong>
                            </span>
                        @endif
                    </div>
                </div>


                <div class="form-group">
                    <div class="col-xs-12">
                        <input id="password" type="password" class="form-control" name="password"
                               placeholder="  @Lang('institutioncp.password')...">

                        @if ($errors->has('password'))
                            <span class="help-block error_validation" style=" font-size: 13px;color: #ff5757;">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                        @endif
                    </div>
                </div>

                <div class="form-group ">
                    <div class="col-xs-12">
                        <div class="checkbox checkbox-custom">

                            <input id="checkbox-signup" type="checkbox"
                                   name="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label for="checkbox-signup">
                                @Lang('institutioncp.remember')
                            </label>
                        </div>

                    </div>
                </div>

                <div class="form-group text-center m-t-30">
                    <div class="col-xs-12">
                        <button class="btn btn-custom btn-bordred btn-block waves-effect waves-light" type="submit">
                            @Lang('institutioncp.login')
                        </button>
                    </div>
                </div>

                <div class="form-group m-t-30 m-b-0">
                    <div class="col-sm-12">
                        <a href="{{ route('administrator.password.request') }}" class="text-muted"><i
                                    class="fa fa-lock m-r-5"></i>
                            @Lang('institutioncp.forget_password') </a>
                    </div>
                </div>
            </form>

        </div>
    </div>
    <!-- end card-box-->




@endsection
