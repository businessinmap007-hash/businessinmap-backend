<?php

/**
 * «مركبات ونقل» split by what the option actually is.
 *
 * The group held 68 rows of three different kinds at once — car marques,
 * motorcycle marques, and vehicle body types — and all 68 were handed to all 20
 * children that carry it. A motorcycle showroom was offered Bentley and Porsche;
 * a limousine service was offered Kawasaki; a parking garage was offered the
 * entire marque list of the Egyptian market.
 *
 * Three groups, plus two rows that were never vehicles at all and one pair that
 * belongs on a different axis entirely.
 */
return [

    'groups' => [
        'car_brands' => [
            'name_ar' => 'ماركات السيارات',
            'name_en' => 'Car Brands',
            'reorder' => 20,
            'options' => [
                26, 35, 41, 44, 48, 54, 69, 74, 75, 139, 142, 151, 172, 179, 185,
                193, 201, 210, 212, 216, 222, 225, 228, 237, 239, 245, 247, 249,
                252, 263, 268, 277, 291, 298, 301, 318, 320, 335, 348, 351, 361,
                374, 375,
            ],
        ],
        'motorcycle_brands' => [
            'name_ar' => 'ماركات الموتوسيكلات',
            'name_en' => 'Motorcycle Brands',
            'reorder' => 21,
            'options' => [40, 116, 215, 221, 229, 354, 389, 260],
        ],
        'transport_vehicles' => [
            'name_ar' => 'مركبات النقل والركاب',
            'name_en' => 'Transport & Passenger Vehicles',
            'reorder' => 22,
            'options' => [51, 214, 220, 248, 250, 251, 280, 281, 365, 184],
        ],
    ],

    /**
     * Honda and Suzuki build both, and an option row carries a single group, so
     * the motorcycle side needs its own row. `options.name_en` is globally
     * unique, which the suffix also satisfies.
     */
    'motorcycle_twins' => [
        ['name_ar' => 'هوندا موتوسيكلات', 'name_en' => 'Honda Motorcycles'],
        ['name_ar' => 'سوزوكي موتوسيكلات', 'name_en' => 'Suzuki Motorcycles'],
    ],

    /**
     * «سيارة بسائق» / «سيارة بدون سائق» describe HOW the car is supplied, not
     * which car it is — the same axis as فردي / فريق عمل / أونلاين.
     */
    'move_to_service_mode' => [62, 63],

    /** Only the trades that actually supply a car either way face that choice. */
    'driver_children' => [169, 278, 298, 284],

    /**
     * - «سيارة» and «سيارات» say nothing inside a group already called vehicles.
     * - «اكسسوارات سيارات» and «قطع غيار سيارات» are product categories that
     *   already exist as CHILDREN (#38, #44) — an option must not restate a child.
     * - «آ11 / i11» names no vehicle anyone could pick knowingly.
     */
    'retire' => [57, 65, 58, 60, 194],

    /*
    |--------------------------------------------------------------------------
    | Which children face which list
    |--------------------------------------------------------------------------
    | Keyed by child id, since a vehicle child means the same trade under every
    | root it sits beneath.
    */
    'children' => [
        'car_brands' => [
            38,  // اكسسوارت سيارات
            39,  // مركز سيارات
            40,  // ابواب سيارات
            41,  // كهربائي سيارات
            42,  // زيت سيارات
            43,  // قطع غيار سيارات (مصانع)
            44,  // قطع غيار سيارات (محلات)
            53,  // سيارات
            104, // اصلاح زجاج السيارات
            161, // كوتش
            169, // خدمة ليموزين — a Mercedes limo is not a Hyundai one
            188, // معرض سيارات
            249, // جنوط وكاوتش سيارات
        ],
        'motorcycle_brands' => [
            189, // معرض موتوسيكلات
        ],
        'transport_vehicles' => [
            46,  // مغسلة سيارات — which sizes it can take
            119, // جراج
            169, // خدمة ليموزين
            244, // ونش إنقاذ
            278, // نقل ركاب
            284, // سيارات نقل
            298, // التاكسي الأبيض
            68,  // شركة شحن
            198, // مكتب شحن
            243, // مندوب
            166, // شحن بري وبحري وجوى
            154, // نقل دولي
            139, // معدات ثقيلة
        ],
    ],
];
