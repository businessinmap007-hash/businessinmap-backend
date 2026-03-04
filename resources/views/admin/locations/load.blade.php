<div id="load">


    <table class="table m-0">
        <thead>
        <tr>
            <th>
                <input type="checkbox"/>
            </th>
            <th>صورة المنشأة</th>
            <th>اسم المنشأة</th>
            <th>نوع المنشأة</th>
            <th>تاريخ الانشاء</th>
            <th>الخيارات</th>

        </tr>
        </thead>
        <tbody>


        @foreach($categories as $category)
            <tr>
                <td><input type="checkbox"/></td>

                <td style="width: 10%;">


                    <a data-fancybox="gallery"
                       href="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}">
                        <img style="width: 50%; border-radius: 50%; height: 49px;"
                             src="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>

                    </a>

                </td>
                <td>{{ $category->name }}</td>
                <td>{{ $category->created_at }}</td>
                <td>{{ $category->updated_at }}</td>
                <td>
                    <button class="btn btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">
                        <i class="fa fa-remove"></i>
                    </button>
                    <button class="btn btn-icon btn-sm waves-effect btn-default m-b-5">
                        <i class="fa fa-edit"></i>
                    </button>
                </td>
            </tr>
        @endforeach


        </tbody>
    </table>

    {{--@foreach($categories as $category)--}}


    {{--<div style="display: block;">--}}


    {{--<a data-fancybox="gallery"--}}
    {{--href="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
    {{--<img style="width: 20%;"--}}
    {{--src="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}

    {{--</a>--}}





    {{--{{ $category->name }}--}}

    {{--{{ $category->created_at }}--}}

    {{--{{ $category->updated_at }}--}}




    {{--<button class="btn btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">--}}
    {{--<i class="fa fa-remove"></i>--}}
    {{--</button>--}}
    {{--<button class="btn btn-icon btn-sm waves-effect btn-default m-b-5">--}}
    {{--<i class="fa fa-edit"></i>--}}
    {{--</button>--}}


    {{--</div>--}}
    {{--@endforeach--}}
</div>


{{ $categories->links() }}
