<?php

/**
 * The children that sell by LISTING a thing, and what kind of thing it is.
 *
 * `menu_items` is the platform's listing surface — a row with a name, a
 * description, images and a price — but the `menu` service it belongs to was
 * only ever wired to food: 19 children, all restaurants and groceries, and
 * every one of its item types is a food branch.
 *
 * So an estate agent listing «شقة — غرفتين — سوبر لوكس» and a showroom listing
 * «غرفة نوم — مودرن» were creating listings the taxonomy did not know they
 * could make. It worked, because nothing gates menu_items on the service, but
 * it worked by accident: no config said so, no branch grouped them, and
 * anything that reads the catalogue to decide what a business may sell saw
 * nothing at all.
 *
 * Each family gets its own item type rather than sharing one «listing», because
 * `allowed_item_types` is how a child says what it may put up, and a car
 * showroom offering «وحدة عقارية» would be nonsense on the merchant's screen.
 *
 * Shape: item type key => [name_ar, name_en, [child ids]].
 * Applied by \Database\Seeders\ListingServiceLinkSeeder to EVERY root each
 * child sits under — a workshop lists what it makes as surely as a showroom
 * lists what it sells.
 */
return [

    'branch' => ['key' => 'listings', 'name_ar' => 'المعروضات', 'name_en' => 'Listings'],

    'types' => [
        // عقارات و أراضي — the family that started this
        'property_listing' => [
            'name_ar' => 'وحدة معروضة',
            'name_en' => 'Property Listing',
            'children' => [
                517,  // مكتب عقاري
                518,  // مطور عقاري
                522,  // مالك عقار
                238,  // تسويق عقاري
            ],
        ],

        // معارض — a car is never two of a kind, so it is a listing, not a
        // catalogue product
        'vehicle_listing' => [
            'name_ar' => 'مركبة معروضة',
            'name_en' => 'Vehicle Listing',
            'children' => [
                53,   // سيارات
                188,  // معرض سيارات
                189,  // معرض موتوسيكلات
            ],
        ],

        // أثاث — 93 businesses on «آثاث» alone, the largest single child on the
        // platform, and bespoke work by nature
        'furniture_piece' => [
            'name_ar' => 'قطعة أثاث',
            'name_en' => 'Furniture Piece',
            'children' => [
                116,  // آثاث
                115,  // مفروشات
                57,   // نجف و تحف
            ],
        ],
    ],
];
