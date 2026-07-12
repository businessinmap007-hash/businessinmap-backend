<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Menu order tax
    |--------------------------------------------------------------------------
    | VAT percentage applied to menu order bills (items + service fee). Shown to
    | each participant on their own share of a shared cart. Egypt VAT defaults to
    | 14%. Override with BIM_MENU_TAX_RATE in .env.
    */
    'menu_tax_rate_percent' => (float) env('BIM_MENU_TAX_RATE', 14),

];
