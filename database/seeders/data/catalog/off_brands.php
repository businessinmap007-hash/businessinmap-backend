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
];
