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

    /*
    |--------------------------------------------------------------------------
    | Platform treasury
    |--------------------------------------------------------------------------
    | The account holding the platform's own money: every service fee lands
    | here, and later fines and escheated balances from deleted accounts.
    |
    | Resolved by id from config, never by looking up a name like "BIM". A money
    | destination decided by a string match is an accident waiting to happen —
    | and the existing account named "BIM" is an ordinary trading business that
    | sells and pays fees, so mixing platform money into it would make the two
    | impossible to separate.
    |
    | Created by PlatformAccountSeeder. Until it is set, fees are debited from
    | the payer exactly as before and simply not credited anywhere — the money
    | is never blocked on a missing config.
    */
    'platform_wallet_user_id' => env('BIM_PLATFORM_WALLET_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Account deletion (BIM-15.1)
    |--------------------------------------------------------------------------
    | A deletion request soft-deletes the account and freezes its wallet; the
    | balance does NOT move. Within the grace window both the account and its
    | balance are restored exactly as they were. Only after the window does the
    | balance escheat to the treasury and the identity get anonymized.
    |
    | balance_transfer_cooldown_days: how long after the last operation or
    | dispute before a user may move their balance out — so nobody can transact,
    | drain the wallet, and vanish.
    */
    'account_deletion' => [
        'grace_days' => (int) env('BIM_DELETION_GRACE_DAYS', 30),
        'balance_transfer_cooldown_days' => (int) env('BIM_DELETION_COOLDOWN_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Location resolution (BIM-11.1)
    |--------------------------------------------------------------------------
    | nearest_max_km: how far a "use my location" GPS point may be from the
    | closest city in our tables before we return "no confident match" and let
    | the app fall back to the manual pickers. A city index is coarse, so this
    | is deliberately generous.
    */
    'location' => [
        'nearest_max_km' => (float) env('BIM_LOCATION_NEAREST_MAX_KM', 60),
    ],

];
