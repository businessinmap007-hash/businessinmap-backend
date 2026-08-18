<?php

/*
|--------------------------------------------------------------------------
| ورش ومراكز صيانة — the trade is the option, the workshop is the child
|--------------------------------------------------------------------------
| Owner, 2026-08-10:
|
|   «اضف الى ورش سيارات وأثاث مثلا وقم بتحويل الابناء الى خيارات كل واحدة منهم
|    حسب الملائمة. مثل كهربائي سيارات وعفشجى وميكاني وهكذا.»
|
| The root held 24 flat children, most of them ONE MAN'S JOB: كهربائي سيارات،
| ميكانيكي، سمكري، سروجي، اصلاح زجاج السيارات، ابواب سيارات — six children for
| six benches inside the same garage. A real workshop does several of them and
| had to pick one, and a customer looking for a garage had to guess which of the
| six the owner happened to choose.
|
| Same remodel as الصحة and الموضة before it, and for the same reason: what a
| business DOES is a multi-select option, not the row it hangs from. The child
| becomes the workshop, the old children become its priced specialties.
|
| `line`, all of them — a workshop is booked and the job is what is paid for, so
| «سمكرة» must be able to carry a price the way «كشف عظام» does in a clinic.
|
| NOT FOLDED, on purpose:
|   - «آثاث» #116 — 93 accounts across أربعة جذور (شركات، معارض، مصانع، ورش):
|     a furniture SELLER, not a workshop, and it carries `menu` here.
|   - «تبريد وتكييف» #240 — also stands under شركات, where it means the company
|     that sells the unit. Folding the ورش side alone needs the owner's word.
|   - «باب وشباك» #50 / «نجار باب وشباك» #84 — remodelled 2026-08-10 with their
|     own sixteen types; touching them again in the same day would be churn.
*/

return [

    'root_slug' => 'workshops',

    /*
    |--------------------------------------------------------------------------
    | Benches that changed their name after they were minted
    |--------------------------------------------------------------------------
    | `upsertOptions()` matches a bench by `name_ar` inside its group, so
    | editing a name in the lists below does NOT rename anything — it mints a
    | second row and leaves every child linked to the first. This map is
    | applied before the lists and moves the existing row instead.
    |
    | Owner, 2026-08-17: «يستحسن تعديل بنشر الى جنوط، هو الدارج فى مصر».
    | A puncture is what the bench DOES; جنوط is what the customer walks in
    | asking for, and «كاوتش وجنوط» is what is painted on the shop. The child
    | «جنوط وكاوتش سيارات» #249 under المحلات already used the pair — the
    | workshop bench was the odd one out.
    */
    'option_renames' => [
        'تخصصات ورش السيارات' => [
            'كاوتش وبنشر' => ['كاوتش وجنوط', 'Tyre & Rim Service'],
        ],
    ],

    'domains' => [

        /*
        | The garage. Six of these were children an hour ago and none of them
        | could be combined, which is why «مركز سيارات» existed as a seventh —
        | it was the only way to say «I do more than one of the above».
        */
        [
            'name_ar' => 'ورشة سيارات',
            'name_en' => 'Car Workshop',
            'group_name_ar' => 'تخصصات ورش السيارات',
            'group_name_en' => 'Car Workshop Specialties',
            'price_role' => 'line',
            // Whose service wiring the new child copies. Any of the folded
            // children would do — they are identical under this root — but
            // naming one keeps the copy reproducible.
            'services_from' => 'مركز سيارات',
            'folds' => [
                'كهربائي سيارات' => ['كهرباء سيارات', 'Car Electrical Work'],
                'ميكانيكي' => ['ميكانيكا سيارات', 'Vehicle Mechanics'],
                'سمكري' => ['سمكرة', 'Panel Beating'],
                'سروجي' => ['فرش وتنجيد سيارات', 'Car Upholstery'],
                'اصلاح زجاج السيارات' => ['إصلاح زجاج السيارات', 'Auto Glass Repair'],
                'ابواب سيارات' => ['أبواب سيارات', 'Car Doors'],
                'مركز سيارات' => ['مركز خدمة متكامل', 'Full Service Centre'],
                'فيبر جلاس' => ['فيبر جلاس', 'Fibreglass Work'],
            ],
            // Benches every garage has and no child ever named.
            'extra_options' => [
                'بوية وتلميع' => 'Paint & Polishing',
                'تكييف سيارات' => 'Car Air Conditioning',
                'كاوتش وجنوط' => 'Tyre & Rim Service',
                'فرامل وتعليق' => 'Brakes & Suspension',
                'تغيير زيت وفلاتر' => 'Oil & Filter Change',
                'فحص كمبيوتر' => 'Diagnostics',
            ],
        ],

        /*
        | The joinery. «استورجى» and «كوتش» are the clearest case on the root:
        | both are one bench in a furniture workshop, and a workshop that
        | upholsters AND paints had to be two accounts or half a business.
        */
        [
            'name_ar' => 'ورشة أثاث ونجارة',
            'name_en' => 'Furniture & Carpentry Workshop',
            'group_name_ar' => 'تخصصات ورش الأثاث',
            'group_name_en' => 'Furniture Workshop Specialties',
            'price_role' => 'line',
            'services_from' => 'تنجيد',
            'folds' => [
                'تنجيد' => ['تنجيد', 'Upholstery Work'],
                'استورجى' => ['دهان وتلميع أثاث', 'Furniture Painting'],
                'كوتش' => ['كنب وركنات', 'Sofas & Corner Units'],
                'مطابخ و دريسنج' => ['مطابخ ودريسنج', 'Kitchens & Dressing Rooms'],
                'أويمجى' => ['حفر وأويما', 'Wood Carving Work'],
            ],
            /*
            | «تنجيد» #288 carried the 43 car marques — an upholsterer who also
            | does car seats, and the blanket seeder that granted the axis never
            | asked which kind. Carried verbatim it would have made every
            | furniture workshop on the platform claim to service BMWs. The
            | garage keeps them; this bench does not.
            */
            'carry_exclude_groups' => ['ماركات السيارات'],
            'extra_options' => [
                'نجارة موبيليا' => 'Furniture Joinery',
                'ترميم وإصلاح أثاث' => 'Furniture Restoration',
                'ديكورات خشبية' => 'Wooden Decor',
                'تفصيل غرف نوم' => 'Bedroom Sets To Order',
                /*
                 * 2026-08-12. «استرجي» (Wood Painter) and «أويمجى» (Wood
                 * Carving) each answered with two rows, and the shortage was in
                 * this list: a finisher's work is دوكو and ورنيش and تعتيق
                 * before it is «دهان», and a carver's headline job in Egypt is
                 * أرابيسك ومشربية, which nothing here could say.
                 */
                'رش دوكو' => 'Duco Spray Finishing',
                'ورنيش وسيلر' => 'Varnish & Sealer',
                'تعتيق وباتينا' => 'Antiquing & Patina',
                'أرابيسك ومشربية' => 'Arabesque & Mashrabiya',
                /*
                 * 2026-08-17: «نحتاج فى ورش أثاث ونجارة CNC ايضا».
                 *
                 * A separate bench from the metal shop's «تشغيل CNC»
                 * #1224 and deliberately named differently. A wood CNC
                 * is a ROUTER — it cuts panels, engraves doors and
                 * profiles kitchen fronts — while the metal shop's is a
                 * milling machine. One name in two groups would read as
                 * one trade and price as two.
                 */
                'راوتر CNC' => 'CNC Router',
            ],
        ],

        /*
        | The metal shop. «حداد» here is the WORKSHOP — #259 under مهن وحرفيين is
        | the tradesman, and the owner ruled on 2026-08-09 that the two rows stay
        | apart. Only #31 is folded.
        */
        [
            'name_ar' => 'ورشة حدادة وخراطة',
            'name_en' => 'Metalwork & Machining Workshop',
            'group_name_ar' => 'تخصصات ورش المعادن',
            'group_name_en' => 'Metal Workshop Specialties',
            'price_role' => 'line',
            'services_from' => 'مخرطة',
            'folds' => [
                'حداد' => ['حدادة', 'Blacksmithing'],
                'مخرطة' => ['خراطة', 'Turning & Machining'],
                'الكريتال' => ['كريتال وشبابيك حديد', 'Crittall & Steel Windows'],
                'Cnc' => ['تشغيل CNC', 'CNC Machining'],
                'استانلس ومعدات مطاعم' => ['استانلس ومعدات مطاعم', 'Stainless & Catering Equipment'],
            ],
            'extra_options' => [
                'لحام' => 'Welding',
                'مشغولات حديد ودرابزين' => 'Ironwork & Railings',
                'قص وثني معادن' => 'Metal Cutting & Bending',
                'ألومنيوم ومنيوم' => 'Aluminium Fabrication',
            ],
        ],

        /*
        | The repair bench. Both folded children already carry «أنواع الأجهزة
        | الكهربائية» (2026-08-09) — WHICH appliance. This group is the other
        | half: WHICH JOB, and what it costs.
        */
        [
            'name_ar' => 'ورشة صيانة أجهزة',
            'name_en' => 'Appliance Repair Workshop',
            'group_name_ar' => 'تخصصات ورش الأجهزة',
            'group_name_en' => 'Appliance Workshop Specialties',
            'price_role' => 'line',
            'services_from' => 'تصليح أجهزة كهربائية',
            'folds' => [
                'تصليح أجهزة كهربائية' => ['تصليح أجهزة كهربائية', 'Electrical Appliance Repair'],
                'تصليح غسالات وبتوجازات' => ['تصليح غسالات وبوتاجازات', 'Washer & Cooker Repair'],
            ],
            'extra_options' => [
                'صيانة تبريد وتكييف' => 'Cooling & A/C Servicing',
                'شحن فريون' => 'Refrigerant Recharge',
                'صيانة سخانات' => 'Water Heater Servicing',
                'تركيب أجهزة' => 'Appliance Installation',
                'صيانة دورية' => 'Scheduled Maintenance',
            ],
        ],
    ],
];
