<option value=""> @lang('maincp.status') </option>
<option @if(request($status) == 'all') selected @endif value="all">@lang('maincp.all') </option>


<option @if(request($status) != 'all' && request($status) != '' && request($status) == config('constants.order.under_pricing')) selected
        @endif value="{{ config('constants.order.under_pricing') }}">@lang('maincp.currently_being_priced') 
</option>

<option @if(request($status) == config('constants.order.order_priced')) selected
        @endif value="{{ config('constants.order.order_priced') }}">@lang('maincp.priced') 
</option>


<option @if(request($status) == config('constants.order.order_waiting')) selected
        @endif value="{{ config('constants.order.order_waiting') }}">@lang('maincp.ongoing') 
</option>

<option @if(request($status) == config('constants.order.order_undershipping')) selected
        @endif value="{{ config('constants.order.order_undershipping') }}">@lang('maincp.shipped') 
</option>

<option @if(request($status) == config('constants.order.order_unavailable')) selected
        @endif value="{{ config('constants.order.order_unavailable') }}">@lang('maincp.unavailable') 
</option>


<option @if(request($status) == config('constants.order.order_delay')) selected
        @endif value="{{ config('constants.order.order_delay') }}">@lang('maincp.deferred') 
</option>


<option @if(request($status) == config('constants.order.order_uncomplete')) selected
        @endif value="{{ config('constants.order.order_uncomplete') }}">@lang('maincp.incomplete') 
</option>


<option @if(request($status) == config('constants.order.order_compelete')) selected
        @endif value="{{ config('constants.order.order_compelete') }}">@lang('maincp.completed') 
</option>

