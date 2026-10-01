<?php

/*
|--------------------------------------------------------------------------
| موبايلات · تابلت · ساعات ذكية — the popular models of the Egyptian market
|--------------------------------------------------------------------------
| «هل لدينا كل موديلات الموبيلات بحيث يختار منهم التاجر … يجب الفصل بين
| الموبيلات والتابلت وايضا سمارت واتش … اختار الماركة اوبو يظهر كل
| الموبايلات … لو اخترت F يظهر كل الموديلات F» — المالك، 2026-10-01.
|
| Shape: brand name_en => [
|     'ar' => brand name_ar (used only if the brand has to be created),
|     <device branch name_ar> => [ <series> => [ name_en => spec ] ],
| ]
| spec = [name_ar, chip, ram_gb, storage, screen_inches, os] — null = unknown,
| and an unknown value is simply not written (the spec table shows what
| exists). One row per model at its most-sold storage, the same grain the 21
| phones already in the catalog have.
|
| Not the whole world on day one, deliberately: the owner chose «موديلات
| حقيقية منتشرة» and a merchant who cannot find his model adds it himself
| (pending admin approval) — see BusinessMenuItemController::proposeProduct.
|
| A name_en that already exists under mobiles_accessories is UPDATED (series
| + branch), never duplicated — the first rows of each brand are the 21
| phones seeded before this file.
*/

return [

    'Samsung' => [
        'ar' => 'سامسونج',
        'موبايل' => [
            'Galaxy A' => [
                'Samsung Galaxy A05s 128GB' => ['سامسونج جالاكسي A05s 128 جيجا', 'Snapdragon 680', 4, '128GB', 6.7, 'Android 13'],
                'Samsung Galaxy A06 128GB' => ['سامسونج جالاكسي A06 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.7, 'Android 14'],
                'Samsung Galaxy A14 128GB' => ['سامسونج جالاكسي A14 128 جيجا', 'MediaTek Helio G80', 4, '128GB', 6.6, 'Android 13'],
                'Samsung Galaxy A15 128GB' => ['سامسونج جالاكسي A15 128 جيجا', 'MediaTek Helio G99', 4, '128GB', 6.5, 'Android 14'],
                'Samsung Galaxy A16 128GB' => ['سامسونج جالاكسي A16 128 جيجا', 'MediaTek Helio G99', 6, '128GB', 6.7, 'Android 14'],
                'Samsung Galaxy A24 128GB' => ['سامسونج جالاكسي A24 128 جيجا', 'MediaTek Helio G99', 6, '128GB', 6.5, 'Android 13'],
                'Samsung Galaxy A25 128GB' => ['سامسونج جالاكسي A25 128 جيجا', 'Exynos 1280', 6, '128GB', 6.5, 'Android 14'],
                'Samsung Galaxy A26 128GB' => ['سامسونج جالاكسي A26 128 جيجا', 'Exynos 1380', 6, '128GB', 6.7, 'Android 15'],
                'Samsung Galaxy A34 128GB' => ['سامسونج جالاكسي A34 128 جيجا', 'MediaTek Dimensity 1080', 8, '128GB', 6.6, 'Android 13'],
                'Samsung Galaxy A35 256GB' => ['سامسونج جالاكسي A35 256 جيجا', 'Exynos 1380', 8, '256GB', 6.6, 'Android 14'],
                'Samsung Galaxy A36 256GB' => ['سامسونج جالاكسي A36 256 جيجا', 'Snapdragon 6 Gen 3', 8, '256GB', 6.7, 'Android 15'],
                'Samsung Galaxy A54 256GB' => ['سامسونج جالاكسي A54 256 جيجا', 'Exynos 1380', 8, '256GB', 6.4, 'Android 14'],
                'Samsung Galaxy A55 256GB' => ['سامسونج جالاكسي A55 256 جيجا', 'Exynos 1480', 8, '256GB', 6.6, 'Android 14'],
                'Samsung Galaxy A56 256GB' => ['سامسونج جالاكسي A56 256 جيجا', 'Exynos 1580', 8, '256GB', 6.7, 'Android 15'],
            ],
            'Galaxy M' => [
                'Samsung Galaxy M35 128GB' => ['سامسونج جالاكسي M35 128 جيجا', 'Exynos 1380', 6, '128GB', 6.6, 'Android 14'],
            ],
            'Galaxy S' => [
                'Samsung Galaxy S23 256GB' => ['سامسونج جالاكسي S23 256 جيجا', 'Snapdragon 8 Gen 2', 8, '256GB', 6.1, 'Android 14'],
                'Samsung Galaxy S23 FE 256GB' => ['سامسونج جالاكسي S23 FE 256 جيجا', 'Exynos 2200', 8, '256GB', 6.4, 'Android 13'],
                'Samsung Galaxy S24 256GB' => ['سامسونج جالاكسي S24 256 جيجا', 'Exynos 2400', 8, '256GB', 6.2, 'Android 14'],
                'Samsung Galaxy S24+ 256GB' => ['سامسونج جالاكسي S24 بلس 256 جيجا', 'Exynos 2400', 12, '256GB', 6.7, 'Android 14'],
                'Samsung Galaxy S24 Ultra 256GB' => ['سامسونج جالاكسي S24 الترا 256 جيجا', 'Snapdragon 8 Gen 3', 12, '256GB', 6.8, 'Android 14'],
                'Samsung Galaxy S24 FE 256GB' => ['سامسونج جالاكسي S24 FE 256 جيجا', 'Exynos 2400e', 8, '256GB', 6.7, 'Android 14'],
                'Samsung Galaxy S25 256GB' => ['سامسونج جالاكسي S25 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.2, 'Android 15'],
                'Samsung Galaxy S25+ 256GB' => ['سامسونج جالاكسي S25 بلس 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.7, 'Android 15'],
                'Samsung Galaxy S25 Ultra 256GB' => ['سامسونج جالاكسي S25 الترا 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.9, 'Android 15'],
                'Samsung Galaxy S25 Edge 256GB' => ['سامسونج جالاكسي S25 إيدج 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.7, 'Android 15'],
            ],
            'Galaxy Z' => [
                'Samsung Galaxy Z Flip5 256GB' => ['سامسونج جالاكسي Z فليب 5 256 جيجا', 'Snapdragon 8 Gen 2', 8, '256GB', 6.7, 'Android 13'],
                'Samsung Galaxy Z Fold5 256GB' => ['سامسونج جالاكسي Z فولد 5 256 جيجا', 'Snapdragon 8 Gen 2', 12, '256GB', 7.6, 'Android 13'],
                'Samsung Galaxy Z Flip6 256GB' => ['سامسونج جالاكسي Z فليب 6 256 جيجا', 'Snapdragon 8 Gen 3', 12, '256GB', 6.7, 'Android 14'],
                'Samsung Galaxy Z Fold6 256GB' => ['سامسونج جالاكسي Z فولد 6 256 جيجا', 'Snapdragon 8 Gen 3', 12, '256GB', 7.6, 'Android 14'],
                'Samsung Galaxy Z Fold7 256GB' => ['سامسونج جالاكسي Z فولد 7 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 8.0, 'Android 16'],
            ],
        ],
        'تابلت' => [
            'Galaxy Tab A' => [
                'Samsung Galaxy Tab A9 64GB' => ['سامسونج جالاكسي تاب A9 64 جيجا', 'MediaTek Helio G99', 4, '64GB', 8.7, 'Android 13'],
                'Samsung Galaxy Tab A9+ 64GB' => ['سامسونج جالاكسي تاب A9 بلس 64 جيجا', 'Snapdragon 695', 4, '64GB', 11.0, 'Android 13'],
            ],
            'Galaxy Tab S' => [
                'Samsung Galaxy Tab S9 FE 128GB' => ['سامسونج جالاكسي تاب S9 FE 128 جيجا', 'Exynos 1380', 6, '128GB', 10.9, 'Android 13'],
                'Samsung Galaxy Tab S9 128GB' => ['سامسونج جالاكسي تاب S9 128 جيجا', 'Snapdragon 8 Gen 2', 8, '128GB', 11.0, 'Android 13'],
                'Samsung Galaxy Tab S10+ 256GB' => ['سامسونج جالاكسي تاب S10 بلس 256 جيجا', 'MediaTek Dimensity 9300+', 12, '256GB', 12.4, 'Android 14'],
                'Samsung Galaxy Tab S10 Ultra 256GB' => ['سامسونج جالاكسي تاب S10 الترا 256 جيجا', 'MediaTek Dimensity 9300+', 12, '256GB', 14.6, 'Android 14'],
            ],
        ],
        'ساعة ذكية' => [
            'Galaxy Watch' => [
                'Samsung Galaxy Watch6 44mm' => ['سامسونج جالاكسي ووتش 6 44 مم', 'Exynos W930', 2, '16GB', 1.47, 'Wear OS 4'],
                'Samsung Galaxy Watch6 Classic 47mm' => ['سامسونج جالاكسي ووتش 6 كلاسيك 47 مم', 'Exynos W930', 2, '16GB', 1.47, 'Wear OS 4'],
                'Samsung Galaxy Watch7 44mm' => ['سامسونج جالاكسي ووتش 7 44 مم', 'Exynos W1000', 2, '32GB', 1.47, 'Wear OS 5'],
                'Samsung Galaxy Watch Ultra 47mm' => ['سامسونج جالاكسي ووتش الترا 47 مم', 'Exynos W1000', 2, '32GB', 1.47, 'Wear OS 5'],
                'Samsung Galaxy Watch FE 40mm' => ['سامسونج جالاكسي ووتش FE 40 مم', 'Exynos W920', 1.5, '16GB', 1.2, 'Wear OS 5'],
            ],
        ],
    ],

    'Apple' => [
        'ar' => 'ابل',
        'موبايل' => [
            'iPhone 13' => [
                'Apple iPhone 13 128GB' => ['ابل ايفون 13 128 جيجا', 'Apple A15 Bionic', 4, '128GB', 6.1, 'iOS 17'],
            ],
            'iPhone 14' => [
                'Apple iPhone 14 128GB' => ['ابل ايفون 14 128 جيجا', 'Apple A15 Bionic', 6, '128GB', 6.1, 'iOS 16'],
                'Apple iPhone 14 Plus 128GB' => ['ابل ايفون 14 بلس 128 جيجا', 'Apple A15 Bionic', 6, '128GB', 6.7, 'iOS 16'],
                'Apple iPhone 14 Pro 128GB' => ['ابل ايفون 14 برو 128 جيجا', 'Apple A16 Bionic', 6, '128GB', 6.1, 'iOS 16'],
                'Apple iPhone 14 Pro Max 128GB' => ['ابل ايفون 14 برو ماكس 128 جيجا', 'Apple A16 Bionic', 6, '128GB', 6.7, 'iOS 16'],
            ],
            'iPhone 15' => [
                'Apple iPhone 15 128GB' => ['ابل ايفون 15 128 جيجا', 'Apple A16 Bionic', 6, '128GB', 6.1, 'iOS 17'],
                'Apple iPhone 15 Plus 128GB' => ['ابل ايفون 15 بلس 128 جيجا', 'Apple A16 Bionic', 6, '128GB', 6.7, 'iOS 17'],
                'Apple iPhone 15 Pro 128GB' => ['ابل ايفون 15 برو 128 جيجا', 'Apple A17 Pro', 8, '128GB', 6.1, 'iOS 17'],
                'Apple iPhone 15 Pro Max 256GB' => ['ابل ايفون 15 برو ماكس 256 جيجا', 'Apple A17 Pro', 8, '256GB', 6.7, 'iOS 17'],
            ],
            'iPhone 16' => [
                'Apple iPhone 16e 128GB' => ['ابل ايفون 16e 128 جيجا', 'Apple A18', 8, '128GB', 6.1, 'iOS 18'],
                'Apple iPhone 16 128GB' => ['ابل ايفون 16 128 جيجا', 'Apple A18', 8, '128GB', 6.1, 'iOS 18'],
                'Apple iPhone 16 Plus 128GB' => ['ابل ايفون 16 بلس 128 جيجا', 'Apple A18', 8, '128GB', 6.7, 'iOS 18'],
                'Apple iPhone 16 Pro 128GB' => ['ابل ايفون 16 برو 128 جيجا', 'Apple A18 Pro', 8, '128GB', 6.3, 'iOS 18'],
                'Apple iPhone 16 Pro Max 256GB' => ['ابل ايفون 16 برو ماكس 256 جيجا', 'Apple A18 Pro', 8, '256GB', 6.9, 'iOS 18'],
            ],
            'iPhone 17' => [
                'Apple iPhone 17 256GB' => ['ابل ايفون 17 256 جيجا', 'Apple A19', 8, '256GB', 6.3, 'iOS 26'],
                'Apple iPhone Air 256GB' => ['ابل ايفون اير 256 جيجا', 'Apple A19 Pro', 12, '256GB', 6.5, 'iOS 26'],
                'Apple iPhone 17 Pro 256GB' => ['ابل ايفون 17 برو 256 جيجا', 'Apple A19 Pro', 12, '256GB', 6.3, 'iOS 26'],
                'Apple iPhone 17 Pro Max 256GB' => ['ابل ايفون 17 برو ماكس 256 جيجا', 'Apple A19 Pro', 12, '256GB', 6.9, 'iOS 26'],
            ],
        ],
        'تابلت' => [
            'iPad' => [
                'Apple iPad 11 (A16) 128GB' => ['ابل ايباد 11 (A16) 128 جيجا', 'Apple A16', 6, '128GB', 11.0, 'iPadOS 18'],
            ],
            'iPad Air' => [
                'Apple iPad Air 11 (M2) 128GB' => ['ابل ايباد اير 11 (M2) 128 جيجا', 'Apple M2', 8, '128GB', 11.0, 'iPadOS 17'],
                'Apple iPad Air 11 (M3) 128GB' => ['ابل ايباد اير 11 (M3) 128 جيجا', 'Apple M3', 8, '128GB', 11.0, 'iPadOS 18'],
            ],
            'iPad Pro' => [
                'Apple iPad Pro 11 (M4) 256GB' => ['ابل ايباد برو 11 (M4) 256 جيجا', 'Apple M4', 8, '256GB', 11.0, 'iPadOS 17'],
            ],
            'iPad mini' => [
                'Apple iPad mini (A17 Pro) 128GB' => ['ابل ايباد ميني (A17 Pro) 128 جيجا', 'Apple A17 Pro', 8, '128GB', 8.3, 'iPadOS 18'],
            ],
        ],
        'ساعة ذكية' => [
            'Apple Watch SE' => [
                'Apple Watch SE (2nd gen) 44mm' => ['ابل ووتش SE الجيل الثاني 44 مم', 'Apple S8', null, '32GB', null, 'watchOS'],
            ],
            'Apple Watch Series' => [
                'Apple Watch Series 9 45mm' => ['ابل ووتش سيريس 9 45 مم', 'Apple S9', null, '64GB', null, 'watchOS 10'],
                'Apple Watch Series 10 46mm' => ['ابل ووتش سيريس 10 46 مم', 'Apple S10', null, '64GB', null, 'watchOS 11'],
            ],
            'Apple Watch Ultra' => [
                'Apple Watch Ultra 2 49mm' => ['ابل ووتش الترا 2 49 مم', 'Apple S9', null, '64GB', null, 'watchOS 10'],
            ],
        ],
    ],

    'Xiaomi' => [
        'ar' => 'شاومي',
        'موبايل' => [
            'Redmi' => [
                'Xiaomi Redmi A3 64GB' => ['شاومي ريدمي A3 64 جيجا', 'MediaTek Helio G36', 3, '64GB', 6.71, 'Android 14'],
                'Xiaomi Redmi 12 128GB' => ['شاومي ريدمي 12 128 جيجا', 'MediaTek Helio G88', 8, '128GB', 6.79, 'Android 13'],
                'Xiaomi Redmi 13 128GB' => ['شاومي ريدمي 13 128 جيجا', 'MediaTek Helio G91 Ultra', 8, '128GB', 6.79, 'Android 14'],
                'Xiaomi Redmi 13C 128GB' => ['شاومي ريدمي 13C 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.74, 'Android 13'],
                'Xiaomi Redmi 14C 128GB' => ['شاومي ريدمي 14C 128 جيجا', 'MediaTek Helio G81 Ultra', 4, '128GB', 6.88, 'Android 14'],
            ],
            'Redmi Note' => [
                'Xiaomi Redmi Note 12 128GB' => ['شاومي ريدمي نوت 12 128 جيجا', 'Snapdragon 685', 6, '128GB', 6.67, 'Android 13'],
                'Xiaomi Redmi Note 13 128GB' => ['شاومي ريدمي نوت 13 128 جيجا', 'Snapdragon 685', 6, '128GB', 6.67, 'Android 13'],
                'Xiaomi Redmi Note 13 Pro 256GB' => ['شاومي ريدمي نوت 13 برو 256 جيجا', 'Snapdragon 7s Gen 2', 8, '256GB', 6.67, 'Android 13'],
                'Xiaomi Redmi Note 13 Pro+ 256GB' => ['شاومي ريدمي نوت 13 برو بلس 256 جيجا', 'MediaTek Dimensity 7200 Ultra', 12, '256GB', 6.67, 'Android 13'],
                'Xiaomi Redmi Note 14 256GB' => ['شاومي ريدمي نوت 14 256 جيجا', 'MediaTek Helio G99 Ultra', 8, '256GB', 6.67, 'Android 14'],
                'Xiaomi Redmi Note 14 Pro 256GB' => ['شاومي ريدمي نوت 14 برو 256 جيجا', 'MediaTek Helio G100 Ultra', 8, '256GB', 6.67, 'Android 14'],
                'Xiaomi Redmi Note 14 Pro+ 5G 256GB' => ['شاومي ريدمي نوت 14 برو بلس 5G 256 جيجا', 'Snapdragon 7s Gen 3', 12, '256GB', 6.67, 'Android 14'],
            ],
            'Poco' => [
                'Xiaomi Poco C65 128GB' => ['شاومي بوكو C65 128 جيجا', 'MediaTek Helio G85', 6, '128GB', 6.74, 'Android 14'],
                'Xiaomi Poco M6 Pro 256GB' => ['شاومي بوكو M6 برو 256 جيجا', 'MediaTek Helio G99 Ultra', 8, '256GB', 6.67, 'Android 14'],
                'Xiaomi Poco X6 256GB' => ['شاومي بوكو X6 256 جيجا', 'Snapdragon 7s Gen 2', 8, '256GB', 6.67, 'Android 13'],
                'Xiaomi Poco X6 Pro 256GB' => ['شاومي بوكو X6 برو 256 جيجا', 'MediaTek Dimensity 8300 Ultra', 8, '256GB', 6.67, 'Android 14'],
                'Xiaomi Poco X7 256GB' => ['شاومي بوكو X7 256 جيجا', 'MediaTek Dimensity 7300 Ultra', 8, '256GB', 6.67, 'Android 14'],
                'Xiaomi Poco X7 Pro 256GB' => ['شاومي بوكو X7 برو 256 جيجا', 'MediaTek Dimensity 8400 Ultra', 8, '256GB', 6.67, 'Android 15'],
                'Xiaomi Poco F6 256GB' => ['شاومي بوكو F6 256 جيجا', 'Snapdragon 8s Gen 3', 8, '256GB', 6.67, 'Android 14'],
            ],
            'Xiaomi' => [
                'Xiaomi 13T 256GB' => ['شاومي 13T 256 جيجا', 'MediaTek Dimensity 8200 Ultra', 12, '256GB', 6.67, 'Android 13'],
                'Xiaomi 14 256GB' => ['شاومي 14 256 جيجا', 'Snapdragon 8 Gen 3', 12, '256GB', 6.36, 'Android 14'],
                'Xiaomi 14T 256GB' => ['شاومي 14T 256 جيجا', 'MediaTek Dimensity 8300 Ultra', 12, '256GB', 6.67, 'Android 14'],
                'Xiaomi 14T Pro 512GB' => ['شاومي 14T برو 512 جيجا', 'MediaTek Dimensity 9300+', 12, '512GB', 6.67, 'Android 14'],
                'Xiaomi 15 256GB' => ['شاومي 15 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.36, 'Android 15'],
            ],
        ],
        'تابلت' => [
            'Redmi Pad' => [
                'Xiaomi Redmi Pad SE 128GB' => ['شاومي ريدمي باد SE 128 جيجا', 'Snapdragon 680', 4, '128GB', 11.0, 'Android 13'],
                'Xiaomi Redmi Pad Pro 128GB' => ['شاومي ريدمي باد برو 128 جيجا', 'Snapdragon 7s Gen 2', 6, '128GB', 12.1, 'Android 14'],
            ],
            'Xiaomi Pad' => [
                'Xiaomi Pad 6 128GB' => ['شاومي باد 6 128 جيجا', 'Snapdragon 870', 6, '128GB', 11.0, 'Android 13'],
            ],
        ],
        'ساعة ذكية' => [
            'Redmi Watch' => [
                'Xiaomi Redmi Watch 4' => ['شاومي ريدمي ووتش 4', null, null, null, 1.97, 'HyperOS'],
            ],
        ],
    ],

    'Oppo' => [
        'ar' => 'اوبو',
        'موبايل' => [
            'A' => [
                'Oppo A18 128GB' => ['اوبو A18 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.56, 'Android 14'],
                'Oppo A38 128GB' => ['اوبو A38 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.56, 'Android 13'],
                'Oppo A58 128GB' => ['اوبو A58 128 جيجا', 'MediaTek Helio G85', 6, '128GB', 6.72, 'Android 13'],
                'Oppo A60 256GB' => ['اوبو A60 256 جيجا', 'Snapdragon 680', 8, '256GB', 6.67, 'Android 14'],
                'Oppo A78 128GB' => ['اوبو A78 128 جيجا', 'Snapdragon 680', 8, '128GB', 6.56, 'Android 13'],
                'Oppo A79 5G 256GB' => ['اوبو A79 5G 256 جيجا', 'MediaTek Dimensity 6020', 8, '256GB', 6.72, 'Android 13'],
            ],
            'F' => [
                'Oppo F25 Pro 256GB' => ['اوبو F25 برو 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 14'],
                'Oppo F27 Pro+ 256GB' => ['اوبو F27 برو بلس 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 14'],
            ],
            'Reno' => [
                'Oppo Reno 10 256GB' => ['اوبو رينو 10 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 13'],
                'Oppo Reno 11 256GB' => ['اوبو رينو 11 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 14'],
                'Oppo Reno 11F 256GB' => ['اوبو رينو 11F 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.7, 'Android 14'],
                'Oppo Reno 12 256GB' => ['اوبو رينو 12 256 جيجا', 'MediaTek Dimensity 7300 Energy', 12, '256GB', 6.7, 'Android 14'],
                'Oppo Reno 12F 256GB' => ['اوبو رينو 12F 256 جيجا', 'MediaTek Dimensity 6300', 8, '256GB', 6.67, 'Android 14'],
                'Oppo Reno 13 256GB' => ['اوبو رينو 13 256 جيجا', 'MediaTek Dimensity 8350', 12, '256GB', 6.59, 'Android 15'],
            ],
            'Find' => [
                'Oppo Find X8 256GB' => ['اوبو فايند X8 256 جيجا', 'MediaTek Dimensity 9400', 12, '256GB', 6.59, 'Android 15'],
            ],
        ],
    ],

    'Realme' => [
        'ar' => 'ريلمي',
        'موبايل' => [
            'C' => [
                'Realme C51 128GB' => ['ريلمي C51 128 جيجا', 'Unisoc T612', 4, '128GB', 6.74, 'Android 13'],
                'Realme C53 128GB' => ['ريلمي C53 128 جيجا', 'Unisoc T612', 6, '128GB', 6.74, 'Android 13'],
                'Realme C55 128GB' => ['ريلمي C55 128 جيجا', 'MediaTek Helio G88', 6, '128GB', 6.72, 'Android 13'],
                'Realme C61 128GB' => ['ريلمي C61 128 جيجا', 'Unisoc T612', 6, '128GB', 6.74, 'Android 14'],
                'Realme C65 128GB' => ['ريلمي C65 128 جيجا', 'MediaTek Helio G85', 6, '128GB', 6.67, 'Android 14'],
                'Realme C67 256GB' => ['ريلمي C67 256 جيجا', 'Snapdragon 685', 8, '256GB', 6.72, 'Android 14'],
                'Realme C75 256GB' => ['ريلمي C75 256 جيجا', 'MediaTek Helio G92 Max', 8, '256GB', 6.72, 'Android 14'],
            ],
            'Note' => [
                'Realme Note 50 128GB' => ['ريلمي نوت 50 128 جيجا', 'Unisoc T612', 4, '128GB', 6.74, 'Android 13'],
            ],
            'Realme' => [
                'Realme 11 256GB' => ['ريلمي 11 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.4, 'Android 13'],
                'Realme 12 128GB' => ['ريلمي 12 128 جيجا', 'MediaTek Dimensity 6100+', 8, '128GB', 6.67, 'Android 14'],
                'Realme 12+ 256GB' => ['ريلمي 12 بلس 256 جيجا', 'MediaTek Dimensity 7050', 8, '256GB', 6.67, 'Android 14'],
                'Realme 12 Pro+ 256GB' => ['ريلمي 12 برو بلس 256 جيجا', 'Snapdragon 7s Gen 2', 12, '256GB', 6.7, 'Android 14'],
                'Realme 13+ 256GB' => ['ريلمي 13 بلس 256 جيجا', 'MediaTek Dimensity 7300 Energy', 8, '256GB', 6.67, 'Android 14'],
            ],
            'GT' => [
                'Realme GT 6 256GB' => ['ريلمي GT 6 256 جيجا', 'Snapdragon 8s Gen 3', 12, '256GB', 6.78, 'Android 14'],
            ],
        ],
        'تابلت' => [
            'Realme Pad' => [
                'Realme Pad 2 128GB' => ['ريلمي باد 2 128 جيجا', 'MediaTek Helio G99', 6, '128GB', 11.5, 'Android 13'],
            ],
        ],
    ],

    'Infinix' => [
        'ar' => 'انفينكس',
        'موبايل' => [
            'Smart' => [
                'Infinix Smart 8 128GB' => ['انفينكس سمارت 8 128 جيجا', 'Unisoc T606', 4, '128GB', 6.6, 'Android 13 Go'],
            ],
            'Hot' => [
                'Infinix Hot 30 128GB' => ['انفينكس هوت 30 128 جيجا', 'MediaTek Helio G88', 8, '128GB', 6.78, 'Android 13'],
                'Infinix Hot 40 128GB' => ['انفينكس هوت 40 128 جيجا', 'MediaTek Helio G88', 8, '128GB', 6.78, 'Android 13'],
                'Infinix Hot 40i 128GB' => ['انفينكس هوت 40i 128 جيجا', 'Unisoc T606', 8, '128GB', 6.56, 'Android 13'],
                'Infinix Hot 40 Pro 256GB' => ['انفينكس هوت 40 برو 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.78, 'Android 13'],
                'Infinix Hot 50 256GB' => ['انفينكس هوت 50 256 جيجا', 'MediaTek Helio G100', 8, '256GB', 6.78, 'Android 14'],
                'Infinix Hot 50i 128GB' => ['انفينكس هوت 50i 128 جيجا', 'MediaTek Helio G81', 4, '128GB', 6.7, 'Android 14'],
                'Infinix Hot 50 Pro+ 256GB' => ['انفينكس هوت 50 برو بلس 256 جيجا', 'MediaTek Helio G100', 8, '256GB', 6.78, 'Android 14'],
            ],
            'Note' => [
                'Infinix Note 30 256GB' => ['انفينكس نوت 30 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.78, 'Android 13'],
                'Infinix Note 40 256GB' => ['انفينكس نوت 40 256 جيجا', 'MediaTek Helio G99 Ultimate', 8, '256GB', 6.78, 'Android 14'],
                'Infinix Note 40 Pro 256GB' => ['انفينكس نوت 40 برو 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.78, 'Android 14'],
                'Infinix Note 50 256GB' => ['انفينكس نوت 50 256 جيجا', 'MediaTek Helio G100 Ultimate', 8, '256GB', 6.78, 'Android 15'],
            ],
            'GT' => [
                'Infinix GT 20 Pro 256GB' => ['انفينكس GT 20 برو 256 جيجا', 'MediaTek Dimensity 8200 Ultimate', 12, '256GB', 6.78, 'Android 14'],
            ],
        ],
    ],

    'Tecno' => [
        'ar' => 'تكنو',
        'موبايل' => [
            'Pop' => [
                'Tecno Pop 8 128GB' => ['تكنو بوب 8 128 جيجا', 'Unisoc T606', 4, '128GB', 6.6, 'Android 13 Go'],
            ],
            'Spark' => [
                'Tecno Spark 20 128GB' => ['تكنو سبارك 20 128 جيجا', 'MediaTek Helio G85', 8, '128GB', 6.6, 'Android 13'],
                'Tecno Spark 20C 128GB' => ['تكنو سبارك 20C 128 جيجا', 'MediaTek Helio G36', 4, '128GB', 6.6, 'Android 13'],
                'Tecno Spark 20 Pro 256GB' => ['تكنو سبارك 20 برو 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.78, 'Android 13'],
                'Tecno Spark 30 256GB' => ['تكنو سبارك 30 256 جيجا', 'MediaTek Helio G91', 8, '256GB', 6.78, 'Android 14'],
                'Tecno Spark 30C 128GB' => ['تكنو سبارك 30C 128 جيجا', 'MediaTek Helio G81', 4, '128GB', 6.67, 'Android 14'],
                'Tecno Spark 30 Pro 256GB' => ['تكنو سبارك 30 برو 256 جيجا', 'MediaTek Helio G100', 8, '256GB', 6.78, 'Android 14'],
            ],
            'Camon' => [
                'Tecno Camon 20 256GB' => ['تكنو كامون 20 256 جيجا', 'MediaTek Helio G85', 8, '256GB', 6.67, 'Android 13'],
                'Tecno Camon 20 Pro 256GB' => ['تكنو كامون 20 برو 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.67, 'Android 13'],
                'Tecno Camon 30 256GB' => ['تكنو كامون 30 256 جيجا', 'MediaTek Helio G99 Ultimate', 8, '256GB', 6.78, 'Android 14'],
            ],
            'Pova' => [
                'Tecno Pova 5 256GB' => ['تكنو بوفا 5 256 جيجا', 'MediaTek Helio G99', 8, '256GB', 6.78, 'Android 13'],
                'Tecno Pova 6 Pro 256GB' => ['تكنو بوفا 6 برو 256 جيجا', 'MediaTek Dimensity 6080', 8, '256GB', 6.78, 'Android 14'],
            ],
        ],
    ],

    'Honor' => [
        'ar' => 'هونر',
        'موبايل' => [
            'X' => [
                'Honor X6b 128GB' => ['هونر X6b 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.56, 'Android 13'],
                'Honor X7b 128GB' => ['هونر X7b 128 جيجا', 'Snapdragon 680', 6, '128GB', 6.8, 'Android 13'],
                'Honor X8b 256GB' => ['هونر X8b 256 جيجا', 'Snapdragon 680', 8, '256GB', 6.7, 'Android 13'],
                'Honor X9b 256GB' => ['هونر X9b 256 جيجا', 'Snapdragon 6 Gen 1', 8, '256GB', 6.78, 'Android 13'],
                'Honor X9c 256GB' => ['هونر X9c 256 جيجا', 'Snapdragon 6 Gen 1', 12, '256GB', 6.78, 'Android 14'],
            ],
            'Honor' => [
                'Honor 90 256GB' => ['هونر 90 256 جيجا', 'Snapdragon 7 Gen 1 Accelerated', 8, '256GB', 6.7, 'Android 13'],
                'Honor 200 256GB' => ['هونر 200 256 جيجا', 'Snapdragon 7 Gen 3', 12, '256GB', 6.7, 'Android 14'],
                'Honor 200 Pro 512GB' => ['هونر 200 برو 512 جيجا', 'Snapdragon 8s Gen 3', 12, '512GB', 6.78, 'Android 14'],
            ],
            'Magic' => [
                'Honor Magic6 Pro 512GB' => ['هونر ماجيك 6 برو 512 جيجا', 'Snapdragon 8 Gen 3', 12, '512GB', 6.8, 'Android 14'],
            ],
        ],
        'تابلت' => [
            'Pad X' => [
                'Honor Pad X9 128GB' => ['هونر باد X9 128 جيجا', 'Snapdragon 685', 4, '128GB', 11.5, 'Android 13'],
            ],
        ],
    ],

    'Vivo' => [
        'ar' => 'فيفو',
        'موبايل' => [
            'Y' => [
                'Vivo Y03 128GB' => ['فيفو Y03 128 جيجا', 'MediaTek Helio G85', 4, '128GB', 6.56, 'Android 14'],
                'Vivo Y17s 128GB' => ['فيفو Y17s 128 جيجا', 'MediaTek Helio G85', 6, '128GB', 6.56, 'Android 13'],
                'Vivo Y27 128GB' => ['فيفو Y27 128 جيجا', 'MediaTek Helio G85', 6, '128GB', 6.64, 'Android 13'],
                'Vivo Y36 256GB' => ['فيفو Y36 256 جيجا', 'Snapdragon 680', 8, '256GB', 6.64, 'Android 13'],
            ],
            'V' => [
                'Vivo V29 256GB' => ['فيفو V29 256 جيجا', 'Snapdragon 778G', 12, '256GB', 6.78, 'Android 13'],
                'Vivo V30 256GB' => ['فيفو V30 256 جيجا', 'Snapdragon 7 Gen 3', 12, '256GB', 6.78, 'Android 14'],
            ],
        ],
    ],

    'Huawei' => [
        'ar' => 'هواوي',
        'موبايل' => [
            'Nova' => [
                'Huawei Nova 11i 128GB' => ['هواوي نوفا 11i 128 جيجا', 'Snapdragon 680', 8, '128GB', 6.8, 'EMUI 13'],
                'Huawei Nova 12i 128GB' => ['هواوي نوفا 12i 128 جيجا', 'Snapdragon 680', 8, '128GB', 6.7, 'EMUI 14'],
            ],
        ],
        'ساعة ذكية' => [
            'Watch GT' => [
                'Huawei Watch GT 4 46mm' => ['هواوي ووتش GT 4 46 مم', null, null, null, 1.43, 'HarmonyOS'],
            ],
            'Watch Fit' => [
                'Huawei Watch Fit 3' => ['هواوي ووتش فيت 3', null, null, null, 1.82, 'HarmonyOS'],
            ],
        ],
    ],

    'Nokia' => [
        'ar' => 'نوكيا',
        'موبايل' => [
            'C' => [
                'Nokia C32 64GB' => ['نوكيا C32 64 جيجا', 'Unisoc SC9863A', 4, '64GB', 6.5, 'Android 13'],
            ],
            'G' => [
                'Nokia G22 128GB' => ['نوكيا G22 128 جيجا', 'Unisoc T606', 4, '128GB', 6.52, 'Android 12'],
            ],
        ],
    ],

    'OnePlus' => [
        'ar' => 'ون بلس',
        'موبايل' => [
            'Nord' => [
                'OnePlus Nord CE4 256GB' => ['ون بلس نورد CE4 256 جيجا', 'Snapdragon 7 Gen 3', 8, '256GB', 6.7, 'Android 14'],
                'OnePlus Nord 4 256GB' => ['ون بلس نورد 4 256 جيجا', 'Snapdragon 7+ Gen 3', 12, '256GB', 6.74, 'Android 14'],
            ],
            'OnePlus' => [
                'OnePlus 12 256GB' => ['ون بلس 12 256 جيجا', 'Snapdragon 8 Gen 3', 12, '256GB', 6.82, 'Android 14'],
                'OnePlus 13 256GB' => ['ون بلس 13 256 جيجا', 'Snapdragon 8 Elite', 12, '256GB', 6.82, 'Android 15'],
            ],
        ],
    ],

    'Motorola' => [
        'ar' => 'موتورولا',
        'موبايل' => [
            'Moto G' => [
                'Motorola Moto G54 256GB' => ['موتورولا موتو G54 256 جيجا', 'MediaTek Dimensity 7020', 8, '256GB', 6.5, 'Android 13'],
                'Motorola Moto G84 256GB' => ['موتورولا موتو G84 256 جيجا', 'Snapdragon 695', 12, '256GB', 6.55, 'Android 13'],
            ],
            'Edge' => [
                'Motorola Edge 50 Fusion 256GB' => ['موتورولا ايدج 50 فيوجن 256 جيجا', 'Snapdragon 7s Gen 2', 8, '256GB', 6.7, 'Android 14'],
            ],
        ],
    ],

    'Lenovo' => [
        'ar' => 'لينوفو',
        'تابلت' => [
            'Tab M' => [
                'Lenovo Tab M11 128GB' => ['لينوفو تاب M11 128 جيجا', 'MediaTek Helio G88', 4, '128GB', 10.95, 'Android 13'],
            ],
        ],
    ],

    /*
    | The five accessories already in the catalog, filed under their branch
    | so the «شواحن وكابلات» picker stops offering phones. No spec, no series.
    */
    'Anker' => [
        'ar' => 'انكر',
        'شواحن وكابلات' => [
            '' => [
                'Anker Fast Charger 20W' => null,
                'Anker Type-C Cable 1m' => null,
            ],
        ],
        'باور بانك' => [
            '' => ['Anker Power Bank 10000mAh' => null],
        ],
    ],
    '' => [
        'جرابات وكفرات' => ['' => ['Silicone Phone Case' => null]],
        'اسكرين وحماية' => ['' => ['Tempered Glass Screen Protector' => null]],
    ],
];
