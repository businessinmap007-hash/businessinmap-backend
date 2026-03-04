@extends('admin.layouts.master')
@section('title', "إدارة العروض")
@section('content')


    <form method="POST" action="{{ route('offers.update', $result->id) }}" enctype="multipart/form-data"
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
                <h4 class="page-title">تفاصيل العرض</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="card-box">

                    <div class="row">

                        <div class="col-xs-12 col-lg-12">
                            <h4>بيانات العرض</h4>
                            <hr>
                        </div>

                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>اسم العرض باللغة {{ $value }}</label>
                                <p>{{ $result->{'name:'.$locale} }}</p>
                            </div>
                        @endforeach


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>المنتج</label>
                            <p>{{ optional($result->product)->name  }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>سعر العرض:</label>
                            <p>{{ $result->price }} ريال</p>
                        </div>




                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-lg-6 col-xs-12 col-md-6 col-sm-6">
                                <label>وصف العرض باللغة {{ $value }}</label>
                                <p>{{htmlspecialchars_decode(strip_tags($result->{'description:'.$locale})) }}</p>
                            </div>
                        @endforeach





                        <div class="row">

                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>صورة العرض:</label>
                                <img width="100%" style="height: auto; border-radius: 10px; margin-bottom: 10px"
                                     src="{{ asset('public/'.$result->image) }}">
                            </div>


                        </div>


                        <div class="row">
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>@lang('trans.created_at') :</label>
                                <p>{{date('H:i:s || Y/m/d', strtotime($result->created_at))  }} </p>
                            </div>
                        </div>


                    </div>
                </div>

            </div>

        </div>


    </form>

@endsection

