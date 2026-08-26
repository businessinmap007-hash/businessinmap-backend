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

        /*
        | ── the axis these five actually price on, 2026-08-15 ──────────────
        |
        | Five children of this root carried a line and a descriptive and no
        | MODIFIER at all: بلياردو وبينج بونج #30، بولينج #33، مركز ترفيهي #239،
        | اكوا بارك #523 and رحلات ومراكب #526. A child with no modifier can name
        | what it sells and cannot say that the same thing costs two different
        | amounts — and the docblock at the top of this very file says what the
        | second amount depends on: «all sell an HOUR, they are all on
        | `booking_time`».
        |
        | So the modifier is the slot, and it is not a new idea — «فترة الحجز»
        | already exists, already carries exactly these five answers, and is
        | already the axis «قاعة مناسبات» prices on in
        | `hall_child_vocabularies.php`. A bowling lane on a Friday evening and
        | the same lane on a Tuesday morning are two prices in every alley in
        | Egypt, and none of these five could say so.
        |
        | Borrowed rather than rewritten: the group is declared in the halls file
        | with its five options, and naming it here adds these children to it.
        |
        | ── two more on 2026-08-17, and one exclusion narrowed ─────────────
        |
        | The list above was «the five children with NO modifier at all», which
        | is a symptom and not the question. Asked properly — is the same thing
        | two prices at two times — two more say yes.
        |
        | **«منطقة أطفال» #525** was never looked at, because it had a modifier:
        | «نمط تقديم الخدمة», فردي and فريق عمل. That answers WHO books the
        | soft-play area, a single child or a party, and says nothing about how
        | long. Every mall play area in Egypt is sold by the hour, and it was
        | the one child of this root with no way to say a time at all.
        |
        | **«استوديوهات» #271** was excluded here on 2026-08-15 with the reason
        | «a studio by the room». The room is the LINE — that is what «أنواع
        | الاستوديوهات» is — and the reason mistook a line for a price: the same
        | recording room is one figure for an hour and another for the day, and
        | hourly hire is the unit the whole trade quotes in.
        |
        | **«فوتوجرافر» #217 keeps the exclusion**, and it is the half of that
        | sentence that was right: a shoot IS priced by what is shot. «تصوير
        | أفراح» is a package with a day inside it, not a room somebody rents
        | by the hour.
        */
        'فترة الحجز' => [
            'name_en' => 'Booking Slot', 'price_role' => 'modifier',
            'children' => [30, 33, 239, 523, 526, 525, 271],
            'options' => [
                'فترة صباحية' => 'Morning Slot',
                'فترة مسائية' => 'Evening Slot',
                'يوم كامل' => 'Full Day',
                'نهاية الأسبوع' => 'Weekend',
                'بالساعة' => 'Hourly',
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

        // «بينج بونج» #219 — hard-deleted by the owner 2026-08-26 (rootless
        // list review); folded into a bench of «ألعاب ومرافق الترفيه» well
        // before that.

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

            /*
             * ── the ladies' day an aqua park could not advertise ──────────
             *
             * «سيدات / رجال / ميكس» #2389–2391 were minted at 23:19 on
             * 2026-08-13 and the owner then went through this root venue by
             * venue. From 23:54 onward he pinned all three onto every one he
             * touched — بلياردو، بولينج، بلاي ستيشن، مركز ترفيهي، صالة ألعاب،
             * رحلات ومراكب, six in a row. The one venue he had finished BEFORE
             * that, at 23:49, is this one, and it carries no decision on them
             * either way.
             *
             * A ladies' day is an ordinary thing at an Egyptian aqua park and
             * a customer filters on it, so the axis is handed over. This is
             * read off the ORDERING rather than off a recorded ruling — one
             * click in the screen reverses it, and a withdrawal outranks this.
             *
             * «منطقة أطفال», which he reached at 00:04 and did NOT give it to,
             * is left alone: a children's play area does not segregate, and
             * that is a decision made after the pattern had started.
             */
            'ملاءمة المكان' => ['سيدات', 'رجال', 'ميكس'],
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
        /*
         * «انترنت كافيه» #155 was missed by the eleven because it is filed
         * beside them and was not in the owner's list. Same shape exactly: a
         * floor of machines hired by the hour — until it folded into «مركز
         * ترفيهي» and was hard-deleted by the owner 2026-08-26 (rootless
         * list review).
         */

        526 => [
            'خدمات السياحة والسفر' => ['رحلات داخلية', 'رحلات بحرية', 'رحلات سفاري وبرية', 'برامج سياحية'],
        ],


        /*
         * «استوديوهات» #271 was the one child on the platform that could not be
         * DESCRIBED at all — it named eight room types and a service mode and
         * nothing a searcher narrows on. Its seven siblings under this root all
         * carry «ملاءمة المكان»; it simply never got it.
         *
         * Borrowed, like the trip rows above, rather than written again: a
         * studio is a room people come to, and «عائلي» and «ممنوع التدخين» are
         * as true of a photo studio as of a bowling alley.
         */
        271 => ['ملاءمة المكان' => ['عائلي', 'ممنوع التدخين']],
    ],
];
