<?php

use App\Http\Controllers\Business\Auth\LoginController;
use App\Http\Controllers\Business\BookableItemCalendarController;
use App\Http\Controllers\Business\BookingAddOnController;
use App\Http\Controllers\Business\BookableItemController;
use App\Http\Controllers\Business\BookingController;
use App\Http\Controllers\Business\BusinessServicePriceController;
use App\Http\Controllers\Business\CatalogListingController;
use App\Http\Controllers\Business\DashboardController;
use App\Http\Controllers\Business\LocaleController;
use App\Http\Controllers\Business\MenuItemController;
use App\Http\Controllers\Business\MenuMarketCatalogController;
use App\Http\Controllers\Business\MenuPharmacyCatalogController;
use App\Http\Controllers\Business\MenuReviewController;
use App\Http\Controllers\Business\MenuItemExtraController;
use App\Http\Controllers\Business\MenuItemVariantController;
use App\Http\Controllers\Business\MenuSectionController;
use App\Http\Controllers\Business\ShareStoreController;
use App\Http\Controllers\Business\StaffController;
use App\Http\Controllers\Business\TableCallController;
use App\Http\Controllers\Business\TableController;
use App\Http\Controllers\Business\TrainingPlanController;
use App\Http\Controllers\Business\TripReservationController;
use App\Http\Controllers\Business\TripScheduleController;
use App\Http\Controllers\Business\BookingSettingsController;
use App\Http\Controllers\Business\MenuSettingsController;
use App\Http\Controllers\Business\OfferingController;
use App\Http\Controllers\Business\OrderController;
use App\Http\Controllers\Business\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Owner Panel
|--------------------------------------------------------------------------
| A scoped "mini admin" panel for business owners (type=business). Every
| screen behind business.panel is scoped to the logged-in owner's own
| business_id, so owners only ever see their own units, prices and bookings.
*/

Route::prefix('business')->name('business.')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Panel language toggle (session-stored, applied by SetPanelLocale). Needs a
    // signed-in session but no capability — changing your own display language.
    Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

    Route::middleware(['business.panel'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('offerings', [OfferingController::class, 'index'])->name('offerings.index');
        // The owner's own sequence: which offering leads his list.
        Route::post('offerings/reorder', [OfferingController::class, 'reorder'])->name('offerings.reorder');

        /*
         * إضافاتُ الحجز — نظامُ الوجبات وما شابهه.
         *
         * ما يقرّره النزيلُ وهو نفسُه فى كل غرفة، فيُكتب مرّةً على كل سطور
         * أسعار الحجز. وما يخصّ غرفةً بعينها — إطلالتُها — يبقى على شاشتها.
         */
        Route::get('booking-add-ons', [BookingAddOnController::class, 'index'])->name('booking-add-ons.index');
        Route::put('booking-add-ons', [BookingAddOnController::class, 'update'])->name('booking-add-ons.update');

        Route::get('bookable-items', [BookableItemController::class, 'index'])->name('bookable-items.index');
        Route::get('bookable-items/create', [BookableItemController::class, 'create'])->name('bookable-items.create');
        Route::post('bookable-items', [BookableItemController::class, 'store'])->name('bookable-items.store');
        Route::get('bookable-items/{id}/edit', [BookableItemController::class, 'edit'])->whereNumber('id')->name('bookable-items.edit');
        // «٦ غرف فردى و١٠ مزدوجة» — المدى كلُّه فى حفظٍ واحد.
        Route::get('bookable-items/bulk', [BookableItemController::class, 'bulk'])->name('bookable-items.bulk');
        Route::post('bookable-items/bulk', [BookableItemController::class, 'bulkStore'])->name('bookable-items.bulk.store');

        // سعرُ نوعِ الوحدة وإضافاتُه، من شاشة الوحدة نفسها.
        Route::post('bookable-items/{id}/pricing', [BookableItemController::class, 'storePricing'])->whereNumber('id')->name('bookable-items.pricing.store');

        // صورُ الوحدة. على شاشة التعديل وحدها — نموذجُ الإنشاء لا يرفع ملفات.
        Route::post('bookable-items/{id}/images', [BookableItemController::class, 'storeImages'])->whereNumber('id')->name('bookable-items.images.store');
        Route::delete('bookable-items/{id}/images/{image}', [BookableItemController::class, 'destroyImage'])->whereNumber(['id', 'image'])->name('bookable-items.images.destroy');

        /*
         * إغلاقُ الوحدة وقواعدُ سعرها.
         *
         * الجدولان مبنيّان والمحرّكُ يقرؤهما، والبابُ الوحيدُ كان فى لوحة
         * الإدارة — ومن يعرف أن الغرفة تحت الصيانة هو صاحبُ المحل لا موظّفُ
         * المنصّة. فالبابُ هنا، على شاشة الوحدة نفسها.
         */
        Route::post('bookable-items/{id}/blocked-slots', [BookableItemCalendarController::class, 'storeBlockedSlot'])->whereNumber('id')->name('bookable-items.blocked-slots.store');
        Route::delete('bookable-items/{id}/blocked-slots/{slot}', [BookableItemCalendarController::class, 'destroyBlockedSlot'])->whereNumber(['id', 'slot'])->name('bookable-items.blocked-slots.destroy');
        Route::post('bookable-items/{id}/price-rules', [BookableItemCalendarController::class, 'storePriceRule'])->whereNumber('id')->name('bookable-items.price-rules.store');
        Route::delete('bookable-items/{id}/price-rules/{rule}', [BookableItemCalendarController::class, 'destroyPriceRule'])->whereNumber(['id', 'rule'])->name('bookable-items.price-rules.destroy');

        Route::put('bookable-items/{id}', [BookableItemController::class, 'update'])->whereNumber('id')->name('bookable-items.update');
        Route::delete('bookable-items/{id}', [BookableItemController::class, 'destroy'])->whereNumber('id')->name('bookable-items.destroy');

        Route::get('prices', [BusinessServicePriceController::class, 'index'])->name('prices.index');
        Route::get('prices/create', [BusinessServicePriceController::class, 'create'])->name('prices.create');
        Route::post('prices', [BusinessServicePriceController::class, 'store'])->name('prices.store');
        Route::get('prices/{id}/edit', [BusinessServicePriceController::class, 'edit'])->whereNumber('id')->name('prices.edit');
        Route::put('prices/{id}', [BusinessServicePriceController::class, 'update'])->whereNumber('id')->name('prices.update');
        Route::delete('prices/{id}', [BusinessServicePriceController::class, 'destroy'])->whereNumber('id')->name('prices.destroy');

        Route::get('menu-settings', [MenuSettingsController::class, 'edit'])->name('menu-settings.edit');
        Route::put('menu-settings', [MenuSettingsController::class, 'update'])->name('menu-settings.update');

        // نمط الحجز وتفاصيله — ما يقرّره صاحب النشاط داخل ما فتحه له الطفل.
        Route::get('booking-settings', [BookingSettingsController::class, 'edit'])->name('booking-settings.edit');
        Route::put('booking-settings', [BookingSettingsController::class, 'update'])->name('booking-settings.update');

        /*
         * ملفُّ النشاط — اسمُه وشعارُه ونبذتُه وموقعُه ومواعيدُه.
         *
         * بلا حَجب: الأعمدةُ على `users` نفسها، وكلُّ نشاطٍ له اسمٌ وشعارٌ
         * ومواعيد مهما باع. وكانت تُكتب من لوحة الإدارة والـAPI فقط، فصاحبُ
         * المحل داخل لوحته لا يستطيع تغيير شعاره.
         */
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/hours', [ProfileController::class, 'updateHours'])->name('profile.hours');

        // Delegated staff: grant an employee scoped management of the page.
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{user}', [StaffController::class, 'update'])->whereNumber('user')->name('staff.update');
        Route::delete('staff/{user}', [StaffController::class, 'destroy'])->whereNumber('user')->name('staff.destroy');

        // Restaurant tables + their permanent QR stickers (BIM-13.3).
        // Storefront QR — "share your store" (BIM-13.4).
        Route::get('share-store', [ShareStoreController::class, 'show'])->name('share-store');

        Route::get('tables', [TableController::class, 'index'])->name('tables.index');
        Route::post('tables', [TableController::class, 'store'])->name('tables.store');
        Route::get('tables/print', [TableController::class, 'print'])->name('tables.print');
        Route::put('tables/{id}', [TableController::class, 'update'])->whereNumber('id')->name('tables.update');
        Route::delete('tables/{id}', [TableController::class, 'destroy'])->whereNumber('id')->name('tables.destroy');

        // Live dine-in table service calls (waiter / bill) — the standing board.
        Route::get('table-calls', [TableCallController::class, 'index'])->name('table-calls.index');
        Route::post('table-calls/{id}/resolve', [TableCallController::class, 'resolve'])->whereNumber('id')->name('table-calls.resolve');

        Route::get('menu-sections', [MenuSectionController::class, 'index'])->name('menu-sections.index');
        Route::get('menu-sections/create', [MenuSectionController::class, 'create'])->name('menu-sections.create');
        Route::post('menu-sections', [MenuSectionController::class, 'store'])->name('menu-sections.store');
        Route::get('menu-sections/{id}/edit', [MenuSectionController::class, 'edit'])->whereNumber('id')->name('menu-sections.edit');
        Route::put('menu-sections/{id}', [MenuSectionController::class, 'update'])->whereNumber('id')->name('menu-sections.update');
        Route::delete('menu-sections/{id}', [MenuSectionController::class, 'destroy'])->whereNumber('id')->name('menu-sections.destroy');

        // قائمتُه كاملةً: القسم ثم البند ثم أصنافه، والبندُ الفارغ معها.
        // قبل «menu» لأن الأخيرة تلتقط {id}؛ ولا معرّف في هذا المسار أصلًا،
        // فلا شيء يُوسَّع به إلى منيو غيره.
        Route::get('menu/review', [MenuReviewController::class, 'index'])->name('menu.review');

        // شاشة تعبئة الرفوف — السوبر ماركت والهايبر والمني ماركت فقط، من
        // مفردات الأصناف الجاهزة بدل الكتابة اليدوية.
        Route::get('menu/catalog', [MenuMarketCatalogController::class, 'index'])->name('menu.catalog.index');
        Route::put('menu/catalog', [MenuMarketCatalogController::class, 'update'])->name('menu.catalog.update');

        // «قاموس الأدوية» — الصيدلية فقط، بحثًا لا جدولًا (25,065 صفًّا).
        Route::get('menu/pharmacy', [MenuPharmacyCatalogController::class, 'index'])->name('menu.pharmacy.index');
        Route::get('menu/pharmacy/search', [MenuPharmacyCatalogController::class, 'search'])->name('menu.pharmacy.search');
        Route::post('menu/pharmacy', [MenuPharmacyCatalogController::class, 'store'])->name('menu.pharmacy.store');

        Route::get('menu', [MenuItemController::class, 'index'])->name('menu.index');
        Route::get('menu/create', [MenuItemController::class, 'create'])->name('menu.create');
        Route::post('menu', [MenuItemController::class, 'store'])->name('menu.store');
        Route::get('menu/{id}/edit', [MenuItemController::class, 'edit'])->whereNumber('id')->name('menu.edit');
        Route::put('menu/{id}', [MenuItemController::class, 'update'])->whereNumber('id')->name('menu.update');
        Route::delete('menu/{id}', [MenuItemController::class, 'destroy'])->whereNumber('id')->name('menu.destroy');

        // Photos for a menu item — the dish, the flat, the car. Edit-only:
        // the create form is a plain POST and would drop the files silently.
        Route::post('menu/{id}/images', [MenuItemController::class, 'storeImages'])->whereNumber('id')->name('menu.images.store');
        Route::delete('menu/{id}/images/{image}', [MenuItemController::class, 'destroyImage'])->whereNumber(['id', 'image'])->name('menu.images.destroy');

        // Variants (sizes) + extras (add-ons) for a menu item.
        Route::post('menu/{menuItem}/variants', [MenuItemVariantController::class, 'store'])->whereNumber('menuItem')->name('menu.variants.store');
        Route::put('menu/{menuItem}/variants/{variant}', [MenuItemVariantController::class, 'update'])->whereNumber(['menuItem', 'variant'])->name('menu.variants.update');
        Route::delete('menu/{menuItem}/variants/{variant}', [MenuItemVariantController::class, 'destroy'])->whereNumber(['menuItem', 'variant'])->name('menu.variants.destroy');
        Route::post('menu/{menuItem}/extras', [MenuItemExtraController::class, 'store'])->whereNumber('menuItem')->name('menu.extras.store');
        Route::put('menu/{menuItem}/extras/{extra}', [MenuItemExtraController::class, 'update'])->whereNumber(['menuItem', 'extra'])->name('menu.extras.update');
        Route::delete('menu/{menuItem}/extras/{extra}', [MenuItemExtraController::class, 'destroy'])->whereNumber(['menuItem', 'extra'])->name('menu.extras.destroy');

        Route::get('products', [CatalogListingController::class, 'index'])->name('products.index');
        Route::get('products/create', [CatalogListingController::class, 'create'])->name('products.create');
        Route::get('products/lookup', [CatalogListingController::class, 'productLookup'])->name('products.lookup');
        Route::post('products', [CatalogListingController::class, 'store'])->name('products.store');
        Route::get('products/{id}/edit', [CatalogListingController::class, 'edit'])->whereNumber('id')->name('products.edit');
        Route::put('products/{id}', [CatalogListingController::class, 'update'])->whereNumber('id')->name('products.update');
        Route::delete('products/{id}', [CatalogListingController::class, 'destroy'])->whereNumber('id')->name('products.destroy');

        // Scheduling service: the carrier publishes trip legs, then works the
        // reservation desk for them. Static paths stay ahead of the dynamic
        // {id} ones so /schedules/reservations can never be read as a leg id.
        Route::get('schedules', [TripScheduleController::class, 'index'])->name('schedules.index');
        Route::get('schedules/create', [TripScheduleController::class, 'create'])->name('schedules.create');
        Route::post('schedules', [TripScheduleController::class, 'store'])->name('schedules.store');

        Route::get('schedules/reservations', [TripReservationController::class, 'index'])->name('schedules.reservations.index');
        Route::post('schedules/reservations/{id}/confirm', [TripReservationController::class, 'confirm'])->whereNumber('id')->name('schedules.reservations.confirm');
        Route::post('schedules/reservations/{id}/complete', [TripReservationController::class, 'complete'])->whereNumber('id')->name('schedules.reservations.complete');
        Route::post('schedules/reservations/{id}/reject', [TripReservationController::class, 'reject'])->whereNumber('id')->name('schedules.reservations.reject');

        Route::get('schedules/{id}/edit', [TripScheduleController::class, 'edit'])->whereNumber('id')->name('schedules.edit');
        Route::put('schedules/{id}', [TripScheduleController::class, 'update'])->whereNumber('id')->name('schedules.update');
        Route::delete('schedules/{id}', [TripScheduleController::class, 'destroy'])->whereNumber('id')->name('schedules.destroy');
        Route::post('schedules/{schedule}/block', [TripReservationController::class, 'block'])->whereNumber('schedule')->name('schedules.block');

        // Training & nutrition plans — the trainer writing at a desk. The web
        // face of the API's own trainer side; both go through
        // TrainingPlanService, so the client is pushed the same either way.
        // `create` and `lookup` stay ahead of {id}, which is a plan number.
        Route::get('training-plans', [TrainingPlanController::class, 'index'])->name('training-plans.index');
        Route::get('training-plans/create', [TrainingPlanController::class, 'create'])->name('training-plans.create');
        Route::get('training-plans/lookup', [TrainingPlanController::class, 'lookup'])->name('training-plans.lookup');
        Route::post('training-plans', [TrainingPlanController::class, 'store'])->name('training-plans.store');
        Route::get('training-plans/{id}', [TrainingPlanController::class, 'show'])->whereNumber('id')->name('training-plans.show');
        Route::put('training-plans/{id}', [TrainingPlanController::class, 'update'])->whereNumber('id')->name('training-plans.update');
        Route::post('training-plans/{id}/exercises', [TrainingPlanController::class, 'addExercise'])->whereNumber('id')->name('training-plans.exercises.store');
        Route::delete('training-plans/{id}/exercises/{exercise}', [TrainingPlanController::class, 'removeExercise'])->whereNumber(['id', 'exercise'])->name('training-plans.exercises.destroy');
        Route::post('training-plans/{id}/meals', [TrainingPlanController::class, 'addMeal'])->whereNumber('id')->name('training-plans.meals.store');
        Route::delete('training-plans/{id}/meals/{meal}', [TrainingPlanController::class, 'removeMeal'])->whereNumber(['id', 'meal'])->name('training-plans.meals.destroy');
        Route::post('training-plans/{id}/body-reports', [TrainingPlanController::class, 'storeReport'])->whereNumber('id')->name('training-plans.body-reports.store');
        Route::delete('training-plans/{id}/body-reports/{report}', [TrainingPlanController::class, 'destroyReport'])->whereNumber(['id', 'report'])->name('training-plans.body-reports.destroy');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{id}', [BookingController::class, 'show'])->whereNumber('id')->name('bookings.show');
        Route::post('bookings/{id}/food', [BookingController::class, 'addFood'])->whereNumber('id')->name('bookings.food.add');
        Route::delete('bookings/{id}/food', [BookingController::class, 'removeFood'])->whereNumber('id')->name('bookings.food.remove');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
        Route::post('orders/{id}/food', [OrderController::class, 'addFood'])->whereNumber('id')->name('orders.food.add');
        Route::post('orders/{id}/product', [OrderController::class, 'addProduct'])->whereNumber('id')->name('orders.product.add');
        Route::delete('orders/{id}/food', [OrderController::class, 'removeFood'])->whereNumber('id')->name('orders.food.remove');
        Route::delete('orders/{id}', [OrderController::class, 'destroy'])->whereNumber('id')->name('orders.destroy');
    });
});
