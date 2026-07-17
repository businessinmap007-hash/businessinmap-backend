<?php

namespace App\Support;

/**
 * BIM-14.1 — the AdminV2 permission vocabulary.
 *
 * Before this, `admin.v2` middleware asked one question — "are you an admin?" —
 * and every admin who passed it reached everything: the treasury, the fee rules,
 * dispute resolutions that move money. There was no second tier to be, so the
 * gap was latent rather than exploited. It stops being latent the first time a
 * support agent is hired.
 *
 * Bouncer was already installed and already had abilities, but they describe a
 * different application: `products_management`, `sliders_management`,
 * `banners_management`, `home_settings` — v1-era nouns, none of which name
 * anything on the AdminV2 surface. So this is a new vocabulary, not a reuse.
 *
 * Named after JOBS rather than screens, because that is what gets delegated. Ten
 * of these plus ACCESS cover 321 routes; one ability per route group would have
 * been 61 names nobody could hold in their head, and a permission set nobody
 * understands gets granted wholesale, which is the thing we are trying to stop.
 *
 * MONEY is the axis that matters. It is not just the wallet screens: the
 * money-moving actions that live inside other domains (resolving a dispute by
 * refunding, releasing a booking deposit, unlocking a guarantee to balance)
 * require MONEY *in addition to* their own domain ability. That is what lets
 * someone triage disputes all day without being able to move a pound.
 */
final class AdminAbility
{
    /** Enter the panel at all: dashboard, the shared business picker, uploads. */
    public const ACCESS = 'admin.access';

    /** Accounts: view, edit, suspend, delete. */
    public const USERS = 'admin.users';

    /**
     * Anything that moves money or can authorise its movement: wallets, manual
     * recharges, payments, top-ups, and the live payment-gateway credentials
     * (whoever can rewrite those can redirect real money).
     */
    public const MONEY = 'admin.money';

    /** What the platform charges: base fees, the rules engine, promotions, consents. */
    public const FEES = 'admin.fees';

    /** Dispute triage. Resolutions that move money need MONEY as well. */
    public const DISPUTES = 'admin.disputes';

    /** Guarantees and their levels — the trust layer. */
    public const TRUST = 'admin.trust';

    /** The taxonomy: categories, children, options, catalog, service branches. */
    public const CATALOG = 'admin.catalog';

    /** Live operations: bookings, bookable items, delivery, menus, trips, tables. */
    public const OPERATIONS = 'admin.operations';

    /** B2B: offers, partnerships, allocations, boosts, subscriptions, prices. */
    public const COMMERCE = 'admin.commerce';

    /** Marketing content: posts, jobs, sponsors, albums. */
    public const CONTENT = 'admin.content';

    /** Platform configuration: push credentials, notification centre. */
    public const SETTINGS = 'admin.settings';

    /**
     * Grant and revoke other admins' abilities.
     *
     * The root power, and deliberately not folded into SETTINGS: whoever can
     * hand out MONEY effectively has MONEY, so bundling this with the push
     * credentials screen would have quietly made SETTINGS equal to everything.
     *
     * It is only safe because of the rule in AdminAbilityService: you can grant
     * only what you already hold. That, plus not being able to edit yourself, is
     * what stops this from being a one-click path to root.
     */
    public const ROLES = 'admin.roles';

    public const ALL = [
        self::ACCESS,
        self::USERS,
        self::MONEY,
        self::FEES,
        self::DISPUTES,
        self::TRUST,
        self::CATALOG,
        self::OPERATIONS,
        self::COMMERCE,
        self::CONTENT,
        self::SETTINGS,
        self::ROLES,
    ];

    /**
     * Bouncer's wildcard. A holder passes every check, including abilities that
     * do not exist yet — which is why granting it is the safe way to say
     * "exactly the access you had before this system existed".
     */
    public const WILDCARD = '*';

    /** @return array<string, string> ability => Arabic label, for a future roles UI. */
    public static function labels(): array
    {
        return [
            self::ACCESS => 'الدخول للوحة الإدارة',
            self::USERS => 'إدارة الحسابات',
            self::MONEY => 'الأموال (المحافظ والمدفوعات وبيانات بوابة الدفع)',
            self::FEES => 'رسوم الخدمة وقواعدها',
            self::DISPUTES => 'النزاعات (الفرز)',
            self::TRUST => 'الضمانات ومستوياتها',
            self::CATALOG => 'التصنيفات والكتالوج وفروع الخدمات',
            self::OPERATIONS => 'العمليات (حجوزات، توصيل، منيو، رحلات)',
            self::COMMERCE => 'العروض والشراكات والاشتراكات',
            self::CONTENT => 'المحتوى (منشورات، وظائف، رعاة، ألبومات)',
            self::SETTINGS => 'إعدادات المنصة',
            self::ROLES => 'صلاحيات المشرفين (منح وسحب)',
        ];
    }

    /** @return array<string, string> ability => what granting it actually lets someone do. */
    public static function hints(): array
    {
        return [
            self::ACCESS => 'بدونها لا يرى شيئًا في اللوحة إطلاقًا.',
            self::USERS => 'عرض وتعديل وإيقاف وحذف حسابات المستخدمين.',
            self::MONEY => '⚠️ يحرّك أموالًا حقيقية: شحن يدوي، تسوية نزاع، فك عربون، وبيانات بوابة الدفع.',
            self::FEES => 'يحدّد ما تتقاضاه المنصة من كل عملية.',
            self::DISPUTES => 'فرز النزاعات ومتابعتها. لا يستطيع صرف المال بدون صلاحية الأموال.',
            self::TRUST => 'الضمانات ومستوياتها.',
            self::CATALOG => 'التصنيفات والكتالوج وفروع الخدمات.',
            self::OPERATIONS => 'الحجوزات والتوصيل والمنيو والرحلات.',
            self::COMMERCE => 'العروض والشراكات والاشتراكات والتسعير.',
            self::CONTENT => 'المنشورات والوظائف والرعاة والألبومات.',
            self::SETTINGS => 'بيانات الإشعارات ومركز الإشعارات.',
            self::ROLES => '⚠️ يمنح غيره صلاحيات — لكن لا يستطيع منح ما لا يملكه هو.',
        ];
    }

    public static function hint(string $ability): string
    {
        return self::hints()[$ability] ?? '';
    }

    public static function label(string $ability): string
    {
        return self::labels()[$ability] ?? $ability;
    }
}
