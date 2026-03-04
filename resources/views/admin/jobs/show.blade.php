@extends('admin.layouts.master')
@section('title', "إدارة الوظائف")
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
                <h4 class="page-title">تفاصيل الوظيفة</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="card-box">

                    <div class="row">

                        <div class="col-xs-12 col-lg-12">
                            <h4>بيانات الوظيفة</h4>
                            <hr>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>عنوان الوظيفة</label>
                            <p>{{ $result->title }}</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>القسم</label>
                            <p>{{ optional($result->category)->name }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>اسم المؤسسة</label>
                            <p>{{ $result->company }}</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>تاريخ بداية الوظيفة:</label>
                            <p>{{ $result->start_at != "" ? $result->start_at->format('Y-m-d') : "--"}}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>تاريخ نهاية الوظيفة:</label>
                            <p>{{ $result->closed_at->format('Y-m-d') }}</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>تاريخ إنشاء الوظيفة:</label>
                            <p>{{ $result->created_at->format('Y-m-d') }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>سعر التقديم:</label>
                            <p>{{ $result->price}} ريال سعودي</p>
                        </div>

                        <div class="col-lg-8 col-xs-12 col-md-8 col-sm-8">
                            <label>حالة الوظيفة:</label>

                            <p>
                                @if(\Carbon\Carbon::parse($result->start_at) > \Carbon\Carbon::now())
                                    <strong style="color: #FFA321;"> فتح باب التقديم قريباً </strong>

                                @elseif(\Carbon\Carbon::parse($result->closed_at) < \Carbon\Carbon::now())
                                    <strong style="color: #FF6E6E;"> تم الإنتهاء </strong>

                                @elseif(\Carbon\Carbon::parse($result->closed_at) > \Carbon\Carbon::now())

                                    <strong style="color: #80CBC4;">

                                        الإنتهاء
                                        خلال: {!! \Carbon\Carbon::parse($result->closed_at)->diff(\Carbon\Carbon::now())->days !!}
                                        يوم
                                    </strong>
                                @endif
                            </p>

                        </div>

                        @if($result->papers != "")
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label> الاوراق المطلوبة:</label>
                                    @foreach(explode(',', $result->papers) as $paper)
                                        <p><strong>- {{$paper}} </strong></p>
                                    @endforeach

                            </div>
                        @endif

                        <div class="col-lg-8 col-xs-12 col-md-8 col-sm-8">
                            <label> وصف الوظيفة :</label>
                            <p>{{ $result->description  }}</p>
                        </div>

                    </div>
                </div>

            </div>

        </div>


    </form>

@endsection

