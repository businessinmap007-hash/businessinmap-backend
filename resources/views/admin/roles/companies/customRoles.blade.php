@if($type && $type == 'sub')
    @if(isset($abilities))
        @foreach($abilities as $ability)
            <div class="col-xs-3">
                <input type="checkbox" name="companyRoles[]"
                       value="{{ $ability->name }}">
                <span class="mission">{{ $ability->title }}</span>
            </div>
        @endforeach
    @else
        <p>@lang('maincp.please_select_a_major_facility_first_to_specify_permissions')</p>
    @endif
@else
    @foreach($allAbilities as $ability)
        <div class="col-xs-3">
            <input type="checkbox" {{ collect($abilities)->contains($ability)?'checked':'' }} name="companyRoles[]"
                   value="{{ $ability->id }}">
            <span class="mission">{{ $ability->title }}</span>
        </div>
    @endforeach
@endif

