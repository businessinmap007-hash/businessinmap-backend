<?php

/*
|--------------------------------------------------------------------------
| «فنون و ترفية» — the last mute root
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «الاحد عشر جميعا».
|
| I put a question with this one rather than an answer: a billiards hall, a
| bowling alley and a PlayStation lounge all sell an HOUR, they are all on
| `booking_time`, and the child's own name plus that kind may already be the
| whole story — nobody browses a billiards hall by anything but the hour. The
| owner's call is that all eleven get a vocabulary.
|
| Every one is booking + offers with no retail, so the SERVICE rule: the thing
| booked is the priced row, and these are `line` groups.
|
| ── One list for the eight venues ─────────────────────────────────────────
|
| بلياردو، بولينج، بينج بونج، بلاي ستيشن، مركز ترفيهي، اكوا بارك، صالة ألعاب
| and منطقة أطفال are eight children of one shape: a floor with things on it
| that are hired by the hour. They take slices of ONE list rather than eight
| lists that repeat «طاولة بلياردو» at each other.
|
| The slices are deliberately GENEROUS where the trade really overlaps — an
| Egyptian bowling centre almost always has pool tables and an arcade corner,
| and a recreation centre has a bit of everything. The merchant ticks what he
| actually owns; the slice only has to stop being absurd, and «زحاليق مائية»
| on a ping-pong hall would be.
|
| ── The three that are NOT venues ─────────────────────────────────────────
|
| فوتوجرافر sells a SHOOT, استوديوهات rents a ROOM, and رحلات ومراكب runs a
| BOAT. Three different questions, three lists, and none of them is an hour of
| a table:
|
|   فوتوجرافر #217   what he shoots — a wedding, a newborn, a product
|   استوديوهات #271  which room — photo, recording, podcast, chroma
|   رحلات ومراكب #526 which vessel, plus the trip rows BORROWED from «خدمات
|                     السياحة والسفر» rather than written again
*/

return [

    'root' => 'arts-entertainment',

    'name_en_suffix' => 'Leisure',

    'groups' => [

        /*
         * Created empty and sliced below — eight children, eight slices.
         */
        'ألعاب ومرافق الترفيه' => [
            'name_en' => 'Leisure Attractions', 'price_role' => 'line', 'children' => [],
            'options' => [
                'طاولة بلياردو' => 'Pool Table',
                'طاولة سنوكر' => 'Snooker Table',
                'مسار بولينج' => 'Bowling Lane',
                'طاولة بينج بونج' => 'Table Tennis',
                'طاولة بيبي فوت' => 'Foosball Table',
                'جهاز ألعاب' => 'Console Station',
                'ألعاب أركيد' => 'Arcade Machines',
                'ألعاب واقع افتراضي' => 'VR Games',
                'سيميولاتور سباقات' => 'Racing Simulator',
                'ملاهي أطفال' => 'Kids Rides',
                'نطاطات وبيت كور' => 'Bouncers & Ball Pit',
                'منطقة رمل ولعب' => 'Sand & Play Area',
                'زحاليق مائية' => 'Water Slides',
                'حمام سباحة ترفيهي' => 'Leisure Pool',
                'غرفة خاصة' => 'Private Room',
                'حفلات وأعياد ميلاد' => 'Parties & Birthdays',
            ],
        ],

        /*
         * A PS4 hour and a PS5 hour are two prices for one line, which is what
         * the entertainment remodel created the pricing for in the first place.
         */
        'فئة جهاز الألعاب' => [
            'name_en' => 'Console Class', 'price_role' => 'modifier', 'children' => [225, 524],
            'options' => [
                'بلايستيشن ٤' => 'PlayStation 4',
                'بلايستيشن ٥' => 'PlayStation 5',
                'إكس بوكس' => 'Xbox',
                'كمبيوتر ألعاب' => 'Gaming PC',
                'نظارة واقع افتراضي' => 'VR Headset',
                'شاشة كبيرة' => 'Large Screen',
            ],
        ],

        'خدمات التصوير' => [
            'name_en' => 'Photography Services', 'price_role' => 'line', 'children' => [217],
            'options' => [
                'تصوير أفراح' => 'Wedding Photography',
                'تصوير خطوبة' => 'Engagement Shoots',
                'جلسة استوديو' => 'Studio Session',
                'تصوير أطفال وحديثي الولادة' => 'Newborn & Kids',
                'تصوير منتجات' => 'Product Photography',
                'تصوير مناسبات وشركات' => 'Events & Corporate',
                'تصوير فيديو ومونتاج' => 'Video & Editing',
                'تصوير جوي بدرون' => 'Drone Photography',
                'تصوير عقارات' => 'Property Photography',
                'طباعة ألبومات' => 'Albums & Prints',
            ],
        ],

        'أنواع الاستوديوهات' => [
            'name_en' => 'Studio Types', 'price_role' => 'line', 'children' => [271],
            'options' => [
                'استوديو تصوير' => 'Photo Studio',
                'استوديو تسجيل صوتي' => 'Recording Studio',
                'استوديو بودكاست' => 'Podcast Studio',
                'استوديو فيديو' => 'Video Studio',
                'غرفة كروما' => 'Chroma Room',
                'غرفة مونتاج' => 'Editing Suite',
                'قاعة بروفات' => 'Rehearsal Room',
                'معدات إضاءة وصوت' => 'Lighting & Sound Hire',
            ],
        ],

        'المراكب والرحلات النيلية' => [
            'name_en' => 'Boats & Nile Trips', 'price_role' => 'line', 'children' => [526],
            'options' => [
                'رحلة نيلية' => 'Nile Cruise Trip',
                'مركب خاص' => 'Private Boat',
                'يخت' => 'Yacht',
                'فلوكة' => 'Felucca',
                'حفلة على مركب' => 'Boat Party',
                'صيد بحري' => 'Fishing Trip',
            ],
        ],
    ],

    'links' => [

        // ── the eight venues, eight slices of one list ───────────────────

        30 => ['ألعاب ومرافق الترفيه' => ['طاولة بلياردو', 'طاولة سنوكر', 'طاولة بيبي فوت', 'غرفة خاصة']],

        33 => ['ألعاب ومرافق الترفيه' => ['مسار بولينج', 'طاولة بلياردو', 'ألعاب أركيد', 'حفلات وأعياد ميلاد']],

        219 => ['ألعاب ومرافق الترفيه' => ['طاولة بينج بونج', 'طاولة بلياردو', 'طاولة بيبي فوت']],

        225 => [
            'ألعاب ومرافق الترفيه' => ['جهاز ألعاب', 'غرفة خاصة', 'سيميولاتور سباقات', 'ألعاب واقع افتراضي'],
        ],

        // A recreation centre has a bit of everything, which is what makes it one.
        239 => [
            'ألعاب ومرافق الترفيه' => [
                'طاولة بلياردو', 'مسار بولينج', 'طاولة بينج بونج', 'جهاز ألعاب',
                'ألعاب أركيد', 'ألعاب واقع افتراضي', 'ملاهي أطفال',
                'غرفة خاصة', 'حفلات وأعياد ميلاد',
            ],
        ],

        523 => [
            'ألعاب ومرافق الترفيه' => [
                'زحاليق مائية', 'حمام سباحة ترفيهي', 'منطقة رمل ولعب',
                'ملاهي أطفال', 'حفلات وأعياد ميلاد',
            ],
        ],

        524 => [
            'ألعاب ومرافق الترفيه' => [
                'ألعاب أركيد', 'ألعاب واقع افتراضي', 'سيميولاتور سباقات',
                'جهاز ألعاب', 'حفلات وأعياد ميلاد',
            ],
        ],

        525 => [
            'ألعاب ومرافق الترفيه' => [
                'ملاهي أطفال', 'نطاطات وبيت كور', 'منطقة رمل ولعب',
                'ألعاب أركيد', 'حفلات وأعياد ميلاد',
            ],
        ],

        /*
         * The trip rows already exist on «خدمات السياحة والسفر», written for
         * «سياحة» #279 under شركات. A Nile boat operator runs the same safari
         * and the same sea trip; only the vessel is his own vocabulary.
         */
        526 => [
            'خدمات السياحة والسفر' => ['رحلات داخلية', 'رحلات بحرية', 'رحلات سفاري وبرية', 'برامج سياحية'],
        ],
    ],
];
