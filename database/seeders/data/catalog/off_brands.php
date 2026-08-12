<?php

/**
 * Brands the source names that the catalog does not hold yet, with the Arabic
 * spelling they are to be created under.
 *
 * The importer refuses a row whose brand it does not know, because the Arabic
 * spelling of a brand is a decision, not a transliteration a command should
 * make on its own — «Bimbo» could as easily be «بيمبو» as «بمبو», and once a
 * hundred products carry the wrong one it is a hundred rows to fix.
 *
 * So the decision lives here, in a file that can be read and corrected, exactly
 * as `off_terms.php` holds every word that may be translated. A brand not
 * listed here is still refused, and appears in the rejects sheet under
 * «unknown-brand» — which is how this list grows.
 *
 * Keyed by the brand as Open Food Facts spells it, folded the way
 * `OpenFoodFactsRow::brandKey()` folds it (lowercase, letters and digits only),
 * so «Rich Bake», «Rich bake» and «RICH BAKE» all find the same entry.
 *
 * @return array<string,array{ar:string,en:string}>
 */
return [
    'richbake' => ['ar' => 'ريتش بيك', 'en' => 'Rich Bake'],
    'mcvities' => ['ar' => 'ماكفيتيز', 'en' => "McVitie's"],
    'bimbo' => ['ar' => 'بيمبو', 'en' => 'Bimbo'],
    'dinafarms' => ['ar' => 'مزارع دينا', 'en' => 'Dina Farms'],
    'elbawadi' => ['ar' => 'البوادي', 'en' => 'El Bawadi'],
    'crunchy' => ['ar' => 'كرانشي', 'en' => 'Crunchy'],
    'tuc' => ['ar' => 'تاك', 'en' => 'TUC'],
    'redbull' => ['ar' => 'ريد بُل', 'en' => 'Red Bull'],
    'yopolis' => ['ar' => 'يوبوليس', 'en' => 'Yopolis'],
    'balance' => ['ar' => 'بالانس', 'en' => 'Balance'],
    'rosegarden' => ['ar' => 'روز جاردن', 'en' => 'Rose Garden'],
    'lino' => ['ar' => 'لينو', 'en' => 'Lino'],
    'twist' => ['ar' => 'تويست', 'en' => 'Twist'],
    'v7' => ['ar' => 'في سفن', 'en' => 'V7'],
    'hawaa' => ['ar' => 'حواء', 'en' => 'Hawaa'],

    /*
    |--------------------------------------------------------------------------
    | Added after the whole-database export — 2026-08-13
    |--------------------------------------------------------------------------
    |
    | The dump brought the Egyptian set from 1,374 rows to 4,205, and the
    | blocker moved: 1,357 rows were refused for a brand the catalog does not
    | hold, across 901 distinct names. These are the ones appearing most often
    | that I can spell in Arabic with confidence — established Egyptian houses
    | (أطياب، امتنان، جورميه، بسمة، تيميز) and international names with a
    | settled Arabic spelling. The long tail stays refused on purpose.
    */
    'atyab' => ['ar' => 'أطياب', 'en' => 'Atyab'],
    'imtenan' => ['ar' => 'امتنان', 'en' => 'Imtenan'],
    'gourmet' => ['ar' => 'جورميه', 'en' => 'Gourmet'],
    'basma' => ['ar' => 'بسمة', 'en' => 'Basma'],
    'temmys' => ['ar' => 'تيميز', 'en' => "Temmy's"],
    'farmfrites' => ['ar' => 'فارم فريتس', 'en' => 'Farm Frites'],
    'bakerolz' => ['ar' => 'بيك رولز', 'en' => 'Bake Rolz'],
    'breadfast' => ['ar' => 'بريدفاست', 'en' => 'Breadfast'],
    'breadway' => ['ar' => 'بريدواي', 'en' => 'Breadway'],
    'cheetos' => ['ar' => 'تشيتوس', 'en' => 'Cheetos'],
    'haribo' => ['ar' => 'هاريبو', 'en' => 'Haribo'],
    'bebeto' => ['ar' => 'بيبيتو', 'en' => 'Bebeto'],
    'dolphin' => ['ar' => 'دولفين', 'en' => 'Dolphin'],
    'bigchips' => ['ar' => 'بيج شيبس', 'en' => 'Big Chips'],
    'friday' => ['ar' => 'فرايدي', 'en' => 'Friday'],
    'shahd' => ['ar' => 'شهد', 'en' => 'Shahd'],
    'italiano' => ['ar' => 'إيطاليانو', 'en' => 'Italiano'],
    'dobella' => ['ar' => 'دوبيلا', 'en' => 'Dobella'],
    'donlopez' => ['ar' => 'دون لوبيز', 'en' => 'Don Lopez'],
    'organicnation' => ['ar' => 'أورجانيك نيشن', 'en' => 'Organic Nation'],
    'themilkman' => ['ar' => 'ذا ميلك مان', 'en' => 'The Milkman'],
    'bonjorno' => ['ar' => 'بونجورنو', 'en' => 'Bonjorno'],
    'mightycrisp' => ['ar' => 'مايتي كريسب', 'en' => 'Mighty Crisp'],
    'wonderville' => ['ar' => 'وندرفيل', 'en' => 'Wonderville'],
    'scrunch' => ['ar' => 'سكرانش', 'en' => 'Scrunch'],
    'kayy' => ['ar' => 'كاي', 'en' => 'Kayy'],
    'halo' => ['ar' => 'هالو', 'en' => 'Halo'],
    'lychee' => ['ar' => 'ليتشي', 'en' => 'Lychee'],
    'mega' => ['ar' => 'ميجا', 'en' => 'Mega'],
    'dream' => ['ar' => 'دريم', 'en' => 'Dream'],
    'jaguar' => ['ar' => 'جاجوار', 'en' => 'Jaguar'],
    'flaminco' => ['ar' => 'فلامنكو', 'en' => 'Flaminco'],
    'novy' => ['ar' => 'نوفي', 'en' => 'Novy'],
    'spuds' => ['ar' => 'سبدز', 'en' => 'Spuds'],
    'refuel' => ['ar' => 'ريفيول', 'en' => 'Refuel'],
    'keepgoing' => ['ar' => 'كيب جوينج', 'en' => 'Keep Going'],
    'limitless' => ['ar' => 'ليمتلس', 'en' => 'Limitless'],
];
