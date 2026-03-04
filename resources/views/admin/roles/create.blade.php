@extends('admin.layouts.master')

@section('title' , __('maincp.add_roles'))


@section('styles')


    <style>
        #parsley-id-multiple-abilities li {
            position: absolute;
            top: -22px;
            right: 80px;
        }
    </style>

@endsection

@section('content')
    <form method="POST" action="{{ route('roles.store')  }}" enctype="multipart/form-data" data-parsley-validate
          novalidate>
    {{ csrf_field() }}



    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="btn-group pull-right m-t-15">


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')
                    </button>


                </div>
                <h4 class="page-title">@lang('maincp.add_roles')</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="card-box">

                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.add_roles')</h4>

                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName"> @lang('trans.titleArabic')*</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   class="form-control requiredFieldWithMaxLenght"
                                   required
                                   placeholder="@lang('trans.titleArabic')..."/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('title'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('title') }}
                                </p>
                            @endif
                        </div>

                    </div>


                    {{--<div class="col-xs-12">--}}
                    {{--<div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">--}}
                    {{--<label for="usernames">@lang('maincp.name') *</label>--}}
                    {{--<input type="text" name="name" value="{{ old('name') }}" class="form-control"--}}
                    {{--required placeholder="@lang('maincp.name')..."/>--}}
                    {{--@if($errors->has('name'))--}}
                    {{--<p class="help-block">--}}
                    {{--{{ $errors->first('name') }}--}}
                    {{--</p>--}}
                    {{--@endif--}}
                    {{--</div>--}}
                    {{--</div>--}}

                    {{--                                        {{ $abilities }}--}}
                    {{--<div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">--}}
                    {{--<label for="passWord2">@lang('maincp.permission') *</label>--}}
                    {{--<select multiple="multiple" class="multi-select" id="my_multi_select1" name="abilities[]"--}}
                    {{--required--}}
                    {{--data-plugin="multiselect">--}}
                    {{--@foreach($abilities as  $ability)--}}
                    {{--<option value="{{ $ability->name }}" {{ (collect(old('abilities'))->contains($ability->name)) ? 'selected':'' }}>@if($ability == '*')--}}
                    {{--@lang('maincp.all_permission')   @else {{ $ability->title }}@endif</option>--}}
                    {{--@endforeach--}}
                    {{--</select>--}}

                    {{--@if($errors->has('abilities'))--}}
                    {{--<p class="help-block"> {{ $errors->first('abilities') }}</p>--}}
                    {{--@endif--}}

                    {{--</div>--}}

                    <div class="row">
                        <div class="col-xs-12">
                            <label for="passWord2">@lang('maincp.permission') *</label>
                            <div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">
                                <ul style="list-style-type: none;">
                                    <?php $i = 1; ?>
                                    @foreach($abilities as  $ability)



                                        <li style="width: 33%; float: right; background: #f9f9f9;  min-height: 190px; margin-left: 0px; margin-bottom: 10px; ">

                                            <input type="checkbox" name="main_abilities[]" value="{{ $ability->id }}"
                                                   id="{{ $ability->name }}">
                                            <label for="{{ $ability->name }}">{{ $ability->title }}</label>


                                    
                                        </li>




                                        <?php $i++; ?>






                                    @endforeach
                                </ul>


                                {{--<ul>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall" id="tall">--}}
                                {{--<label for="tall">Tall Things</label>--}}

                                {{--<ul>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall-1" id="tall-1">--}}
                                {{--<label for="tall-1">Buildings</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall-2" id="tall-2">--}}
                                {{--<label for="tall-2">Giants</label>--}}

                                {{--<ul>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall-2-1" id="tall-2-1">--}}
                                {{--<label for="tall-2-1">Andre</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall-2-2" id="tall-2-2">--}}
                                {{--<label for="tall-2-2">Paul Bunyan</label>--}}
                                {{--</li>--}}
                                {{--</ul>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="tall-3" id="tall-3">--}}
                                {{--<label for="tall-3">Two sandwiches</label>--}}
                                {{--</li>--}}
                                {{--</ul>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="short" id="short">--}}
                                {{--<label for="short">Short Things</label>--}}

                                {{--<ul>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="short-1" id="short-1">--}}
                                {{--<label for="short-1">Smurfs</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="short-2" id="short-2">--}}
                                {{--<label for="short-2">Mushrooms</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input type="checkbox" name="short-3" id="short-3">--}}
                                {{--<label for="short-3">One Sandwich</label>--}}
                                {{--</li>--}}
                                {{--</ul>--}}
                                {{--</li>--}}
                                {{--</ul>--}}


                            </div>
                        </div>
                    </div>

                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                            @lang('maincp.save_data')
                        </button>
                        <button onclick="window.history.back();return false;" type="reset"
                                class="btn btn-default waves-effect waves-light m-l-5 m-t-20">
                            @lang('maincp.disable')
                        </button>
                    </div>

                </div>
            </div><!-- end col -->


            {{--<div class="col-lg-4">
                <div class="card-box" style="overflow: hidden;">
                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.personal_picture')</h4>
                    <div class="form-group">
                        <div class="col-sm-12">
                            <input type="file" name="image" class="dropify" data-max-file-size="6M"/>

                        </div>
                    </div>

                </div>
            </div>--}}
        </div>
        <!-- end row -->
    </form>
@endsection

@section('scripts')


    <script>

        @if(session()->has('error'))
        setTimeout(function () {
            showMessage('{{ session()->get('error') }}');
        }, 3000);

        @endif

        function showMessage(message) {

            var shortCutFunction = 'error';
            var msg = message;
            var title = 'خطأ!';
            toastr.options = {
                positionClass: 'toast-top-center',
                onclick: null,
                showMethod: 'slideDown',
                hideMethod: "slideUp",
            };
            var $toast = toastr[shortCutFunction](msg, title);
            // Wire up an event handler to a button in the toast, if it exists
            $toastlast = $toast;


        }


    </script>
    <script type="text/javascript">
        $("h3 input:checkbox").cbFamily(function () {
            return $(this).parents("h3").next().find("input:checkbox");
        });


    </script>

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>


@endsection
