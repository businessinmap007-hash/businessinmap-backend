<?php

/**
 * The head noun of a product name → the department it belongs on.
 *
 * «الاسم التجارى والنوع والحجم فقط» — and the owner is right that the TYPE is
 * enough to shelve a thing. A «لبن» goes on the milk shelf whatever Open Food
 * Facts does or does not say about it, and 721 Egyptian rows carry a brand and
 * a recognisable type while carrying **no category at all**. Those were being
 * refused for want of a fact the name already states.
 *
 * This is consulted only AFTER `off_department_map.php` finds nothing: what the
 * source actually declares still outranks what the name implies.
 *
 * Only nouns whose shelf is not in doubt are listed. «مشروب» and «صلصة» are
 * deliberately absent — a drink may be a juice or a soda, and a sauce may be a
 * condiment or a tin, and a name alone does not say which. Those rows keep
 * waiting for a category.
 *
 * Keyed by the ARABIC noun, i.e. the value side of `off_terms.php`, so the two
 * files cannot drift into disagreeing about what a word means.
 *
 * @return array<string,string>  Arabic head noun → product_category_children.slug
 */
return [
    // Deliberately absent, though both are nouns in `off_terms.php`:
    //   «كريمة» — a dairy cream, a cream biscuit, a cream chocolate, a hand
    //     cream. It put a Galaxy bar on the milk shelf.
    //   «فلفل»  — a spice, or the flavour of a crisp. It put Doritos among
    //     the spices.
    // An ambiguous head is worse than none: those rows wait for a category.

    // dairy
    'لبن' => 'dairy_milk',
    'لبن كامل الدسم' => 'dairy_milk',
    'جبنة' => 'cheese',
    'لبنة' => 'cheese',
    'زبادي' => 'yoghurt',

    // drinks
    'مياه' => 'water',
    'عصير' => 'juice',
    'كولا' => 'soft_drinks',
    'صودا' => 'soft_drinks',
    'قهوة' => 'tea_coffee',
    'شاي' => 'tea_coffee',

    // sweets and snacks
    'شوكولاتة' => 'chocolate',
    'حلوى' => 'chocolate',
    'لبان' => 'chocolate',
    'بسكويت' => 'biscuits_snacks',
    'كوكيز' => 'biscuits_snacks',
    'ويفر' => 'biscuits_snacks',
    'كراكرز' => 'biscuits_snacks',
    'شيبس' => 'biscuits_snacks',
    'كيك' => 'biscuits_snacks',

    // breakfast, bakery and spreads
    'خبز' => 'breakfast',
    'توست' => 'breakfast',
    'كرواسون' => 'breakfast',
    'شوفان' => 'breakfast',
    'كورن فليكس' => 'breakfast',
    'رقائق' => 'breakfast',
    'حبوب إفطار' => 'breakfast',
    'عسل' => 'breakfast',
    'مربى' => 'breakfast',
    'حلاوة طحينية' => 'breakfast',
    'طحينة' => 'breakfast',
    'عسل أسود' => 'breakfast',
    'زبدة فول سوداني' => 'breakfast',

    // staples
    'أرز' => 'rice_pasta',
    'مكرونة' => 'rice_pasta',
    'اسباجتي' => 'rice_pasta',
    'بيني' => 'rice_pasta',
    'فوسيلي' => 'rice_pasta',
    'نودلز' => 'rice_pasta',

    // fats
    'زيت' => 'oils_ghee',
    'زيت زيتون' => 'oils_ghee',
    'سمن' => 'oils_ghee',
    'زبدة' => 'oils_ghee',
    'مارجرين' => 'oils_ghee',

    // tins
    'تونة' => 'canned',
    'سردين' => 'canned',
    'صلصة طماطم' => 'canned',

    // condiments
    'كاتشب' => 'sauces',
    'مايونيز' => 'sauces',
    'خردل' => 'sauces',
    'خل' => 'sauces',

    // pulses, grains and spices
    'فول' => 'legumes_grains',
    'عدس' => 'legumes_grains',
    'حمص' => 'legumes_grains',
    'مكسرات' => 'legumes_grains',
    'تمر' => 'legumes_grains',
    'ملح' => 'spices',
    'بهارات' => 'spices',

    // frozen
    'آيس كريم' => 'frozen',

    // household and care
    'شامبو' => 'personal_care',
    'بلسم' => 'personal_care',
    'صابون' => 'personal_care',
    'مزيل عرق' => 'personal_care',
    'معجون أسنان' => 'personal_care',
    'لوشن' => 'personal_care',
    'لوشن للجسم' => 'personal_care',
    'جل استحمام' => 'personal_care',
    'منظف' => 'detergents',
    'مناديل' => 'paper_products',
    'حفاضات' => 'baby_care',
];
