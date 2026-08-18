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
    'driver_children' => [169, 278, 284],

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
            // #38 «اكسسوارت سيارات» folded into #8 «اكسسوار» on 2026-08-10
            // (AccessoryMergeSeeder) and is now the option «اكسسوار سيارات».
            // #8 is deliberately NOT put in its place: a general accessories
            // shop saying which of 43 marques it stocks is noise, and the
            // option already says it deals in car accessories. Left named here,
            // this seeder would keep handing the marques to a rootless row.
            // #39 مركز سيارات · #40 ابواب سيارات · #41 كهربائي سيارات
            // #104 اصلاح زجاج السيارات · #161 كوتش — all five became BENCHES
            // inside #543 «ورشة سيارات» on 2026-08-10 (WorkshopRemodelSeeder).
            // Left named here, this seeder kept handing 43 marques to five
            // children no root can reach: a stale answer sheet waiting for
            // whoever re-attaches one.
            543, // ورشة سيارات
            42,  // زيت سيارات
            43,  // قطع غيار سيارات (مصانع)
            44,  // قطع غيار سيارات (محلات)
            // «سيارات» #53 folded into #188 on 2026-08-17 and is retired.
            169, // خدمة ليموزين — a Mercedes limo is not a Hyundai one
            188, // معرض سيارات
            /*
             * «سيارة من المالك» #549, created 2026-08-17 beside the showroom.
             *
             * It was GIVEN the 43 marques on the day it was made and never named
             * here, so this file — which is the declared authority and prunes
             * what it does not list — took all 43 straight back off on its next
             * run. The add/remove loop this taxonomy keeps producing, and the
             * reason `ChildOptionDecisionTest` went red: a seeder that grants
             * and a seeder that declares, disagreeing in the dark.
             *
             * The marque is the first word of any private listing — «هيونداي
             * النترا ٢٠١٩ من المالك» — so it belongs to this child at least as
             * plainly as to the showroom.
             */
            549, // سيارة من المالك
            249, // جنوط وكاوتش سيارات
        ],
        'motorcycle_brands' => [
            189, // معرض موتوسيكلات
        ],
        'transport_vehicles' => [
            46,  // مغسلة سيارات — which sizes it can take
            /*
             * «جراج» #119 left on 2026-08-12. It had the whole list — باص ٥٠
             * راكب، جامبو، مقطورة — which is what a HAULIER hires out; a garage
             * parks a bus, it does not hire one out. It says «خدمات الجراج
             * والانتظار» now, and the bus survives in it as «انتظار حافلات
             * ونقل», a row about the SPACE.
             *
             * Both halves, as always: narrowing it in `child_option_scopes.php`
             * alone leaves this file granting it back on the next run.
             */
            169, // خدمة ليموزين
            /*
             * «سائق» #85, added 2026-08-11. What a hired driver is booked WITH
             * is the vehicle, and this is the list that says so.
             *
             * Naming him in `child_option_scopes.php` alone was not enough and
             * the tests said so: THIS file decides who holds the group and the
             * scope file only narrows what a holder may answer. Scoped but not
             * named, he was a child with a narrowing and no grant — so the
             * seeder read his desired set as empty and deleted the five rows on
             * its next run. Both halves, or neither.
             */
            85,  // سائق
            244, // ونش إنقاذ
            278, // نقل ركاب
            284, // سيارات نقل
            68,  // شركة شحن
            198, // مكتب شحن
            243, // مندوب
            // #166 شحن بري وبحري وجوى folded into #68 on 2026-08-18.
            // #154 نقل دولي folded into #166 on 2026-08-12.
            139, // معدات ثقيلة
        ],
    ],
];
