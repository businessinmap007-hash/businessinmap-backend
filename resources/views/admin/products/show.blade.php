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
                                <label>اسم المنتج باللغة {{ $value }}</label>
                                <p>{{ $result->{'name:'.$locale} }}</p>
                            </div>
                        @endforeach


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>القسم</label>
                            <p>{{ optional($result->category)->parent_id > 0 ? optional($result->category)->name . '/'.optional($result->category->parent)->name  : optional($result->category)->name   }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>سعر المنتج:</label>
                            <p>{{ $result->price }} ريال</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>السعر بعد التخفيض:</label>
                            <p>{{ $result->price_sale }} ريال</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label> الكمية:</label>
                            <p>{{ optional($result)->quantity }} قطعة</p>
                        </div>




                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-lg-6 col-xs-12 col-md-6 col-sm-6">
                                <label>وصف المنتج باللغة {{ $value }}</label>
                                <p>{{htmlspecialchars_decode(strip_tags($result->{'description:'.$locale})) }}</p>
                            </div>
                        @endforeach





                        <div class="row">

                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>صورة المنتج:</label>
                                <img width="100%" style="height: auto; border-radius: 10px; margin-bottom: 10px"
                                     src="{{ asset('public/'.$result->image) }}">
                            </div>

                            <div class="col-lg-8 col-xs-12 col-md-6 col-sm-6">
                                <label> صور أخري للمنتج:</label>
                                <div class="clearfix"></div>
                                @foreach($result->images as $image)
                                    <div style="position: relative;margin: 1.5px; width: 24.5%;  height: 120px; float: right; ">


                                        <a data-fancybox="gallery"
                                           href="{{ $helper->getDefaultImage( asset($image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                            <img style="width: 100%; height: 100%; border-radius: 10px; margin-bottom: 10px"
                                                 src="{{ $helper->getDefaultImage( asset($image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}"/>
                                        </a>


                                    </div>
                                @endforeach
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

