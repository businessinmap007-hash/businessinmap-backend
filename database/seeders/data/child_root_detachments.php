<?php

/*
|--------------------------------------------------------------------------
| «احذف س من أبناء ص» — a child leaving a root it does not belong under
|--------------------------------------------------------------------------
| Owner, 2026-08-10:
|
|   «حذف آثاث وباب وشباك من ابناء الورش وحذف عفشجي من شحن وتوصيل.»
|
| A move ([[child_root_moves.php]]) says «it belongs somewhere ELSE». This says
| «it does not belong HERE», which is a different instruction and sometimes has
| no destination at all — «عفشجى» stands under one root, so removing it retires
| the trade.
|
| NOTHING IS DELETED. The master row survives, and the `category_parent_child`
| row that goes IS the undo record. What must not survive is an ACCOUNT left
| pointing at a root its child no longer hangs from: that merchant disappears
| from every screen at once. So every entry either holds no account or names
| exactly where its merchants go, and the seeder refuses to detach otherwise.
*/

return [

    /*
    | «آثاث» is a SELLER — a showroom, a company, a factory — and it keeps all
    | three of those roots. Under ورش it was the odd one out: it carried `menu`
    | where every other workshop child takes a booking, which is the shape of a
    | shop, not a bench. Its 29 workshop merchants are furniture WORKSHOPS and
    | that child now exists — «ورشة أثاث ونجارة», built this morning out of
    | تنجيد، استورجى، كوتش، مطابخ ودريسنج.
    */
    [
        'child_name_ar' => 'آثاث',
        'root_slug' => 'workshops',
        'reassign_to' => 'ورشة أثاث ونجارة',
        'why' => 'بائع أثاث لا ورشة؛ ومن يعمل بالورش هو ورشة أثاث ونجارة',
    ],

    /*
    | The doors-and-windows TRADE took شركات and المحلات this morning and stands
    | under مصانع beside them — the three roots a doors business sells from. The
    | workshop form of it has always had its own child, «نجار باب وشباك» #84,
    | which holds the three workshop accounts and keeps the sixteen door types.
    | Both under ورش said the same thing twice.
    */
    [
        'child_name_ar' => 'باب وشباك',
        'root_slug' => 'workshops',
        'reassign_to' => null, // no account stands here
        'why' => 'شكل الورشة له ابنه «نجار باب وشباك»؛ والتجارة تحت مصانع وشركات والمحلات',
    ],

    /*
    | «عفشجى» came here from ورش yesterday, and the owner has since named it
    | among the one-man benches that should be words rather than rows. It stands
    | under this root only, so this retires the child: the row survives, nothing
    | points at it.
    |
    | Its one merchant goes to «مندوب» — the individual tier of this root, beside
    | the شركة and the مكتب — which keeps him on `schedules` and findable.
    |
    | Listed under BOTH roots on purpose. On a fresh database the child starts
    | under ورش (data/categories.php) and the move that brought it here has been
    | withdrawn, so «here» depends on how far the seed has got.
    */
    [
        'child_name_ar' => 'عفشجى',
        'root_slug' => 'shipping-delivery',
        'reassign_to' => 'مندوب',
        'why' => 'المالك: يُحذف من شحن وتوصيل',
    ],
    [
        'child_name_ar' => 'عفشجى',
        'root_slug' => 'workshops',
        'reassign_to' => null,
        'why' => 'ولا يعود إلى الورش — نقلٌ لا صيانة',
    ],
];
