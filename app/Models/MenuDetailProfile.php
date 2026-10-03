<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One kind of «منيو تفصيلي»: mobiles, computers, laptops, cars… — the set of
 * catalog attributes a product of that kind is described by. An option group
 * with no profile is a «منيو أساسي» (name, price, quantity — produce, spices).
 *
 * The profile decides three things at once, which is why it is one table and
 * not three settings: the fields «التسعير والتفاصيل» shows the merchant, the
 * spec table on the customer's product page, and the filters search offers
 * to compare one product's price across shops.
 */
class MenuDetailProfile extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'icon', 'uses_catalog', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'uses_catalog' => 'boolean',
    ];

    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class, 'menu_detail_profile_id');
    }

    /**
     * The profile's fields in display order, each with its label and unit —
     * the shape the vocabulary endpoint and the admin preview both send.
     *
     * @param  list<int>  $profileIds
     * @return array<int, list<array{id:int,code:string,name:string,data_type:string,unit:?string,show_on_card:bool,per_item:bool,is_filterable:bool,options:list<array{id:int,name:string}>}>>
     */
    public static function fieldsFor(array $profileIds, bool $english = false): array
    {
        if (empty($profileIds)) {
            return [];
        }

        $rows = DB::table('menu_detail_profile_attributes as pa')
            ->join('catalog_attributes as a', 'a.id', '=', 'pa.catalog_attribute_id')
            ->leftJoin('catalog_units as u', 'u.id', '=', 'a.unit_id')
            ->whereIn('pa.menu_detail_profile_id', $profileIds)
            ->orderBy('pa.menu_detail_profile_id')
            ->orderBy('pa.sort_order')
            ->orderBy('a.sort_order')
            ->get(['pa.menu_detail_profile_id', 'a.id', 'a.code', 'a.name_ar', 'a.name_en', 'a.data_type', 'u.name_ar as unit_ar', 'u.name_en as unit_en', 'pa.show_on_card', 'pa.per_item', 'pa.is_filterable', 'pa.show_on_page', 'pa.display']);

        // A select field (colour, gearbox, fuel) offers its options — the
        // merchant picks, search filters by them; nothing is typed.
        $options = DB::table('catalog_attribute_options')
            ->whereIn('attribute_id', $rows->where('data_type', 'select')->pluck('id')->unique()->all())
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'attribute_id', 'value_ar', 'value_en'])
            ->groupBy('attribute_id');

        return $rows
            ->groupBy('menu_detail_profile_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'code' => (string) $r->code,
                'name' => (string) ($english ? ($r->name_en ?: $r->name_ar) : ($r->name_ar ?: $r->name_en)),
                'data_type' => (string) $r->data_type,
                'unit' => ($english ? ($r->unit_en ?: $r->unit_ar) : ($r->unit_ar ?: $r->unit_en)) ?: null,
                'show_on_card' => (bool) $r->show_on_card,
                'per_item' => (bool) $r->per_item,
                'is_filterable' => (bool) $r->is_filterable,
                // On the customer's product page? And how a list field is drawn
                // for the merchant: auto | chips | dropdown.
                'show_on_page' => (bool) $r->show_on_page,
                'display' => (string) $r->display,
                'options' => ($options[$r->id] ?? collect())->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'name' => (string) ($english ? ($o->value_en ?: $o->value_ar) : ($o->value_ar ?: $o->value_en)),
                ])->values()->all(),
            ])->values()->all())
            ->all();
    }

    /**
     * The descriptive option groups each kind offers, in the admin's order —
     * profile id => [option_group_id, …]. A kind with none has no entry.
     *
     * @param  list<int>  $profileIds
     * @return array<int, list<int>>
     */
    public static function describingGroupIds(array $profileIds): array
    {
        return array_map(
            fn ($groups) => array_column($groups, 'id'),
            self::describingGroups($profileIds)
        );
    }

    /**
     * The same groups with what the admin set on each: whether it shows on the
     * customer's product page and how it is drawn for the merchant.
     *
     * @param  list<int>  $profileIds
     * @return array<int, list<array{id:int,show_on_page:bool,display:string,multiple:bool}>>
     */
    public static function describingGroups(array $profileIds): array
    {
        if (empty($profileIds)) {
            return [];
        }

        return DB::table('menu_detail_profile_option_groups')
            ->whereIn('menu_detail_profile_id', $profileIds)
            ->orderBy('menu_detail_profile_id')->orderBy('sort_order')->orderBy('id')
            ->get(['menu_detail_profile_id', 'option_group_id', 'show_on_page', 'display', 'multiple'])
            ->groupBy('menu_detail_profile_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'id' => (int) $r->option_group_id,
                'show_on_page' => (bool) $r->show_on_page,
                'display' => (string) $r->display,
                'multiple' => (bool) $r->multiple,
            ])->values()->all())
            ->all();
    }

    public function label(bool $english = false): string
    {
        return (string) ($english ? ($this->name_en ?: $this->name_ar) : ($this->name_ar ?: $this->name_en));
    }
}
