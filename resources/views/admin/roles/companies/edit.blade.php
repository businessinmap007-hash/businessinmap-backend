@extends('admin.layouts.master')

@section('title' ,  __('maincp.add_roles'))

@section('content')
    <form method="POST" action="{{ route('roles.companies.update', $agency->id)  }}" enctype="multipart/form-data"
          data-parsley-validate
          novalidate>
    {{ csrf_field() }}




    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="btn-group pull-right m-t-15">


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>


                </div>
                <h4 class="page-title">@lang('maincp.add_roles')</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card-box">

                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.add_roles')</h4>


                    <div class="col-xs-12">
                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="usernames">@lang('maincp.name') *</label>
                            <select name="agency" class="form-control">
                                <option value="">@lang('maincp.please_select_the_type_of_establishment')</option>
                                @foreach($agencies as $agcy)
                                    <option value="{{ $agcy->id }}"
                                            @if($agency->id ==$agcy->id ) selected @endif>
                                        {{ $agcy->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($errors->has('name'))
                                <p class="help-block">
                                    {{ $errors->first('name') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    {{--                                        {{ $abilities }}--}}
                    <div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">
                        <label for="passWord2"@lang('maincp.permission')> *</label>
                        <select multiple="multiple" class="multi-select" id="my_multi_select1" name="abilities[]"
                                required
                                data-plugin="multiselect">
                            @foreach($allAbilities as  $ability)
                                <option value="{{ $ability->id }}" {{ collect($abilities->pluck('id'))->contains($ability->id)?"selected":'' }}>
                                    {{ $ability->title }}
                                </option>
                            @endforeach
                        </select>
                        @if($errors->has('abilities'))
                            <p class="help-block"> {{ $errors->first('abilities') }}</p>
                        @endif
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



@endsection
