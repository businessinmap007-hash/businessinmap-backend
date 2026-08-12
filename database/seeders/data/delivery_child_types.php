<?php

/*
|--------------------------------------------------------------------------
| Delivery narrowing — which of its branch's mechanisms a trade may offer
|--------------------------------------------------------------------------
| Same rule as data/retail_child_types.php, one service across: a branch is
| expanded WHOLE into allowed_item_types, which is right until two trades
| share it.
|
| Two shapes are supported.
|
| `__only_for` — a TYPE that belongs to named trades and to nobody else. Read
| it as «this mechanism may only be offered by these». It is the inverse of a
| per-child list and it is the right shape here, because the exceptions are
| few and the rule is «everybody else gets the generic ones»: naming the
| twenty-five trades that keep a specialised mechanism is honest, naming the
| hundred-odd that do not is a list nobody will maintain.
|
| Everything else — a plain [trade => types] entry, intersected with the
| branch, exactly as retail does it.
|
| Neither may empty a child. An empty allowed_item_types reads as EVERY type
| (BoundUnboundedConfigsSeeder), so a restriction that leaves nothing keeps
| the whole branch and warns instead.
*/

return [

    /*
    | «نقل عينات طبية» sat on `delivery_coldchain` beside «توصيل مبرّد» and
    | «نقل مجمّد», and the branch went out whole to twenty-seven food trades.
    | A vegetable seller, a poultry farm, a livestock company and a plant
    | nursery were each offered medical sample transport — while «معمل
    | تحاليل», the one business on the platform that actually moves samples,
    | had no delivery configuration at all. It has one now, and so do the
    | hospital and the medical centre; the food trades keep the cold chain
    | they do need.
    |
    | «توصيل مطعم»، «توصيل سوبر ماركت» and «توصيل صيدلية» are the same shape
    | on the generic `delivery` branch, which carries three neutral mechanisms
    | and three trade-named ones and goes out whole to seventy-four children.
    | A gold shop and a bookshop were each being offered pharmacy delivery and
    | restaurant delivery.
    */
    /*
    | Note what is NOT in the two medical lists: «معمل تحاليل», «صيدلية»,
    | «مستشفى», «عيادة». Every child of «الصحة» is booking-only — none has a
    | menu or a retail surface — so BookingWithoutDeliverySeeder took delivery
    | off all seven by the owner's rule «حجز بدون توصيل هو حجز وقت او مدة فلا
    | نستخدم خدمة التوصيل». They cannot hold a delivery mechanism at all.
    |
    | Which leaves «توصيل صيدلية» offered by seventy-four businesses, not one
    | of them a pharmacy. Restricting it here fixes the seventy-four; whether a
    | pharmacy should be able to sell and deliver at all is a separate question
    | and his to answer.
    */
    '__only_for' => [

        'medical_sample_courier' => [
            'مستلزمات طبية', 'مواد دوائية',
            // The couriers themselves carry for all of them.
            'مندوب', 'مكتب', 'شركة',
        ],

        'pharmacy_delivery' => [
            'مستلزمات طبية', 'مواد دوائية', 'مكملات غذائية',
            'مندوب', 'مكتب', 'شركة',
        ],

        'restaurant_delivery' => [
            'مطعم', 'مطعم وكافيه', 'كافيه', 'مجمع مطاعم', 'أكل بيتى',
            'عربية قهوة ومأكولات',
            // The owner's kitchens: «المخابز والحلويات مطابخ» and «عصائر مطبخ».
            'مخابز', 'حلويات', 'عصائر',
            'مستلزمات مطاعم', 'مستلزمات كافيهات', 'مستلزمات قهاوى',
            'مندوب', 'مكتب', 'شركة',
        ],

        'grocery_delivery' => [
            'سوبر ماركت', 'مني ماركت', 'هايبر ماركت',
            'بن', 'منظفات', 'أسماك', 'مجمدات', 'خضار وفاكهة', 'دواجن',
            'حبوب وغلال', 'مواشي وأرانب', 'مزارع سمكية',
            'مواد غذائية', 'مواد غذائية ومنظفات',
            'مندوب', 'مكتب', 'شركة',
        ],
    ],
];
