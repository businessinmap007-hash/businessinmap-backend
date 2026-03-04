@extends('admin.layouts.master')
@section('title', "إدارة المنتجات")
@section('content')


    <form method="POST" action="{{ route('users.update', $result->id) }}" enctype="multipart/form-data"
          data-parsley-validate novalidate>
    {{ csrf_field() }}
    {{ method_field('PUT') }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back') <span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>

                </div>
                <h4 class="page-title">تفاصيل المنتج</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="card-box">

                    <div class="row">

                        <div class="col-xs-12 col-lg-12">
                            <h4>بيانات المنتج</h4>
                            <hr>
                        </div>

                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>عنوان المعرض باللغة {{ $value }}</label>
                                <p>{{ $result->{'title:'.$locale} }}</p>
                            </div>
                        @endforeach

                        <hr/>

                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>وصف المعرض باللغة {{ $value }}</label>
                                <p>{{htmlspecialchars_decode(strip_tags($result->{'description:'.$locale})) }}</p>
                            </div>
                            <br/>
                        @endforeach

                        <hr/>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('trans.created_at') :</label>
                            <p>{{date('H:i:s || Y/m/d', strtotime($result->created_at))  }} </p>
                        </div>


                    </div>
                </div>

            </div>

        </div>


    </form>

@endsection

