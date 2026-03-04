{{--@extends('layouts.app')--}}

{{--@section('content')--}}
{{--<h3 class="page-title">@lang('global.roles.title')</h3>--}}


{{--<form action="{{ route('roles.update', $role->id) }}" method="post">--}}
{{--{{ csrf_field() }}--}}
{{--{{ method_field('PUT') }}--}}


{{--<div class="panel panel-default">--}}
{{--<div class="panel-heading">--}}
{{--@lang('global.app_edit')--}}
{{--</div>--}}

{{--<div class="panel-body">--}}
{{--<div class="row">--}}
{{--<div class="col-xs-12 form-group">--}}

{{--<label for="name" >Name*</label>--}}
{{--<input type="text" name="title" value="{{ $role->title  }}" class="form-control" required/>--}}
{{--<p class="help-block"></p>--}}
{{--@if($errors->has('name'))--}}
{{--<p class="help-block">--}}
{{--{{ $errors->first('name') }}--}}
{{--</p>--}}
{{--@endif--}}
{{--</div>--}}

{{--<div class="col-xs-12 form-group">--}}

{{--<label for="name" >Name*</label>--}}
{{--<input type="text" name="name" value="{{ $role->name  }}" class="form-control" required/>--}}
{{--<p class="help-block"></p>--}}
{{--@if($errors->has('title'))--}}
{{--<p class="help-block">--}}
{{--{{ $errors->first('title') }}--}}
{{--</p>--}}
{{--@endif--}}
{{--</div>--}}

{{--</div>--}}
{{--<div class="row">--}}
{{--<div class="col-xs-12 form-group">--}}

{{--<label for="abilities" >Abilities</label>--}}
{{--                    {!! Form::select('abilities[]', $abilities, old('abilities') ? old('abilities') : $role->getAbilities()->pluck('name', 'name'), ['class' => 'form-control select2', 'multiple' => 'multiple']) !!}--}}

{{--<select class="form-control" name="abilities[]" required multiple>--}}
{{--@foreach($abilities as $key => $ability)--}}
{{--<option value="{{ $ability }}">{{ $ability }}</option>--}}
{{--@endforeach--}}
{{--</select>--}}
{{--<p class="help-block"></p>--}}
{{--@if($errors->has('abilities'))--}}
{{--<p class="help-block">--}}
{{--{{ $errors->first('abilities') }}--}}
{{--</p>--}}
{{--@endif--}}
{{--</div>--}}
{{--</div>--}}

{{--</div>--}}
{{--</div>--}}


{{--<button type="submit">update</button>--}}

{{--</form>--}}
{{--@endsection--}}


@extends('admin.layouts.master')




@section('styles')


    <style>
        #parsley-id-multiple-abilities li{
            position: absolute;
            top: -22px;
            right: 80px;
        }
    </style>

@endsection


@section('content')
    <form method="POST" action="{{ route('roles.update', $role->id) }}" enctype="multipart/form-data"
          data-parsley-validate novalidate>

    {{ csrf_field() }}
    {{ method_field('PUT') }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-8 col-sm-offset-2" >
                <div class="btn-group pull-right m-t-15">
                    <a href="{{ route('roles.index') }}" type="button" class="btn btn-custom waves-effect waves-light"
                       aria-expanded="false">@lang('maincp.view_the_roles')
                        <span class="m-l-5">
                        <i class="fa fa-reply"></i>
                    </span>
                    </a>
                </div>
                <h4 class="page-title">@lang('maincp.role_management')</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 col-sm-offset-2" >
                <div class="card-box">


                    <div id="errorsHere"></div>
                    <div class="dropdown pull-right">


                    </div>

                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.edit_roles')</h4>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName"> @lang('trans.titleArabic')*</label>
                            <input type="text" name="title" value="{{ $role->title or  old('title') }}" class="form-control requiredFieldWithMaxLenght"
                                   required data-parsley-trigger="keyup"
                                   placeholder="@lang('trans.titleArabic')..."/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('title'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('title') }}
                                </p>
                            @endif
                        </div>

                    </div>



                    <div class="col-xs-12">
                        <label for="passWord2">@lang('maincp.permission') *</label>
                        <div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">
                            <ul style="list-style-type: none;">
                                <?php $i = 1; ?>
                                @foreach($abilities as  $ability)



                                    <li style="width: 32%; min-height: 190px; float: right; background: #f9f9f9; margin-left: 5px; margin-bottom: 10px; ">
                                        <input type="checkbox" {{ collect($role->abilities->pluck('id'))->contains($ability->id)  ? "checked" : ""}} name="main_abilities[]" value="{{ $ability->id }}" id="{{ $ability->name }}">
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

   
 <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>


@endsection


