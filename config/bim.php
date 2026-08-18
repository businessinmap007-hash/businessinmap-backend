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
    | Talent cards — who pays, and how much
    |--------------------------------------------------------------------------
    | Owner, 2026-08-18: «يكون هو المستهدف المباشر والاساسى… والكشاف ايضا سيدفع
    | مقابل الفيديو اذا قام بالتواصل او طلب البيانات لان بيانات الناشئ سوف تكون
    | مخفية».
    |
    | THE SCOUT PAYS, NEVER THE PLAYER. The card is free to publish: a fee on
    | uploading would tax the boy for being watched, and the more scouts opened
    | his video the more he would owe — a bill that grows with his talent, sent
    | to a fourteen-year-old with no wallet. The party with a budget is the one
    | looking, and he is also the one the owner named as the direct target.
    |
    | `view` is charged ONCE per (player, scout) no matter how often he reopens
    | the card — «واذا شاهده اكثر من مرة تحسب مرة واحدة فقط».
    |
    | `reveal` is the real product and is deliberately NOT symbolic. A pound
    | does not stop a fake scout harvesting a hundred minors' phone numbers; a
    | real price plus a named, dated row is what makes that expensive and
    | traceable. Raise it before you lower it.
    |
    | Set either to 0 to switch that half off — a free launch charges nothing
    | and still records who looked.
    */
    'talent' => [
        'view_fee' => (float) env('BIM_TALENT_VIEW_FEE', 5),
        'reveal_fee' => (float) env('BIM_TALENT_REVEAL_FEE', 50),
        'scout_child_id' => (int) env('BIM_TALENT_SCOUT_CHILD_ID', 550),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Fraud detection (fines system, stage C)
    |--------------------------------------------------------------------------
    | The rating-graph scan raises a suspected-fraud flag for an admin to
    | review — it never fines or bans on its own. A flag needs a minimum number
    | of operations first, or one dispute on one operation reads as 100%. The
    | ratios are the share of a user's operations that ended disputed/cancelled.
    */
    'fraud' => [
        'min_operations' => (int) env('BIM_FRAUD_MIN_OPERATIONS', 5),
        'disputed_ratio' => (float) env('BIM_FRAUD_DISPUTED_RATIO', 0.30),
        'cancelled_ratio' => (float) env('BIM_FRAUD_CANCELLED_RATIO', 0.50),
    ],

];
