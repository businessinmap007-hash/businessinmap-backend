@extends('layouts.master')


@section('styles')
@endsection
@section('content')

    <!-- Main Content-->
    <main class="main-content">
        <!--products -->
        <section id="categories">
            <div class="container">
                <div class="main">
                    <h3 class="title"> جميع الأقسام </h3>
                    <div class="row">
                        <div class="col-xs-12">
                            <!-- item -->
                            @foreach($allCategories as $category)
                                <div class="item">
                                    <h3 class="item-title">
                                        <a href="#"> {{ $category->name }} </a>
                                    </h3>
                                    <div class="item-wrapper">
                                        <ul class="row">
                                            @foreach($category->children as $child)
                                                <li class="col-xs-12 col-md-3">
                                                    <a href="#"> {{ $child->name }} </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <!-- item -->
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- End Main Content-->

@endsection
@section('scripts')
@endsection
