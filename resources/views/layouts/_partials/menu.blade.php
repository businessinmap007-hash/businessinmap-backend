<!-- start header2 -->
<div class="header2">
    <div class="container">


        <nav class="navbar navbar-inverse">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                </div>
                <div class="collapse navbar-collapse" id="myNavbar">
                    <ul class="nav navbar-nav">
                        <li><a href="{{ route('user.home') }}"><span></span>الرئيسية</a></li>

                        @foreach($menuCategories as $category)
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle"><span></span>
                                {{ $category->name }}
                                <i class="fa fa-angle-down"></i>
                            </a>
                            <ul class="dropdown-menu row">
                                @foreach($category->children as $child)
                                <li class="col-sm-3">
                                    <ul>
                                        <li><i class="fa fa-angle-left"></i><a href="{{ route('category.products') }}?category={{$child->id}}">{{ $child->name }}</a></li>
                                    </ul>
                                </li>
                                @endforeach
                            </ul>
                        </li>
                        @endforeach

                        <li><a href="{{ route('categories') }}"><span></span>آخري</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        {{--<nav class="navbar navbar-inverse">--}}
        {{--<div class="container-fluid">--}}
        {{--<div class="navbar-header">--}}
        {{--<button type="button" class="navbar-toggle" data-toggle="collapse"--}}
        {{--data-target="#myNavbar">--}}
        {{--<span class="icon-bar"></span>--}}
        {{--<span class="icon-bar"></span>--}}
        {{--<span class="icon-bar"></span>--}}
        {{--</button>--}}
        {{--</div>--}}
        {{--<div class="collapse navbar-collapse" id="myNavbar">--}}
        {{--<ul class="nav navbar-nav">--}}
        {{--<li class="active"><a href="{{ route('user.home') }}"><span></span>الرئيسية</a></li>--}}

        {{--@foreach($menuCategories as $category)--}}
        {{--<li><a href="{{ route('category.products') }}?category={{$category->id}}"><span></span>{{ $category->name }}</a></li>--}}
        {{--@endforeach--}}

        {{--<li class="search">--}}


        {{--<form action="{{ route('category.products') }}" id="filterForm" class="navbar-form navbar-left" >--}}
        {{--<div class="input-group">--}}
        {{--<input type="text" class="form-control" name="s" value="{{ request('s') }}"--}}
        {{--placeholder="ابحث عن باسم المنتج">--}}
        {{--<div class="input-group-btn">--}}
        {{--<button class="btn btn-default" type="submit">--}}
        {{--<img src="{{ request()->root() }}/public/assets/front/img/icon4.png">--}}
        {{--</button>--}}
        {{--</div>--}}
        {{--</div>--}}
        {{--</form>--}}
        {{--</li>--}}
        {{--</ul>--}}
        {{--</div>--}}
        {{--</div>--}}
        {{--</nav>--}}
    </div>
</div>
<!-- end header2 -->