<?php

/*
|--------------------------------------------------------------------------
| «ورش ومراكز صيانة» — one child holding the wrong half of its trade
|--------------------------------------------------------------------------
| «تبريد وتكييف» #240 stands under «ورش» and «شركات». The companies pass gave
| it «أعمال التبريد والتكييف», a `modifier` naming what a supplier STOCKS —
| chillers, split units, cold rooms — which is right for the wholesaler and
| silent for the workshop: a repair shop is booked, and the JOB is the priced
| row.
|
| It borrows the cooling rows of «تخصصات ورش الأجهزة», which is what its six
| siblings under this root already run on. The modifier stays: which system
| still qualifies the price of the work.
|
| ── 2026-08-17: «راجع باقي أبناء الورش بنفس الطريقة» ─────────────────────
|
| All six read fluent, and this is the LIGHTEST-curated root in the sweep so
| far — 23 ledger rows against 219 for الرياضة and 151 for الزراعة. What is
| here is mostly seeder output, so an absence is more likely to be an absence.
|
| **None of the six could say whether it comes to you.**
|
| Every one carries «نمط تقديم الخدمة» and every one carries the same two
| answers: فردي and فريق عمل — one man or a crew. That is a real modifier and
| it is not the one this trade turns on. What decides the price of a repair in
| Egypt is WHERE it happens: the technician climbs to your balcony to gas a
| split unit, or the compressor comes down to the shop. «الفني بيجيلك البيت»
| is a different quote from «هاتها الورشة».
|
| «زيارة منزلية» #1979 is that row and it already lives in this exact group —
| it was moved there out of «تسهيلات ومرافق طبية» on 2026-08-16, precisely so
| a house call could carry a price instead of being a facility tick. Its five
| carriers are all medical.
|
| And the reason no workshop has it is the pattern this sweep has now hit three
| times: **a curation save freezes the group as it was that day**. #84 and #240
| had «نمط تقديم الخدمة» curated on 2026-08-14 (فردي pinned, أونلاين withdrawn),
| and the other four on 2026-08-10 — all of them BEFORE #1979 joined the group
| on the 16th. No child in this root carries a decision on it either way.
|
| Three get it, and the split is where the work physically happens:
|
|   #84  نجار باب وشباك    a door is measured and hung at the address
|   #240 تبريد وتكييف       the split unit is on the customer's wall
|   #546 ورشة صيانة أجهزة  a washing machine is repaired in the kitchen
|
| The other three are left, and deliberately: you bring the car to «ورشة
| سيارات», and «خراطة» and «تنجيد» are what a workshop with a floor and a lathe
| exists for. Both do fit work on site — a kitchen, a stair rail — but the
| bench is the trade and a modifier that is true half the time is noise on the
| pricing screen.
*/

return [

    'root' => 'workshops',

    'name_en_suffix' => 'Workshop',

    'links' => [
        84 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],

        240 => [
            'تخصصات ورش الأجهزة' => ['صيانة تبريد وتكييف', 'شحن فريون', 'تركيب أجهزة', 'صيانة دورية'],
            'نمط تقديم الخدمة' => ['زيارة منزلية'],
        ],

        546 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],
    ],
];
