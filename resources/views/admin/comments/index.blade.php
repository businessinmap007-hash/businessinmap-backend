@extends('admin.layouts.master')

@section('title', "إدارة التعليقات")
@section('styles')

    <!-- Custom box css -->
    <link href="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.css" rel="stylesheet">

    <style>
        .errorValidationReason {
            border: 1px solid red;
        }
    </style>
@endsection
@section('content')

    <!-- Page-Title -->
    <div class="row">
        <div class="col-xs-8 col-xs-offset-2">

            <h4 class="page-title">
                إدارة التعليقات
            </h4>
        </div>
    </div>


    <div class="row">
        <div class="col-xs-8 col-xs-offset-2">
            <div class="card-box table-responsive">

                <div class="dropdown pull-right">
                    {{--<a href="#" class="dropdown-toggle card-drop" data-toggle="dropdown" aria-expanded="false">--}}
                    {{--<i class="zmdi zmdi-more-vert"></i> --}}
                    {{--</a>--}}

                </div>

                <h4 class="header-title m-t-0 m-b-30">
                    قائمة التعليقات
                </h4>


                <div class="card-box">
                    <div class="comment">
                        <img src="{{ asset('public/'.optional($post->user)->image) }}" alt="" class="comment-avatar">
                        <div class="comment-body">
                            <div class="comment-text">
                                <div class="comment-header">
                                    <a href="#"
                                       title=""> {{ optional($post->user)->name }} </a><span> {{ $post->created_at != "" ? $post->created_at->diffForHumans() : "--" }} </span>
                                </div>
                                {{ $post->body }}

                                <div class="m-t-15">


                                    @if($post->images->count() > 0)
                                        @foreach($post->images as $image)
                                            <a data-fancybox="gallery"
                                               href="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                                <img class="thumb-md"
                                                     style="width: 35px; border-radius: 50%; height: 35px; border: 1px dotted #000;"
                                                     src="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}"/>
                                            </a>
                                        @endforeach
                                    @else
                                        <strong style="font-size: 12px;">لا يوجد صور</strong>
                                    @endif

                                </div>
                            </div>

                            <div class="comment-footer">
                                <a href="javascript:;"><i class="fa fa-thumbs-o-up"></i> ({{ $post->likes->count() }}
                                    )</a>
                                <a href="javascript:;"><i class="fa fa-thumbs-o-down"></i>
                                    ({{ $post->dislikes->count() }})</a>
                                <a href="javascript:;"><i class="fa fa-arrow-circle-o-up"></i> ({{ $post->share_count }}
                                    )</a>
                            </div>
                        </div>


                        @forelse($post->comments as $comment)


                            <div class="comment">
                                <img src="{{ asset('public/'.optional($comment->user)->image) }}" alt=""
                                     class="comment-avatar">
                                <div class="comment-body">
                                    <div class="comment-text">
                                        <div class="comment-header">
                                            <a href="#" title="">{{ $comment->user->name }} </a>
                                            <span>{{ $comment->created_at != "" ? $comment->created_at->diffForHumans() : "-- " }}

                                               <label class="label label-info">
                                                   {{ $comment->type == 'public' ? "عام" : "خاص"}}
                                               </label>
                                            </span>
                                        </div>
                                        {{ $comment->comment }}
                                    </div>

                                </div>


                                @foreach($comment->children as $child)
                                    <div class="comment">
                                        <img src="{{ asset('public/'.optional($comment->user)->image) }}" alt=""
                                             class="comment-avatar">
                                        <div class="comment-body">
                                            <div class="comment-text">
                                                <div class="comment-header">
                                                    <a href="#" title="">{{ $comment->user->name }} </a><span>{{ $child->created_at != "" ? $child->created_at->diffForHumans() : "--" }} </span>
                                                </div>
                                                {{ $child->comment }}
                                            </div>

                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty

                            <strong>No Comments</strong>
                        @endforelse
                    </div>


                </div>


            </div>
        </div><!-- end col -->
    </div>
    <!-- end row -->


@endsection


@section('scripts')



@endsection



