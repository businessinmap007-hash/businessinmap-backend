@extends('layouts.master')


@section('content')





    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details">
                <h4 class="text-center">{{ getTextForAnotherLang($agent, 'name', app()->getLocale()) }}</h4>
            </div>
        </div>
    </div>
    <!-- details  -->
    <div class="container pt-5 ">
        <div class="row">
            <div class="col-xl-8 mt-5">
                <p>
                    {{  getTextForAnotherLang($agent, 'description', app()->getLocale())}}
                </p>
                <div class="details">
                    <h6 class="home-title my-4">@lang('trans.details')</h6>
                    <dl class="dl-horizontal m-b-0">


                        @if(getTextForAnotherLang($agent, 'name', app()->getLocale()))
                            <dt>
                                @lang('trans.company_name')
                            </dt>
                            <dd>
                                {{ getTextForAnotherLang($agent, 'name', app()->getLocale()) }}
                            </dd>
                        @endif


                        @if(getTextForAnotherLang($agent, 'requirements', app()->getLocale()))
                            <dt>
                                @lang('trans.agent_name')
                            </dt>
                            <dd>
                                {{ getTextForAnotherLang($agent, 'requirements', app()->getLocale()) }}
                            </dd>
                        @endif

                        @if(getTextForAnotherLang(@$agent->agentType, 'name', app()->getLocale()))
                            <dt>
                                @lang('trans.campaign_type')
                            </dt>
                            <dd>
                                @if($agent->city)
                                    {{ @getTextForAnotherLang(@$agent->agentType , 'name', app()->getLocale()) }}
                                @else
                                    -- @lang('trans.unknown')
                                @endif
                            </dd>
                        @endif


                        @if(getTextForAnotherLang(@$agent->city, 'name', app()->getLocale()))
                            <dt>
                                @lang('trans.city')
                            </dt>
                            <dd>
                                @if($agent->city)
                                    {{ @getTextForAnotherLang(@$agent->city, 'name', app()->getLocale()) }}
                                @else
                                    -- @lang('trans.unknown')
                                @endif
                            </dd>
                        @endif
                        @if(getTextForAnotherLang(@$agent->city, 'name', app()->getLocale()))
                            <dt>
                                @lang('trans.country')
                            </dt>
                            <dd>
                                @if($agent->city != "")
                                    {{ @getTextForAnotherLang(@$agent->city->country, 'name', app()->getLocale()) }}
                                @else
                                    -- @lang('trans.unknown')
                                @endif
                            </dd>
                        @endif

                        <dt>
                           @lang('trans.activity_type')
                        </dt>
                        <dd>
                            {{ $agent->permit_no  == 1 ? __('trans.haj') : __('trans.umrah')}}
                        </dd>

                        @if(getTextForAnotherLang($agent, 'address', app()->getLocale()))
                            <dt>
                                @lang('trans.location')
                            </dt>
                            <dd>
                                {{ getTextForAnotherLang($agent, 'address', app()->getLocale()) }}
                            </dd>
                        @endif

                        @if($agent->phone != "")
                            <dt>
                               @lang('trans.phone')
                            </dt>
                            <dd>
                                {{ $agent->phone }}
                            </dd>
                        @endif


                    </dl>
                </div>

            </div>


        </div>
    </div>



@endsection
