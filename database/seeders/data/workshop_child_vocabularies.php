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
*/

return [

    'root' => 'workshops',

    'name_en_suffix' => 'Workshop',

    'links' => [
        240 => ['تخصصات ورش الأجهزة' => ['صيانة تبريد وتكييف', 'شحن فريون', 'تركيب أجهزة', 'صيانة دورية']],
    ],
];
