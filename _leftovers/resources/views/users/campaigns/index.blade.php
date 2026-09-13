@extends('layouts.master')


@section('content')



<div class="hamalat-header text-center text-white">
    <div class="overlay">
        <div class="w-50 m-auto header-details">
            <h4 class="text-center mb-4">@lang('trans.campaigns') {{ getTextForAnotherLang($campaignType, 'name',app()->getLocale())  }}</h4>

            <p>{{ getTextForAnotherLang($campaignType, 'description',app()->getLocale()) }}</p>

        </div>

    </div>
</div>

@if(count($campaignType->campaigns->where('is_suspend', 0)) > 0)

<!-- internal hajj  -->
<div class="container pt-5 internal-hajj">
    <div>


        <ul class="row pt-5 text-center">


            <?php $i = 1; ?>

            <?php $count = 0; ?>

            @foreach($campaignType->campaigns->where('is_suspend', 0) as $key => $campaign)



            <li class="col-xl-3 col-md-4 col-sm-6 mb-4">
                <a class="redirectToDetailsIfLoggedin" data-url="{{ route('campaign.details', $campaign->id) }}" href="javascript:;">
                    <div class="card">


                        @if (count($campaign->files) > 0)
                        <img class="campaign-img m-0" src="{{ $helper->getDefaultImage( config('constants.options.image_url') . $campaign->files[0]->url, request()->root().'/public/assets/admin/images/about_img.jpg')  }}" alt="صورة الحملة">
                        @endif

                        <div class="card-body" data-maxlength="60">
                            <h5 class="campaign-title text-dark">{{anotherLangWhenDefaultNotFound($campaign, 'name')}} </h5>
                            <h6 class="campaign-loc">
                                @if($campaign->city_id)
                                {{ $campaign->city ? $campaign->city->country->name .' - '. $campaign->city->name : "--" }}
                                @endif

                            </h6>
                            <p class="card-text">
                                {{ substr( getTextForAnotherLang($campaignType, 'description',app()->getLocale()), 0, 30) }}
                            </p>

                        </div>
                    </div>
                </a>
            </li>

            @if($i%8== 0)
            @if(isset($ads[$count]))
            <div class="col-xl-12 mb-4">
                <img src="{{  config('constants.options.image_url') . $ads[$count]->image }}" class="w-100">
            </div>
            @endif
            <?php $count++; ?>
            @endif
            <?php $i++; ?>
            @endforeach
        </ul>

    </div>
</div>
@else
{{ noResults() }}
@endif

@endsection


@section('scripts')
<script>
    $(".redirectToDetailsIfLoggedin").on('click', function() {
        var isLogin = "{{ auth()->check() }}";
        var targetUrl = $(this).attr('data-url');
        if (isLogin) {
            window.location.href = targetUrl;
        } else {
            showMessageError("{{ __('trans.shoud_be_login') }}");
        }
    });

    function showMessageError(message) {
        var shortCutFunction = 'error';
        var msg = message;
        var title = "@lang('institutioncp.error')";
        toastr.options = {
            positionClass: 'toast-top-left',
            onclick: null
        };
        var $toast = toastr[shortCutFunction](msg, title);
        // Wire up an event handler to a button in the toast, if it exists
        $toastlast = $toast;
    }
</script>

@endsection