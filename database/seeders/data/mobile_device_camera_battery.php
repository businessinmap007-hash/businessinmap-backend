<?php

/*
| Cameras and battery of the device catalog — «هناك مواصفات اخرى ناقصة من
| التفاصيل وهى الكاميرات والبطارية» (المالك، 2026-10-01).
|
| name_en => [rear camera MP (main sensor), front camera MP, battery mAh].
| null = not known with confidence, and then not written at all — a blank
| row in the spec table is better than a wrong number. Apple does not publish
| battery capacity; the figures here are the ones its regulatory filings
| report, and the 2025 models are left blank until those are known.
| Applied by MobileDeviceCatalogSeeder.
*/

return [
    // Samsung — phones
    'Samsung Galaxy A05s 128GB' => [50, 13, 5000],
    'Samsung Galaxy A06 128GB' => [50, 8, 5000],
    'Samsung Galaxy A14 128GB' => [50, 13, 5000],
    'Samsung Galaxy A15 128GB' => [50, 13, 5000],
    'Samsung Galaxy A16 128GB' => [50, 13, 5000],
    'Samsung Galaxy A24 128GB' => [50, 13, 5000],
    'Samsung Galaxy A25 128GB' => [50, 13, 5000],
    'Samsung Galaxy A26 128GB' => [50, 13, 5000],
    'Samsung Galaxy A34 128GB' => [48, 13, 5000],
    'Samsung Galaxy A35 256GB' => [50, 13, 5000],
    'Samsung Galaxy A36 256GB' => [50, 12, 5000],
    'Samsung Galaxy A54 256GB' => [50, 32, 5000],
    'Samsung Galaxy A55 256GB' => [50, 32, 5000],
    'Samsung Galaxy A56 256GB' => [50, 12, 5000],
    'Samsung Galaxy M35 128GB' => [50, 13, 6000],
    'Samsung Galaxy S23 256GB' => [50, 12, 3900],
    'Samsung Galaxy S23 FE 256GB' => [50, 10, 4500],
    'Samsung Galaxy S24 256GB' => [50, 12, 4000],
    'Samsung Galaxy S24+ 256GB' => [50, 12, 4900],
    'Samsung Galaxy S24 Ultra 256GB' => [200, 12, 5000],
    'Samsung Galaxy S24 FE 256GB' => [50, 10, 4700],
    'Samsung Galaxy S25 256GB' => [50, 12, 4000],
    'Samsung Galaxy S25+ 256GB' => [50, 12, 4900],
    'Samsung Galaxy S25 Ultra 256GB' => [200, 12, 5000],
    'Samsung Galaxy S25 Edge 256GB' => [200, 12, 3900],
    'Samsung Galaxy Z Flip5 256GB' => [12, 10, 3700],
    'Samsung Galaxy Z Fold5 256GB' => [50, 10, 4400],
    'Samsung Galaxy Z Flip6 256GB' => [50, 10, 4000],
    'Samsung Galaxy Z Fold6 256GB' => [50, 10, 4400],
    'Samsung Galaxy Z Fold7 256GB' => [200, 10, 4400],
    // Samsung — tablets
    'Samsung Galaxy Tab A9 64GB' => [8, 2, 5100],
    'Samsung Galaxy Tab A9+ 64GB' => [8, 5, 7040],
    'Samsung Galaxy Tab S9 FE 128GB' => [8, 12, 8000],
    'Samsung Galaxy Tab S9 128GB' => [13, 12, 8400],
    'Samsung Galaxy Tab S10+ 256GB' => [13, 12, 10090],
    'Samsung Galaxy Tab S10 Ultra 256GB' => [13, 12, 11200],
    // Samsung — watches (no camera)
    'Samsung Galaxy Watch6 44mm' => [null, null, 425],
    'Samsung Galaxy Watch6 Classic 47mm' => [null, null, 425],
    'Samsung Galaxy Watch7 44mm' => [null, null, 425],
    'Samsung Galaxy Watch Ultra 47mm' => [null, null, 590],
    'Samsung Galaxy Watch FE 40mm' => [null, null, 247],

    // Apple
    'Apple iPhone 13 128GB' => [12, 12, 3240],
    'Apple iPhone 14 128GB' => [12, 12, 3279],
    'Apple iPhone 14 Plus 128GB' => [12, 12, 4325],
    'Apple iPhone 14 Pro 128GB' => [48, 12, 3200],
    'Apple iPhone 14 Pro Max 128GB' => [48, 12, 4323],
    'Apple iPhone 15 128GB' => [48, 12, 3349],
    'Apple iPhone 15 Plus 128GB' => [48, 12, 4383],
    'Apple iPhone 15 Pro 128GB' => [48, 12, 3274],
    'Apple iPhone 15 Pro Max 256GB' => [48, 12, 4441],
    'Apple iPhone 16e 128GB' => [48, 12, 4005],
    'Apple iPhone 16 128GB' => [48, 12, 3561],
    'Apple iPhone 16 Plus 128GB' => [48, 12, 4674],
    'Apple iPhone 16 Pro 128GB' => [48, 12, 3582],
    'Apple iPhone 16 Pro Max 256GB' => [48, 12, 4685],
    'Apple iPhone 17 256GB' => [48, 18, null],
    'Apple iPhone Air 256GB' => [48, 18, null],
    'Apple iPhone 17 Pro 256GB' => [48, 18, null],
    'Apple iPhone 17 Pro Max 256GB' => [48, 18, null],
    // Watches: no camera; battery capacity is not published by Apple.
    'Apple Watch SE (2nd gen) 44mm' => [null, null, null],
    'Apple Watch Series 9 45mm' => [null, null, null],
    'Apple Watch Series 10 46mm' => [null, null, null],
    'Apple Watch Ultra 2 49mm' => [null, null, null],
    'Apple iPad 11 (A16) 128GB' => [12, 12, null],
    'Apple iPad Air 11 (M2) 128GB' => [12, 12, null],
    'Apple iPad Air 11 (M3) 128GB' => [12, 12, null],
    'Apple iPad Pro 11 (M4) 256GB' => [12, 12, null],
    'Apple iPad mini (A17 Pro) 128GB' => [12, 12, null],

    // Xiaomi / Redmi / Poco
    'Xiaomi Redmi A3 64GB' => [8, 5, 5000],
    'Xiaomi Redmi 12 128GB' => [50, 8, 5000],
    'Xiaomi Redmi 13 128GB' => [108, 13, 5030],
    'Xiaomi Redmi 13C 128GB' => [50, 8, 5000],
    'Xiaomi Redmi 14C 128GB' => [50, 13, 5160],
    'Xiaomi Redmi Note 12 128GB' => [50, 13, 5000],
    'Xiaomi Redmi Note 13 128GB' => [108, 16, 5000],
    'Xiaomi Redmi Note 13 Pro 256GB' => [200, 16, 5100],
    'Xiaomi Redmi Note 13 Pro+ 256GB' => [200, 16, 5000],
    'Xiaomi Redmi Note 14 256GB' => [108, 20, 5500],
    'Xiaomi Redmi Note 14 Pro 256GB' => [200, 32, 5500],
    'Xiaomi Redmi Note 14 Pro+ 5G 256GB' => [50, 20, 5110],
    'Xiaomi Poco C65 128GB' => [50, 8, 5000],
    'Xiaomi Poco M6 Pro 256GB' => [64, 16, 5000],
    'Xiaomi Poco X6 256GB' => [64, 16, 5100],
    'Xiaomi Poco X6 Pro 256GB' => [64, 16, 5000],
    'Xiaomi Poco X7 256GB' => [50, 20, 5500],
    'Xiaomi Poco X7 Pro 256GB' => [50, 20, 6000],
    'Xiaomi Poco F6 256GB' => [50, 20, 5000],
    'Xiaomi 13T 256GB' => [50, 20, 5000],
    'Xiaomi 14 256GB' => [50, 32, 4610],
    'Xiaomi 14T 256GB' => [50, 32, 5000],
    'Xiaomi 14T Pro 512GB' => [50, 32, 5000],
    'Xiaomi 15 256GB' => [50, 32, 5240],
    'Xiaomi Redmi Pad SE 128GB' => [8, 5, 8000],
    'Xiaomi Redmi Pad Pro 128GB' => [8, 8, 10000],
    'Xiaomi Pad 6 128GB' => [13, 8, 8840],
    'Xiaomi Redmi Watch 4' => [null, null, 470],

    // Oppo
    'Oppo A18 128GB' => [8, 5, 5000],
    'Oppo A38 128GB' => [50, 5, 5000],
    'Oppo A58 128GB' => [50, 8, 5000],
    'Oppo A60 256GB' => [50, 8, 5000],
    'Oppo A78 128GB' => [50, 8, 5000],
    'Oppo A79 5G 256GB' => [50, 8, 5000],
    'Oppo F25 Pro 256GB' => [64, 32, 5000],
    'Oppo F27 Pro+ 256GB' => [64, 8, 5000],
    'Oppo Reno 10 256GB' => [64, 32, 5000],
    'Oppo Reno 11 256GB' => [50, 32, 5000],
    'Oppo Reno 11F 256GB' => [64, 32, 5000],
    'Oppo Reno 12 256GB' => [50, 32, 5000],
    'Oppo Reno 12F 256GB' => [50, 32, 5000],
    'Oppo Reno 13 256GB' => [50, 50, 5600],
    'Oppo Find X8 256GB' => [50, 32, 5630],

    // Realme
    'Realme C51 128GB' => [50, 5, 5000],
    'Realme C53 128GB' => [50, 8, 5000],
    'Realme C55 128GB' => [64, 8, 5000],
    'Realme C61 128GB' => [50, 5, 5000],
    'Realme C65 128GB' => [50, 8, 5000],
    'Realme C67 256GB' => [108, 8, 5000],
    'Realme C75 256GB' => [50, 8, 6000],
    'Realme Note 50 128GB' => [13, 5, 5000],
    'Realme 11 256GB' => [108, 16, 5000],
    'Realme 12 128GB' => [108, 8, 5000],
    'Realme 12+ 256GB' => [50, 16, 5000],
    'Realme 12 Pro+ 256GB' => [50, 32, 5000],
    'Realme 13+ 256GB' => [50, 16, 5000],
    'Realme GT 6 256GB' => [50, 32, 5500],
    'Realme Pad 2 128GB' => [8, 5, 8360],

    // Infinix
    'Infinix Smart 8 128GB' => [13, 8, 5000],
    'Infinix Hot 30 128GB' => [50, 8, 5000],
    'Infinix Hot 40 128GB' => [50, 32, 5000],
    'Infinix Hot 40i 128GB' => [50, 32, 5000],
    'Infinix Hot 40 Pro 256GB' => [108, 32, 5000],
    'Infinix Hot 50 256GB' => [50, 8, 5000],
    'Infinix Hot 50i 128GB' => [48, 8, 5000],
    'Infinix Hot 50 Pro+ 256GB' => [50, 13, 5000],
    'Infinix Note 30 256GB' => [64, 16, 5000],
    'Infinix Note 40 256GB' => [108, 32, 5000],
    'Infinix Note 40 Pro 256GB' => [108, 32, 5000],
    // Not confirmed yet — listed so the gap is a decision, not an oversight.
    'Infinix Note 50 256GB' => [null, null, null],
    'Infinix GT 20 Pro 256GB' => [108, 32, 5000],

    // Tecno
    'Tecno Pop 8 128GB' => [13, 8, 5000],
    'Tecno Spark 20 128GB' => [50, 32, 5000],
    'Tecno Spark 20C 128GB' => [50, 8, 5000],
    'Tecno Spark 20 Pro 256GB' => [108, 32, 5000],
    'Tecno Spark 30 256GB' => [64, 13, 5000],
    'Tecno Spark 30C 128GB' => [50, 8, 5000],
    'Tecno Spark 30 Pro 256GB' => [108, 13, 5000],
    'Tecno Camon 20 256GB' => [64, 32, 5000],
    'Tecno Camon 20 Pro 256GB' => [64, 32, 5000],
    'Tecno Camon 30 256GB' => [50, 50, 5000],
    'Tecno Pova 5 256GB' => [50, 8, 6000],
    'Tecno Pova 6 Pro 256GB' => [108, 32, 6000],

    // Honor
    'Honor X6b 128GB' => [50, 5, 5200],
    'Honor X7b 128GB' => [108, 8, 6000],
    'Honor X8b 256GB' => [108, 50, 4500],
    'Honor X9b 256GB' => [108, 16, 5800],
    'Honor X9c 256GB' => [108, 16, 6600],
    'Honor 90 256GB' => [200, 50, 5000],
    'Honor 200 256GB' => [50, 50, 5200],
    'Honor 200 Pro 512GB' => [50, 50, 5200],
    'Honor Magic6 Pro 512GB' => [50, 50, 5600],
    'Honor Pad X9 128GB' => [5, 5, 7250],

    // Vivo
    'Vivo Y03 128GB' => [13, 5, 5000],
    'Vivo Y17s 128GB' => [50, 8, 5000],
    'Vivo Y27 128GB' => [50, 8, 5000],
    'Vivo Y36 256GB' => [50, 16, 5000],
    'Vivo V29 256GB' => [50, 50, 4600],
    'Vivo V30 256GB' => [50, 50, 5000],

    // Huawei
    'Huawei Nova 11i 128GB' => [48, 16, 5000],
    'Huawei Nova 12i 128GB' => [108, 8, 5000],
    'Huawei Watch GT 4 46mm' => [null, null, null],
    'Huawei Watch Fit 3' => [null, null, null],

    // Nokia
    'Nokia C32 64GB' => [50, 8, 5000],
    'Nokia G22 128GB' => [50, 8, 5050],

    // OnePlus
    'OnePlus Nord CE4 256GB' => [50, 16, 5500],
    'OnePlus Nord 4 256GB' => [50, 16, 5500],
    'OnePlus 12 256GB' => [50, 32, 5400],
    'OnePlus 13 256GB' => [50, 32, 6000],

    // Motorola
    'Motorola Moto G54 256GB' => [50, 16, 5000],
    'Motorola Moto G84 256GB' => [50, 16, 5000],
    'Motorola Edge 50 Fusion 256GB' => [50, 32, 5000],

    // Lenovo
    'Lenovo Tab M11 128GB' => [13, 8, 7040],
];
