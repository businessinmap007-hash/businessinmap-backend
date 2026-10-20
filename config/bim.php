<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Changing a business's trade (main + sub category on the same account)
    |--------------------------------------------------------------------------
    | «لا يمكن تغيير النشاط الا بعد مرور 15 يوم على اخر عملية بيع او حجز حتى
    | تكون هذه الفترة ضمانا لعدم بيع منتج غير مطابق للمواصفات او خدمة وهمية».
    | Without the wait, changing trade is a way to shed a bad record. 0 turns it
    | off — only ever for a test machine (BIM_TRADE_SWITCH_COOLING_OFF_DAYS=0).
    */
    'trade_switch_cooling_off_days' => (int) env('BIM_TRADE_SWITCH_COOLING_OFF_DAYS', 15),


    /*
    |--------------------------------------------------------------------------
    | Rides (خدمة ليموزين) — priced by distance, never drawn on a map
    |--------------------------------------------------------------------------
    | The platform has no transport licence, so a ride is asked and answered WITHOUT a map: the customer names where
    | from and where to, the distance between the two places is a straight line between their centres stretched by a
    | road factor, and the price the customer starts from is that distance x a rate. It is only a suggestion: the
    | customer changes it and each driver answers with his own.
    */
    'rides' => [
        'rate_per_km' => (float) env('BIM_RIDE_RATE_PER_KM', 7),
        'min_fare' => (float) env('BIM_RIDE_MIN_FARE', 30),
        'road_factor' => (float) env('BIM_RIDE_ROAD_FACTOR', 1.3),
        // an immediate ride is asked for only so long; a scheduled one lives until its time
        'ttl_minutes' => (int) env('BIM_RIDE_TTL_MINUTES', 30),
        // the drivers told about a new request
        'notify_limit' => (int) env('BIM_RIDE_NOTIFY_LIMIT', 50),
        // «راجع فاضي»: a driver going back empty offers the way home. The share of the reference fare he is
        // offered as a starting price (he sets his own), how far ahead he may publish, and the hours of grace after the
        // departure time before the offer is no longer listed.
        'empty_return' => [
            'suggested_share' => (float) env('BIM_EMPTY_RETURN_SHARE', 0.5),
            'window_hours' => (int) env('BIM_EMPTY_RETURN_WINDOW_HOURS', 72),
            'grace_minutes' => (int) env('BIM_EMPTY_RETURN_GRACE_MINUTES', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    | A moderator who opens a report is recorded as the one dealing with it; nobody else may act on it until he closes it or
    | lets it go. A claim untouched for `claim_minutes` lapses, so a moderator who walks away cannot freeze a report for good.
    */
    'moderation' => [
        'claim_minutes' => 30,
        // a reported account is frozen (not removed) for this many days
        'freeze_days' => 3,
        // the Nth freeze deletes the account and confiscates its balance and guarantee
        'freezes_to_delete' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Training and nutrition programmes
    |--------------------------------------------------------------------------
    | A programme is a PAID month (`plan_months`): the fee set in the control panel (events `training_program_change` for the
    | workout, `nutrition_program_change` for the meals) is taken when the trainer shares it with a client and again for every
    | further month; a plan that is not renewed disappears from the client. Editing inside the month is free.
    */
    'training' => [
        'plan_months' => 1,
        // days before paid_until that trainer and client are told the month is ending
        'renew_reminder_days' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner listings (إعلان من المالك) — one item per category, no business account
    |--------------------------------------------------------------------------
    | An ordinary account may hold ONE live listing per category (one car, one flat, one phone…). It lives `active_days`
    | unless renewed; once sold/rented (or expired) it is removed `purge_days` later. Phone numbers never appear in the
    | listing or in its chat: when the two agree either may ask to swap numbers and the platform's fee is paid then — priced
    | for each side in the control panel (event `owner_listing_contact`, see PlatformEventFeeService).
    */
    'owner_listings' => [
        'per_category' => (int) env('BIM_OWNER_LISTINGS_PER_CATEGORY', 1),
        'active_days' => (int) env('BIM_OWNER_LISTINGS_ACTIVE_DAYS', 60),
        'purge_days' => (int) env('BIM_OWNER_LISTINGS_PURGE_DAYS', 3),
        'max_images' => (int) env('BIM_OWNER_LISTINGS_MAX_IMAGES', 8),
        // days before `expires_at` that the owner is told the listing is about to end
        'expiry_reminder_days' => (int) env('BIM_OWNER_LISTINGS_EXPIRY_REMINDER_DAYS', 3),
        // an advertiser holding at least this guarantee level needs no deposit (the deposit itself is priced in the cPanel)
        'min_guarantee_level' => env('BIM_OWNER_LISTINGS_MIN_GUARANTEE_LEVEL', 'bronze'),
        'reports_to_hide' => (int) env('BIM_OWNER_LISTINGS_REPORTS_TO_HIDE', 3),
    ],
];
