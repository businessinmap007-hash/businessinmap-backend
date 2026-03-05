<?php

namespace App\Support\AdminV2;

final class AdminV2PermissionMap
{
    /**
     * كل عنصر يمثل "قسم" في Admin V2
     * - ability: الصلاحية المطلوبة (من جدول abilities القديم)
     * - routes: روابط الواجهة (AdminV2)
     * - children: عناصر منيو فرعية (اختياري)
     */
    public static function sections(): array
    {
        return [
                     
            [   
                'key'     => 'users',
                'type'    => 'link',
                'label'   => 'إدارة المستخدمين',
                'route'   => 'admin.users.index',
                'active'  => 'admin.users.',
                'ability' => 'list_users',
                'icon'    => 'users',

                
            ],

            [
                'key'     => 'categories',
                'type'   => 'link',
                'label'  => 'إدارة الأقسام',
                'route'  => 'admin.categories.index',
                'active' => 'admin.categories.',
                'icon'   => 'folder',

            ],

            [
                'key'     => 'posts',
                'label'   => 'المنشورات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null, // ضع ability إن عندك
                'route'   => 'admin.posts.index',
            ],

            [
                'key'     => 'jobs',
                'label'   => 'الوظائف',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.jobs.index',
            ],

            [
                'key'     => 'sponsors',
                'label'   => 'الإعلانات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.sponsors.index',
            ],

            [
                'label' => 'ملاحظات المعاملات',
                'route' => 'admin.wallet-notes.index',
            ],

            [
                'key'     => 'subscription',
                'label'   => 'الاشتراكات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.subscriptions.index',
            ],

            // [
            //     'key'     => 'financial',
            //     'label'   => 'المعاملات المالية',
            //     'icon'    => 'zmdi zmdi-view-dashboard',
            //     'ability' => null,
            //     'route'   => 'admin.financial.index',
            // ],

            [
                'key'     => 'Wallet Transaction',
                'label'   => 'معاملات المحفظة',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.wallet-transactions.index',
            ],


            [
                'key'     => 'albums',
                'label'   => 'الألبومات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.albums.index',
            ],
            [
                'key'     => 'Booking',
                'label'   => 'الحجوزات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.bookings.index',
                'icon'  => 'calendar', // لو نظامك بيدعم icons
            ],
            [
                'key'     => 'business_service_price',
                'label'   => 'تسعير الخدمات للبزنس',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.business_service_prices.index',
            ],
             [
                'key'     => 'service_fee',
                'label'   => 'رسوم الخدمات',
                'icon'    => 'zmdi zmdi-view-dashboard',
                'ability' => null,
                'route'   => 'admin.service_fees.index',
            ],

            

            [
                'key'     => 'coupons',
                'label'   => 'أكواد الخصم',
                'icon'    => 'zmdi zmdi-accounts-outline',
                'ability' => null,
                'children' => [
                    [
                        'label' => 'مشاهدة الأكواد',
                        'route' => 'admin.coupons.index',
                        'ability' => null,
                    ],
                    [
                        'label' => 'إضافة كود خصم',
                        'route' => 'admin.coupons.create',
                        'ability' => null,
                    ],
                ],
            ],

            [
                'key'     => 'settings',
                'label'   => 'إعدادات التطبيق',
                'icon'    => 'zmdi zmdi-accounts-outline',
                'ability' => 'settings_management',
                'children' => [
                    [
                        'label' => 'الخصومات والهدايا',
                        'route' => 'admin.discounts.gifts',
                        'ability' => 'sliders_management', // عدّلها حسب الموجود عندك
                    ],
                    [
                        'label' => 'إعدادات عامة',
                        'route' => 'admin.settings.general',
                        'ability' => 'settings_management',
                    ],
                    [
                        'label' => 'البانرات الإعلانية',
                        'route' => 'admin.banners.index',
                        'ability' => 'banners_management',
                    ],
                    [
                        'label' => 'من نحن',
                        'route' => 'admin.settings.about',
                        'ability' => 'settings_management',
                    ],
                ],
            ],
        ];
    }
}
