@php
    $selectedSurfaces = (array) ($row->surfaces ?? []);
@endphp
<tr>
    <td>
        <input type="hidden" name="rows[{{ $i }}][option_group_id]" value="{{ $group->id }}">
        <select class="a2-select" name="rows[{{ $i }}][item_type_key]">
            <option value="">{{ __('كل البنود') }}</option>
            @foreach($itemTypes as $type)
                <option value="{{ $type->key }}" @selected(($row->item_type_key ?? '') === $type->key)>{{ $name($type) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        @foreach($surfaceLabels as $key => $label)
            <label style="display:block;white-space:nowrap">
                <input type="checkbox" name="rows[{{ $i }}][surfaces][]" value="{{ $key }}" @checked(in_array($key, $selectedSurfaces, true))>
                {{ __($label) }}
            </label>
        @endforeach
    </td>
    <td>
        <select class="a2-select" name="rows[{{ $i }}][usage]">
            <option value="">{{ __('— غير معرَّفة —') }}</option>
            @foreach($usageLabels as $key => $label)
                <option value="{{ $key }}" @selected(($row->usage ?? '') === $key)>{{ __($label) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select class="a2-select" name="rows[{{ $i }}][input_type]">
            @foreach($inputLabels as $key => $label)
                <option value="{{ $key }}" @selected(($row->input_type ?? 'single') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="hidden" name="rows[{{ $i }}][is_required]" value="0">
        <input type="checkbox" name="rows[{{ $i }}][is_required]" value="1" @checked($row->is_required ?? false)>
    </td>
    <td>
        <input type="hidden" name="rows[{{ $i }}][is_active]" value="0">
        <input type="checkbox" name="rows[{{ $i }}][is_active]" value="1" @checked($row->is_active ?? true)>
    </td>
</tr>
