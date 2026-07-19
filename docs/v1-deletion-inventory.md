# v1 Deletion Inventory (Slice A)

Prepared 2026-07-19, **executed same day** — Tier 1 is deleted and the full
test suite is green (646 passed / 3 skipped / 0 failed). The app is offline with
no live traffic, so removing v1 was safe.

## What actually happened (two things the plan didn't foresee)

1. **`routes/api.php` loaded v2.** Its last line was `require __DIR__.'/api_v2.php'`,
   so deleting api.php would have taken the entire v2 API with it. Fixed by
   loading `routes/api_v2.php` directly in `RouteServiceProvider` (same `api`
   middleware + `api` prefix), then deleting api.php.
2. **`routes/web.php` still had a legacy `administrator` group** pointing at
   `Admin\HomeController` + `Admin\BusinessController` (both now deleted). Its
   `admin.dashboard` name is already served by AdminV2's DashboardController and
   its `businesses` resource had no callers, so the whole block was removed.
   (This is why `route:list` first blew up after deletion.)

Also: `Auth::routes()` was already present in `web.php`, so nothing needed
relocating there; and `fawry.success` turned out to have no live caller (the
one apparent match in `Libraries/Main.php` was `fawry-success-payment`, a
different string), so it was dropped, not moved.

Two test files were v1 regression cover and went with the surface they tested:
`tests/Feature/LegacyApiSecurityAuditTest.php` (deleted entirely) and the eight
`/api/v1/deposits` methods inside `DepositsAuthorizationTest.php` (trimmed; its
five v2 methods stay).

---

_Original review list below (Tier 1 all deleted; Tiers 2–3 unchanged)._

Three tiers by confidence:
- **Tier 1 — DELETE now.** Pure v1, zero v2 references. This is slice A.
- **Tier 2 — YOUR DECISION.** The customer *website* frontend (v1-era but a
  separate surface from the mobile API). Listed, not scheduled for deletion.
- **Tier 3 — KEEP.** Current code that merely lives near v1.

---

## Tier 1 — DELETE now (verified safe)

### 1a. `app/Http/Controllers/Api/V1/` — 53 files (whole directory)

```
AlbumController, ApplyController, BusinessController, CartController,
CategoryController, ChatController, CitiesController, CommentController,
CompaniesController, ConversationsController, CouponController, CourierController,
DeliveryController, DepositController, DriverLocationController, FavoritesController,
FinancialController, FinancialControllerOld, FollowController, ForgotPasswordController,
ImageController, ImagesController, JobController, LikeController, LikesController,
ListsController, LocationController, LocationDropdownController, LoginController,
MenuController, NotificationController, NotificationsController, OffersController,
OrderController, OrdersController, PaymentController, PostController, ProductsController,
ProfileController, RatesController, RegistrationController, ReportsController,
ResetPasswordController, RideController, SearchController, SettingsController,
SponsorController, SupportsController, TargetController, TransactionController,
UsersController, WalletController, WalletPinController
```

### 1b. `app/Http/Controllers/Admin/` — 30 files (whole directory)

```
AbilitiesController, AlbumController, Auth/ForgotPasswordController,
Auth/LoginController, Auth/RegisterController, Auth/ResetPasswordController,
BanksController, BannerController, BusinessController, CategoriesController,
ClientController, CommentController, CouponController, GiftController,
HomeController, JobController, LocationController, LoginController, OfferController,
OptionController, PostController, ProductController, RolesController,
SettingsController, SliderController, SponsorController, SupportsController,
TransactionController, UsersController, VendorController
```

### 1c. `resources/views/admin/` — entire directory (141 blades)

Legacy admin-panel views. AdminV2 (`resources/views/admin-v2/`) fully replaces
them. Subdirectories: abilities, albums, auth, banners, categories, clients,
comments, coupon, coupons, emails, home, jobs, layouts, locations, menu_items,
menus, notifications, offers, options, posts, products, roles, settings,
sliders, sponsors, transactions, users.

### 1d. Dead translation models — 6 files

Their DB tables do **not exist** and **no code references them** (confirmed).
Pure dead weight — this is the "get rid of `AddressTranslation`" you asked for.

```
app/Models/AddressTranslation.php
app/Models/AlbumTranslation.php
app/Models/BankTranslation.php
app/Models/FaqTranslation.php
app/Models/PostTranslation.php
app/Models/ProductTranslation.php
```

### 1e. Route wiring changes (edits, not deletions)

- **`routes/api.php`** — DELETE the whole file. It wires only `Api\V1\*`
  (the single "V2" mention is a comment). Remove its loader line in
  `app/Providers/RouteServiceProvider.php:22`.
- **`routes/admin.php`** — remove every `Admin\*` route. A few non-Admin
  fragments live here and must be **relocated, not lost**: `fawry/success`
  (name `fawry.success`), `Auth::routes()` (line 190), and the commented
  `LanguageController` line. I'll move the keepers into `web.php` (or delete
  `Auth::routes()` if the customer site goes too — depends on Tier 2). Then
  drop its loader at `RouteServiceProvider.php:24`.

---

## Tier 2 — YOUR DECISION (customer website frontend, v1-era)

A separate surface from the mobile API: the old public website (Blade pages).
These return legacy site views (`home.index`, `auth.login`, `products.index`,
`offers.index`, …). Confirm whether the website is being retired too.

### Root controllers (old site)

```
AddressController, AlbumController, CategoryController, ForgotPasswordController,
HomeController, LoginController, NotificationsController, OfferController,
PageController, ProductController, ProfileController, RateController,
RegistrationController, ResetPasswordController, WishlistController
```

### Their views (candidate)

`resources/views/`: home, auth, categories, products, offers, cart, wishlists,
profile, pages, welcome*, business-profile.blade.php — plus `routes/web.php`'s
customer routes. **Not touching any of these without your word.**

---

## Tier 3 — KEEP (do not delete)

- `app/Http/Controllers/Controller.php` — base class every v2 controller extends.
- **v2 web companions** (strong v2 signals): `DeliveryWebController`,
  `HandoverWebController`, `SharedCartWebController`, `BusinessProfileWebController`
  — the QR delivery/handover + shared-cart landing pages.
- `FilesController` (serves uploaded files), `LanguageController` (locale switch).
- Everything under `AdminV2/`, `Api/V2/`, `app/Services/`, the business panel
  (`routes/business.php` + `resources/views/business/`).

---

## Explicitly NOT in slice A (own pass, needs per-item verification)

- **v1-only models.** A heuristic flags ~25 models unreferenced by v2 code
  (Bank, Banner, Car, Ride, Courier, Conversation, Coupon, Faq, Location,
  Profile, Wishlist, CatalogProduct, the Cart* / MenuCart* family, DeliveryOrder*,
  DriverLocation, Orderdetail, Social, ServiceOrderRejection, BusinessGift…).
  These need individual checks (relations, blades, migrations, seeders,
  factories) before deletion — some may be reachable indirectly. Deferred to a
  dedicated model-cleanup slice.
- **Legacy `lang/` files.** Kept — `resources/lang/en/admin.php` is the *pattern*
  we're emulating for v2 i18n (slice C). Cleanup of truly-dead lang files
  (`web_old.php`, `menu.blade.php`, `Archive.zip`) can ride along with C.

---

## Verification plan after deletion

1. `composer dump-autoload` — no missing-class errors.
2. `php artisan route:list` — resolves with no reference to deleted controllers.
3. `php artisan route:cache && php artisan route:clear` — still succeeds.
4. `php artisan test` — full suite green (was 664 passed / 4 skipped / 0 failed).
