<?php

/*
|--------------------------------------------------------------------------
| Real-estate taxonomy — the same three-axis remodel as Health, with one
| deliberate difference
|--------------------------------------------------------------------------
| Root 18 «عقارات و أراضي» had 12 children, but 10 of them were PROPERTY TYPES
| (شقة، ڤيلا، محل، أرض، مصنع، ورشة، مزرعة، عمارة، معرض، أرض زراعية) rather than
| business types — the same disease Health had. Only تسويق عقاري (16 accounts)
| and مكتب were real businesses.
|
| THE DIFFERENCE FROM HEALTH: a medical specialty merely DESCRIBES a clinic, so
| it became an option. A property type is the thing that is actually listed,
| rented and PRICED — «الإيجار يسجل ما بها كوحدة متاحة للإيجار» — so by the same
| price test it belongs on axis 3 (platform item type / bookable unit), not on
| the option axis. Booking's `real_estate` branch already held four of them
| (شقة/فيلا/استوديو/شاليه), which is where the rest now join.
|
|   who the business IS       → child   : مكتب عقاري، تسويق عقاري، مطور عقاري
|   what DESCRIBES the deal   → option  : بيع/إيجار/تقسيط/كاش (already group #9)
|   what is listed AND PRICED → item type: the property types below
|
| Consumed by RealEstateRemodelSeeder.
*/

return [

    // ── Axis 1. تسويق عقاري already exists and is correct. مكتب عقاري is new:
    // the existing «مكتب» child CANNOT be reused because it is shared with root
    // 5 «شحن وتوصيل», where 14 delivery companies sit on it — only the 4
    // real-estate accounts move onto the new child.
    // مالك عقار is the owner listing their own unit with no broker in between —
    // the «من المالك» side of the market that buyers filter for explicitly.
    // Named to match its siblings' profession-noun pattern (مالك عقاري would be
    // poor Arabic).
    'children' => [
        ['name_ar' => 'مكتب عقاري',  'name_en' => 'Real Estate Office',   'existing' => false],
        ['name_ar' => 'تسويق عقاري', 'name_en' => 'Real Estate Marketing','existing' => true],
        ['name_ar' => 'مطور عقاري',  'name_en' => 'Real Estate Developer','existing' => false],
        ['name_ar' => 'مالك عقار',   'name_en' => 'Property Owner',       'existing' => false],
    ],

    // ── The shared child that must NOT be detached from root 18 wholesale.
    // Only the accounts whose `category_id` is 18 move; the delivery ones stay.
    'shared_child' => [
        'name_ar' => 'مكتب',
        'other_root_id' => 5,
        'move_to' => 'مكتب عقاري',
    ],

    // ── Axis 3: the property types, filed into booking's existing
    // `real_estate` branch. Keys that already exist there (apartment, villa,
    // studio, chalet) are reused, never duplicated.
    'branch_key' => 'real_estate',

    'property_types' => [
        'apartment' => ['شقة', 'Apartment'],
        'villa' => ['ڤيلا', 'Villa'],
        'studio' => ['استوديو', 'Studio'],
        'chalet' => ['شاليه', 'Chalet'],
        'building' => ['عمارة', 'Building'],
        'shop_unit' => ['محل', 'Shop'],
        'office_unit' => ['مكتب', 'Office'],
        'land' => ['أرض', 'Land'],
        'agricultural_land' => ['أرض زراعية', 'Agricultural Land'],
        'farm' => ['مزرعة', 'Farm'],
        'factory_unit' => ['مصنع', 'Factory'],
        'workshop_unit' => ['ورشة', 'Workshop'],
        'showroom_unit' => ['معرض', 'Showroom'],
        'warehouse_unit' => ['مخزن', 'Warehouse'],
    ],

    // ── The property-type children detached from root 18. Master rows are kept
    // (several are shared with other roots, e.g. معرض/مصنع/ورشة under معارض and
    // محلات), so this only ever removes the root-18 link.
    'detach_children' => [
        'شقة', 'ڤيلا', 'عمارة', 'محل', 'أرض', 'أرض زراعية',
        'مزرعة', 'مصنع', 'ورشة', 'معرض',
    ],

    // ── Where accounts sitting on a detached property-type child land.
    'business_migration_target' => 'مكتب عقاري',
];
