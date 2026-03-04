<div class="collapse navbar-collapse" id="myNavbar">
    <ul class="nav navbar-nav">

        {{-- HOME --}}
        <li>
            <a href="{{ Route::has('user.home') ? route('user.home') : '#' }}">
                الرئيسية
            </a>
        </li>

        {{-- CATEGORIES --}}
        @foreach($menuCategories as $category)
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    {{ $category->name }}
                    <i class="fa fa-angle-down"></i>
                </a>

                <ul class="dropdown-menu row">
                    @foreach($category->children as $child)
                        <li class="col-sm-3">
                            <ul>
                                <li>
                                    <a href="{{ Route::has('category.products')
                                        ? route('category.products', ['category' => $child->id])
                                        : '#' }}">
                                        <i class="fa fa-angle-left"></i>
                                        {{ $child->name }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach

        {{-- ALL CATEGORIES --}}
        <li>
            <a href="{{ Route::has('categories') ? route('categories') : '#' }}">
                التصنيفات
            </a>
        </li>

    </ul>
</div>
