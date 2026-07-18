# BIM — Final Engineering Reference (BIM-12.1)

**For the developer who just got repo access.** Docs 01–05 explain intent and
history; this one is the map of what actually exists. Every count and list below
was read out of the codebase and the `business` database, not from memory.

*Generated 2026-07-16. Figures are a snapshot; the shapes they describe are stable.*

---

## 0. Read this first — three things that will bite you

1. **The test suite runs against the real development database.** `phpunit.xml`
   has the sqlite `:memory:` lines commented out and there is no `.env.testing`,
   so tests connect to `DB_CONNECTION=mysql`, `DB_DATABASE=business`. Every test
   **must** `use Illuminate\Foundation\Testing\DatabaseTransactions`. Using
   `RefreshDatabase` will **wipe the development data**. Tests therefore reuse
   existing stable rows (a business user, a booking) and seed only what they need.
2. **`php artisan db:seed` (full) is broken**; run seeders individually
   (`--class=`). All seeders are written re-runnable (`updateOrCreate`).
3. **Do not add a fee, deposit or wallet path outside the services in §7.** The
   money core is the most carefully built part of this system (row locks,
   idempotency keys, a real ledger). Reuse it.

---

## 1. What BIM is, in one paragraph

A Laravel 10 B2B+B2C marketplace. Businesses subscribe to **platform services**
(booking, menu, delivery, retail, schedules, business offers). Every operation
charges a **service fee** split between client and business, and feeds each
party's **rating**. A **wallet** holds points/credit for deposits, guarantees and
fees — it is *not* a bank account and does *not* pay for menu orders (those are
cash on arrival). Classification runs category → child; a business's *priced item
type* is simultaneously its offer, the customer's filter, and the search index.

---

## 2. DB map — 144 tables

Grouped by what owns them. (`SHOW TABLES` count: 144.)

| Domain | Tables |
|---|---|
| **Identity & access** | `users` (3,855), `roles`, `permissions`, `abilities`, `assigned_roles`, `personal_access_tokens`, `password_reset_codes`, `password_resets`, `password_reset_tokens`, `socials` |
| **Classification** | `categories` (434), `category_children_master` (304), `category_parent_child`, `category_target`, `category_user`, `options`, `option_groups`, `category_child_option`, `option_user` |
| **Platform services** | `platform_services` (6), `category_platform_services`, `category_service_configs`, `user_platform_service`, `platform_service_item_types`, `platform_service_item_groups`, `platform_service_item_group_type` |
| **Fees** | `category_child_service_fees`, **`service_fee_rules`** (BIM-3.5), `platform_service_fee_promotions`, `user_service_fee_consents` |
| **Booking** | `bookings` (55), `bookable_items`, `bookable_item_blocked_slots`, `bookable_item_price_rules`, `bookable_allocations`, `booking_reminders`, `business_service_prices`, `business_deposit_policies` |
| **Menu / orders** | `menu_items`, `menu_sections`, `menu_item_variants`, `menu_item_extras`, `menu_carts`, `menu_cart_items`, `menu_cart_item_extras`, `orders`, `order_items`, `order_participants`, `business_menu_settings` |
| **Retail / catalog** | `catalog_products` (0 — wiped, see §10), `catalog_brands`, `catalog_manufacturers`, `catalog_units`, `catalog_attributes`, `catalog_attribute_options`, `catalog_product_attribute_values`, `catalog_product_variants`, `catalog_product_variant_attribute_values`, `catalog_product_barcodes`, `catalog_product_images`, `catalog_import_batches`, `business_catalog_listings` |
| **Delivery** | `delivery_orders`, `delivery_order_items`, `delivery_drivers`, `delivery_completions`, `driver_locations`, `drivers`, `couriers` |
| **Scheduling (6th service)** | `trip_schedules`, `trip_reservations` |
| **Money** | `wallets` (799), `wallet_transactions` (1,305), `wallet_pins`, `wallet_topups`, `wallet_note_templates`, `payments`, `payment_settings`, `deposits`, `deposit_events` |
| **Trust** | `user_guarantees`, `guarantee_levels`, `guarantee_transactions`, `operation_guarantors`, `user_operation_ratings`, `rating_outcome_events`, `operation_reviews`, `ratings`, `disputes`, `dispute_warnings`, `blocked_identities` (BIM-15.1 ban list — hashed, survives anonymization) |
| **Commercial / B2B** | `commercial_offers`, `commercial_offer_targets`, `business_partnerships`, `offer_follows`, `offer_follow_notifications`, `offer_tracking_events`, `offer_boost_packages`, `offer_boost_purchases`, `subscriptions`, `coupons` |
| **QR (BIM-13)** | `business_tables` (+ tokens carried on orders/carts) |
| **Notifications** | `app_notifications`, `notification_channel_rules`, `notification_delivery_logs`, `user_push_tokens`, `push_settings`, `notifications`, `notifications_old` |
| **Geography** | `countries` (249 — full ISO 3166-1), `governorates` (27), `cities` (1,339), `addresses` — **live**. `locations` (71, all unnamed countries), `location_id_mappings` (0) — **dead, see §14** |
| **Content** | `posts`, `albums`, `images`, `banners`, `sponsors`, `comments`, `likes`, `applies`, `services`, `products`, `product_categories`, `product_category_children`, `wishlists`, `follow_user`, `business_gifts` |
| **Messaging** | `conversations`, `messages` |
| **Legacy / scratch** | `posts_backup_images`, `temp_category_option_mapping`, `temp_unmatched_category_option_ids`, `cars`, `rides`, `target_user`, `business_client_allowlist`, `business_client_relationships`, `service_events`, `service_order_rejections`, `failed_jobs`, `migrations` |

134 migrations in `database/migrations/`.

---

## 3. Modules map — the six typed services

`platform_services` is the spine. All six are active:

| key | Arabic | deposit | What it sells |
|---|---|---|---|
| `booking` | الحجز | ✅ | Time/unit reservations (rooms, courts, halls) |
| `menu` | القائمة | — | Food/drink items with variants + extras |
| `delivery` | التوصيل | — | Moving an order to the customer |
| `retail` | التجزئة | — | Physical products off a shared catalog |
| `schedules` | الجدولة والخطوط | ✅ | Published trip legs (freight/passenger/limousine/distribution) |
| `business_offers` | العروض التجارية | — | B2B offers/reselling |

Attach a service to a category child via `category_platform_services`; configure
it per child via `category_service_configs`; a business's actual prices live in
`business_service_prices` (bespoke) or `business_catalog_listings` (retail).

**The unifying rule:** a business's priced item type *is* the offer, the filter,
and the index. Discovery (`Api/V2/DiscoveryController`) is keyed on that, not on
options.

---

## 4. Routes map — 881 routes

| Surface | Routes | File | Guard |
|---|---:|---|---|
| Admin panel (v2 + legacy) | 489 | `routes/admin_v2.php`, `routes/admin.php` | `admin.v2` / `admin` |
| Mobile API v2 | 162 | `routes/api_v2.php` | `auth:sanctum` (+ `business`) |
| Legacy API v1 | 97 | `routes/api.php` | sanctum |
| Business owner panel | 73 | `routes/business.php` | `business.panel` |
| Web/public | 60 | `routes/web.php` | — |

- **v2 is the self-sufficient app surface**; v1 is abandoned but kept (§10).
- **`/api/v2` is fully documented** in `docs/api/openapi-v2.yaml` (137 paths /
  162 operations) and `OpenApiSpecCoverageTest` **fails the build** if you add a
  route without documenting it.
- Route ordering rule: static paths before dynamic `{id}` ones.
- `VerifyCsrfToken::$except` is **empty** — CSRF is enforced on every web route.

---

## 5. Controllers map — 120 controllers

| Namespace | Count | Role |
|---|---:|---|
| `App\Http\Controllers\AdminV2` | 69 | The admin panel. One controller per screen. |
| `App\Http\Controllers\Api\V2` | 33 | The mobile app's entire surface. |
| `App\Http\Controllers\Business` | 18 | The owner's scoped "mini admin". |

**Business panel rule:** every controller scopes to `business_id = Auth::id()`
via a private `scopedX()` helper, so an owner can never touch another's row (it
404s rather than 403s — no existence enumeration). `ResolvesOwnerCatalog`
provides the shared owner/child/service helpers.

**Authorization:** `BusinessPanelMiddleware` (auth + type=business + not
suspended) gates the panel; the `business` middleware gates `/api/v2/business/*`;
`AdminV2Middleware` gates admin. Order viewing goes through `OrderPolicy`.

---

## 6. Models map — 127 models

Not worth listing flat; the ones that carry real invariants:

- **`User`** — both parties. `type` = client|business|admin. `isBusiness()` is
  the gate everything keys on.
- **`Wallet` / `WalletTransaction`** — balance + locked_balance, with a real
  ledger (`balance_before`/`balance_after`) and `idempotency_key`.
- **`Booking`** — `status` (pending→accepted→in_progress→completed) plus
  `meta` carrying the pricing snapshot and confirmation state.
- **`Order`** — `status` *and* a separate `prep_status` (accepted→preparing→
  ready). They are deliberately different columns: delivery/handover key off
  `status=pending`, and drivers see the order at `prep_status=preparing`.
- **`Deposit`** — escrow, per-side hold amounts, FROZEN/RELEASED/REFUNDED.
- **`CategoryChildServiceFee`** — the static base fee per (category, child, service).
- **`ServiceFeeRule`** — BIM-3.5 dynamic rule; owns `matches()` + `applyTo()`.
- **`TripSchedule` / `TripReservation`** — a published leg and its bookings;
  `day_of_week` is **0=Sunday .. 6=Saturday** everywhere.
- **`UserGuarantee`** — **self-only** coverage (there is no third-party guarantor
  on `user_guarantees`), plus `operation_guarantors` for the friend co-guarantor
  feature layered on top.
- **`UserOperationRating`** — the objective ledger: total/success/cancelled/
  disputed per (user, role).

---

## 7. Services map — 76 services

`app/Services`, with subfolders `Catalog/`, `Commercial/`, `Guarantees/`,
`Integrations/`, `Notifications/`, `Payments/`, `Ratings/`, `Schedules/`, `Wallet/`.

**The money core — reuse, never bypass:**

| Service | Owns |
|---|---|
| `WalletService` | Every balance movement. `lockForUpdate` + transaction + idempotency key + ledger. `deposit`/`withdraw`/`hold`/`release`/`refund`. |
| `WalletFeeService` | Resolving and charging platform fees. Consent-gated, idempotent per `booking_fee:{id}:{code}:{payer}`. |
| `ServiceFeeRuleEngine` | BIM-3.5. Selects/orders/compounds dynamic rules over the base fee. |
| `BookingDepositService` | Escrow: freeze/release/refund/external/dispute. |
| `ServiceExecutionEngine` | The booking state machine + the financial guard before start. |
| `OrderFeeSettlementService` | The platform fee on a cash menu order, taken from the business wallet at acceptance. |
| `ServiceFeeConsentEnforcer` | Buying a guarantee or posting a deposit auto-forces fee+rating consent. |
| `RatingService` | `recordForBothParties` — the only writer of the rating ledger. |

**Fee resolution — the layering (BIM-3.5):**

```
category_child_service_fees   → the base: what this service costs in general
        ↓
service_fee_rules             → policy: what THIS operation costs
                                (value, governorate, peak hour, track record,
                                 subscription). Compounds by priority.
        ↓
platform_service_fee_promotions → marketing discount, applied last so it always
                                  discounts the real policy price
        ↓
consent gate → wallet charge (idempotent)
```

Ask `ResolveServiceFeesAction::execute()` for what would be charged and
`::explain()` for why. Neither moves money.

---

## 8. Lifecycles

### Wallet
Every movement goes through `WalletService` inside a DB transaction with the
wallet row locked (`lockForUpdate`), writes `balance_before`/`balance_after`, and
carries an `idempotency_key` — replaying a key returns the first result instead
of charging twice. `hold` moves available→locked (total conserved); `release`
reverses it.

> Landmine, already fixed but instructive: `wallet_transactions.type` is an ENUM.
> A type not in the enum fails with "Data truncated" — which silently broke every
> platform fee until 2026-07-08.

`WalletService::ensureActive()` guards **every** movement (deposit, withdraw,
hold, release, refund, transfer, captureLocked), so `wallets.status = blocked` is
a real freeze in both directions, not a flag. This is what makes the deletion
grace window safe — and why `AccountDeletionService::escheatBalance()` writes its
debit directly instead of calling `withdraw()`: by then the wallet is frozen on
purpose, and unfreezing it to empty it would reopen the window the freeze closes.

### Account deletion (BIM-15.1)
```
request()  day 0   soft delete + wallet freeze + revoke tokens — NO money moves
restore()  ≤grace  account and balance both come back untouched
finalize() >grace  escheat to treasury (PURPOSE_ESCHEAT) + anonymize identity
```
Blocked while the user could still owe or be owed: open dispute (either side),
pending operations as client **or** business, an accepted `operation_guarantors`
obligation, `locked_balance > 0` (escrow is not theirs to take), a ban, or the
treasury itself. `finalize()` refuses to seize contested money — locked balance
or a dispute that appeared after the request sets `deletion_hold_reason` and
waits for a human. Swept daily by `accounts:finalize-deletions`.

The row is never hard-deleted: other people's ledger, ratings and invoices point
at that id. Anonymization empties the person out and keeps `created_at` (the
product's decision) — and **hashes the identity into `blocked_identities` first
when the account is banned**, because it destroys the very email and phone a ban
is enforced on.

### Booking
```
pending → accepted → in_progress → completed
                  ↘ cancelled / dispute
```
`ServiceExecutionEngine::moveBookingToInProgress` is the gate: both parties must
confirm, the deposit (if the policy requires one) is frozen, the execution fee is
charged once (stamped in `meta._execution_fee.charged_at`), then the status flips.
The financial guard runs before any state change.

### Deposit (single-source)
Resolved **only** from `business_deposit_policies`. `BookingDepositService::freeze`
holds the client's funds (available→locked) and creates a FROZEN deposit;
`release` returns them; `refund` returns them and marks REFUNDED. If the client's
**guarantee** covers the deposit, no wallet hold is taken at all.

> The invoice displays `Booking::depositAmount()` (the resolved/held policy
> amount), so what is shown always equals what is held.

### Order
```
status:      cart → pending → … → completed
prep_status:        accepted → preparing → ready
```
Two columns on purpose. At `preparing` the order becomes visible to drivers.
Fulfilment completes through the QR flows (handover, or the two-stage delivery
loop), not through `status` sub-states. Once `prep_status` is set the order can no
longer be cancelled — the fee is committed.

### Trip reservation (schedules)
```
pending → confirmed → completed   (+ blocked = carrier's offline hold)
       ↘ cancelled
```
Capacity is checked under a row lock on the schedule, so concurrent reservations
cannot oversell. `complete` records success for **both** parties and releases the
deposit. A cancel only marks reputation once the carrier had confirmed — a
never-confirmed pending request cancels with no rating hit.

---

## 9. Pending tasks

**Open:**
- `catalog_products` is empty (§10) — retail has no master data until a real
  import feed exists. Needs a data source decision, not code. This is also what
  blocks a **retail journey test** (§11.1): four of the six services are walked
  end to end, but retail has nothing to sell and its merchant side is web-panel
  only (`business/products`) with no API at all. `business_offers` is the other
  service still unwalked.
- **Creating a super-admin is server-only, on purpose.** The roles screen manages
  the 12 named abilities and deliberately cannot mint or unmake a wildcard
  holder — that takes a migration or tinker. Fine as-is; noted so nobody
  "fixes" it by adding a button.
- The older AdminV2 trip-schedules blade hardcodes its own Arabic label maps
  instead of using `TripSchedule::modeLabels()` etc.
- **Nearest-city-by-GPS** (the rest of BIM-11.1). `LocationHelper::detectFromLatLng`
  exists but is not wired to any v2 endpoint, so the app cannot offer "use my
  location" — only the manual pickers.
- **The legacy web address form** (`AddressController` + `StoreAddressRequest`) is
  routed but unreachable: `resources/views/addresses/` does not exist. Its id
  space is fixed so it cannot corrupt `addresses`, but it should probably be
  retired — kept for now under the keep-v1 rule.
- **Fines system** (deferred by decision). The `PURPOSE_FINE` treasury bucket and
  the `users.banned_at` + `blocked_identities` machinery already exist, so this
  needs the fraud *detection* and the appeal path, not a redesign. Two things to
  settle first: seizing a balance the instant fraud is suspected is legally
  risky (freeze → review → deduct, reusing `disputes`, is safer), and email+phone
  bans are inherently weak — numbers get recycled and addresses are free. The
  durable fraud signal is the transaction graph around `user_operation_ratings`,
  not the identity.
- **Admin surface for held deletions.** `deletion_hold_reason` is set by the
  sweep and read by nobody yet — an AdminV2 screen should list them.
- **The address book is not wired to menu checkout.**
  `POST /api/v2/cart/{business}/checkout` takes `address` as a free **string**,
  not an `address_id`. Now that addresses actually work (§14), delivery orders
  should reference a saved address rather than re-typing one — otherwise there is
  no governorate on the order and `ServiceFeeRuleEngine` has nothing to match a
  geo fee rule against.

**Done and worth not re-litigating:** the 5-phase architecture reorg (0–5), the
7-point v2 gap list (tests, wallet↔order states, order lifecycle, duplicate
subsystems, mail, authz, docs), BIM-13 QR, BIM-3.5, the platform treasury,
BIM-15.1 account deletion, and BIM-14.1 AdminV2 abilities.

---

## 10. Legacy cleanup plan

**Decision: keep v1, do not bulk-delete.** `routes/api.php` (101 routes) and the
v1 controllers are abandoned for new work but retained deliberately — parts are
still being ported. Build on `Api/V2` and `AdminV2` only.

| Item | Verdict |
|---|---|
| `Api/V1/*`, `routes/api.php` | **Keep.** Abandoned, still mined for ports. |
| Legacy `Admin` (non-V2) | **Keep.** Superseded by AdminV2; do not extend. |
| Options CRUD | **Keep — intentionally alive.** It manages the retained attribute group #12 «أنماط خدمة» and deferred catalog groups. Not dead code. |
| `catalog_products` | **Wiped on purpose.** Deduped 49,494→569, then hard-deleted; now 0, pending a rebuild on a 1:1 branch↔catalog slug mirror. Scaling needs GS1 barcodes + import feeds, not manual entry. |
| `notifications_old`, `posts_backup_images`, `temp_category_option_mapping`, `temp_unmatched_category_option_ids` | Scratch/backup tables. Safe to drop once confirmed unreferenced. |
| `user_device_tokens` | Already removed. `user_push_tokens` is the single live store. |
| `BookingEngine.php`, `BookingTestController` | Already removed (commit `aa35607`). |

---

## 11. Testing

87 test files (84 Feature / 3 Unit), **555 passing / 3 skipped**. Conventions:

- `use DatabaseTransactions` — always (see §0).
- Find-or-create existing rows; `markTestSkipped` when a fixture is absent rather
  than inventing data.
- `app(Service::class)` for services; `$this->actingAs($u, 'sanctum')` for API
  routes, `$this->actingAs($u)` for the web panels.
- `php artisan test --filter=X`.
- `seed()` is reserved on Laravel's `TestCase` — name a helper something else.

Guards that will fail the build if you drift:
- `OpenApiSpecCoverageTest` — an undocumented `/api/v2` route.
- `WorldCountriesSeederTest` — the country list must stay exactly ISO 3166-1.
- `AuthorizationTest` — the `/business/*` gate.

### 11.1 Journey tests, and why they are a different kind of test

Four services are now walked end to end the way the app walks them:

| Test | Covers |
|---|---|
| `DiscoveryJourneyTest` | launch → categories → specialties → discovery → book → carrier accepts |
| `MenuOrderJourneyTest` | browse menu → cart (variants + extras) → checkout → the kitchen sees it |
| `DeliveryJourneyTest` | order → kitchen → driver takes it → pickup scan → delivery scan → ledgered |
| `SchedulesJourneyTest` | carrier publishes a leg → passenger searches → reserves → rides → both rated |

They exist because BIM-11.1 proved that *"has passing tests" is not "works"*:
the old `AddressApiTest` was green for months while creating an address was
**impossible**. The test invented its own ids using the same wrong assumption as
the code, and those invented ids happened to satisfy the very constraint the
real ones could not.

So the rule, and the whole point:

> **Every id the client uses must come out of a previous API response.**

Never `$item->id` from a seeded model — always the id the app would actually be
holding at that moment. If the app cannot discover it, the test cannot proceed,
**and that is the bug being hunted**. Seeding the merchant's own setup directly
is fine: that is the merchant's journey, not the customer's.

This discipline paid for itself immediately. It found a second bug of exactly
the BIM-11.1 shape: `discovery/filters` and `discovery/businesses` both *require*
`child_id`, and no v2 endpoint returned one — 434 categories and 304 specialties
in the database, and the app could not reach a single business. Nothing caught it
because every existing test handed itself a `child_id` straight out of the DB,
which a real client cannot do. Fixed by `Api/V2/CategoryController` (§15).

### 11.2 Landmine: Laravel caches the authenticated user per test method

Swapping the `Authorization: Bearer` header does **not** re-authenticate. The
auth manager resolves the user once and caches it for the rest of the test
method, so the **first** identity silently sticks and every later request is
still that user. A journey test that switches sides without clearing it asserts
nothing — it will happily "prove" the restaurant can see the order while in fact
still asking as the customer.

```php
private function actingWithToken(string $token): self
{
    $this->app['auth']->forgetGuards();   // not decoration

    return $this->withHeader('Authorization', 'Bearer ' . $token);
}
```

Confirmed in isolation: client token → `200 /cart`; swap to the business token →
`403 /business/orders`; after `forgetGuards()` → `200`. Any test that changes
actor mid-method needs this.

---

## 12. Maintenance rule

Update the doc nearest the change, then this file if a shape moved. Counts here
are a snapshot — if you are about to quote one in a decision, re-derive it:

```bash
php artisan route:list --path=api/v2
php artisan test
```

---

## 13. AdminV2 authorization (BIM-14.1)

Two layers, and every route carries both:

| Layer | Question | Where |
|---|---|---|
| `admin.v2` middleware | are you an admin at all? | `AdminV2Middleware` |
| `can:<ability>` | *which* admin are you? | `routes/admin_v2.php` |

The vocabulary is `App\Support\AdminAbility` — 11 abilities named after jobs, not
screens. Bouncer was already installed but its abilities (`products_management`,
`sliders_management`, `home_settings`) are v1-era and name nothing on this
surface; they are left alone, not reused.

**`admin.money` is the axis.** It covers the wallet screens *and* the
money-moving actions that live inside other domains — resolving a dispute by
refunding, releasing a booking deposit, unlocking a guarantee to balance, and the
Fawry credentials form. Those require MONEY **in addition to** their own domain
ability, which is what lets a support agent work the dispute queue all day
without being able to pay anyone out. Triage and `resolve/no-action` move
nothing, so they stay on `admin.disputes` alone.

`AdminAbilityCoverageTest` is the load-bearing part: it walks the **router**, not
the route file, and fails if any route carrying `admin.v2` lacks a `can:`. That
is how the three routes hiding in `AppServiceProvider::registerAdminV2ExtraRoutes()`
were found. No allowlist exists or is needed — login/logout/payment-callback sit
outside the `admin.v2` group and are filtered out by construction.

> Landmine: **route-model binding runs before `can:`** (`SubstituteBindings` is in
> the `web` group; `can:` is route middleware). A 403 test against a made-up id
> gets 404 and proves nothing — use a real row.

> The `*` wildcard passes every check including abilities that do not exist yet.
> Migration `2026_08_06_000000` granted it to the human admins that existed when
> enforcement landed, because one of them had **zero** abilities and would have
> lost the panel — with no UI to grant itself anything back. The treasury is
> excluded: `type=admin` only so it is not a trading business.

### The roles screen — إعدادات التطبيق → صلاحيات المشرفين

`admin/admin-roles`, guarded by `admin.roles`, its **own** ability rather than
`admin.settings`: whoever can hand out MONEY effectively has MONEY, so bundling
it with the push-credentials screen would have quietly made SETTINGS equal to
everything.

Rules live in `AdminAbilityService`, not the controller — they are the security
boundary and belong somewhere testable without an HTTP request:

- **You can only grant what you hold.** This is what makes `admin.roles` safe to
  delegate at all; without it, ROLES silently equals every ability at once.
- **You cannot edit yourself** — closes self-escalation and self-lockout in one
  rule.
- **Abilities outside your scope survive an edit.** A disabled checkbox posts
  nothing, which would otherwise read as "revoke it".
- **Wildcards are untouchable here.** The UI cannot mint or unmake a root, and
  the last super-admin cannot be stripped through a web form.
- The treasury is not listed: it holds money, not powers.

The sidebar hides what the admin cannot open, reading each item's required
ability **off the route's own `can:` middleware** — a second copy of the mapping
would drift.

> The dashboard was the hole in the money boundary: it summed platform fees and
> listed real transactions for anyone who could open the panel, so gating the
> wallet screens alone would have moved the leak rather than closed it. The
> money is now not even queried unless the viewer holds `admin.money`.

---

## 14. Geography: `locations` is dead, `countries`/`governorates`/`cities` is live (BIM-11.1)

Two parallel geo systems existed. This is the verdict, so nobody re-litigates it:

| | Rows | Verdict |
|---|---:|---|
| `countries` | 249 (full ISO 3166-1, named, flags) | **Live.** Source of truth. |
| `governorates` | 27 | **Live.** |
| `cities` | 1,339 | **Live.** |
| `locations` | 71 — all `type=country`, **`name_ar` AND `name_en` empty on every row**, zero governorates, zero cities | **Dead tree.** Never populated. |
| `location_id_mappings` | 0 | Dead. |

Everything written in the last year already read the live tables: the v1 dropdown
API (`/api/v1/{countries,governorates,cities}`), the scheduling service, and the
BIM-3.5 fee-rule admin. Only the address book pointed at `locations` — in four
places at once, which is why **`addresses` had zero rows and no delivery could be
ordered**:

1. **A foreign key** on all three columns → `locations(id)`. The floor under the
   bug: even with everything else fixed, the insert died on the constraint.
2. `Api\V2\AddressController` validated `exists:locations,id`, so
   `governorate_id=1` (القاهرة) was **rejected** (locations starts at id 2) while
   `governorate_id=2` **passed by matching a country**.
3. `Address` model related country/governorate/city to `Location`, so
   `$address->city` could only ever be null.
4. The legacy web form looked up `locations` for `name_en='Egypt'` in a table
   where every name is empty → always 500. Its view does not exist either.

> `ServiceFeeRuleEngine` reads `addresses.governorate_id` raw and compares it to
> ids the fee-rule admin took from `governorates`. Two id spaces, compared as
> one — latent only because no address could exist. Enabling addresses without
> fixing this would have activated it.

Migration `2026_08_07_000000` repoints the foreign keys and **throws if
`addresses` is non-empty**, because the no-data-migration argument only holds
while the table is empty.

`locations` itself is left in place: v1 controllers still reference it, and the
decision to keep legacy code stands. Do not build on it.

> The pre-existing `AddressApiTest` passed while all of this was broken: it
> posted the *same id* as both `governorate_id` and `city_id`, because it encoded
> the same wrong assumption as the code. A test can only catch what it does not
> also believe.

---

## 15. Classification: the front door (root → specialty → discovery)

Discovery is keyed on `child_id`, and until `Api\V2\CategoryController` existed
**no v2 endpoint returned one**. `discovery/filters` and `discovery/businesses`
both require it, so a real client could not reach a single business — the same
shape as the address bug in §14: a required parameter with no discovery path.
Found by `DiscoveryJourneyTest` (§11.1), not by reading code.

The structure, read out of the data rather than assumed:

- **21 root categories** — `categories.parent_id = 0`. **Zero, not NULL**; nothing
  in this table uses NULL, and a `whereNull('parent_id')` returns nothing.
- Roots link to specialties in `category_children_master` **through**
  `category_parent_child`. All 418 links hang off roots; the `categories`
  self-tree below a root carries none, so it is **not** part of this path.
- `child_id` is a `category_children_master.id` — that is what discovery matches.

So the app's path is two hops, and it needs both:

```
GET /api/v2/categories                        → root categories
GET /api/v2/categories/{id}/specialties       → each `id` IS discovery's child_id
GET /api/v2/discovery/businesses?child_id=…
```

Both are public — browsing what exists must not require an account.

Two things worth keeping straight:

- **A business is only findable when *both* are true**: `users.category_child_id`
  is set **and** an active `business_service_prices` row exists for that child. A
  price row alone is invisible. The `businesses` count on each specialty mirrors
  exactly what `DiscoveryController` searches, so the app can tell a dead end
  before the customer taps it (`?sellable=1` drops them).
- `categories.per_month` / `per_year` are the **abandoned subscription pricing**
  and are deliberately not exposed. `DiscoveryJourneyTest` asserts they never
  appear in the response.

## 16. Jobs (2026-07-18): a business posts a vacancy, a client applies

v1's Jobs was fully broken: `Api\V1\JobController` referenced `App\Models\Job`,
`App\Company`, `App\Product` — **none exist**; every method fatal-errors.
`Api\V1\ApplyController` was never routed and also broken (`User::applies()`
didn't exist). Only one part was real: `posts` with `type='job'` (47 live
posts) + the `applies` table (143 live applications) — `AdminV2\JobPostController`
already used this shape, but with none of the fields a real job posting needs
and no applicant-management screen.

**Deliberately not a platform service.** A job posting has no operation being
executed, no fee split, no rating — it's an ad + applications, closer to
Offers than to Booking/Menu/Delivery. Extended `posts` (migration
`2026_08_08_000000_add_job_fields_to_posts`) rather than inventing a parallel
table, keeping the 47 real historical posts intact:

```
posts: + category_id, category_child_id (nullable FKs — the browse taxonomy)
       + salary       (STRING, on purpose — often "يحدد بعد المقابلة", not a number)
       + requirements (text)
       + interview_starts_at (when applications/interviews open;
                               expire_at, already on the table, is when the ad closes)
```

**Visibility rule, enforced in `Api\V2\JobController`, not left to the client:**
the public sees a job and `applicants_count`; only `GET /jobs/{post}/applicants`
(gated on `post.user_id === auth id`) returns applicant identities. Fixed a
real bug found along the way: `User` had no `applies()` relation at all — v1's
`PostResource::isApplied` called it on every authenticated `get/posts` request
and would throw. Added it; `Apply::$fillable` was also missing `user_id`.

`GET /jobs/categories` — only categories/specialties with an open (active,
unexpired) job, counted (parent total = sum of its children, matching the
shape the owner specified: `مصانع=20` with `موبيليات=5 / دهانات=6 / حلويات=4 /
مفروشات=5`).

`AdminV2\JobPostController` gained the new fields plus `GET jobs/{post}/applicants`
(oversight, read-only) — the gap the v1 audit flagged. Fixed a second real bug
while wiring it: `Route::resource('jobs', ...)` derives the parameter `{job}`
by default, but every `jobs/*` blade view (already, before this change) calls
`route(..., ['post' => ...])` — every generated link (index→show, the delete
modal, show→edit) has silently 500'd since the AdminV2 controller was written.
Fixed with `->parameter('jobs', 'post')`. Caught by rendering the screens in
`AdminV2JobsScreensTest`, not by reading the routes.

**Second slice (2026-07-18): hiring loop + follow-and-notify.**
- `POST /jobs/{post}/applicants/{apply}/approve` — the posting business accepts
  one applicant (`applies.approved_at`), idempotent, notifies the applicant
  (`job_application_approved`). Does **not** close the job — a business may
  hire several. `POST /jobs/{post}/close` deactivates it separately.
- `GET /jobs/mine/stats` — the business counters: `jobs_posted`, `jobs_open`,
  `applicants_total`, `approved_total`.
- **Follow a job field → live push.** `job_follows` (own small table — jobs have
  no price/audience axis, so it does *not* reuse `offer_follows`): a user
  follows a whole root `category_id` or one `category_child_id`. On
  `JobController::store`, `JobFollowMatchingService` finds matching active
  follows (child match, or root match with child null), skips the poster, and
  fires the `job_posted` event through the standard
  `NotificationDispatcherService` (in-app always, Firebase when configured).
  Two new keys added to `NotificationChannelRule::defaultEventKeys()`:
  `job_posted`, `job_application_approved`. `GET/POST/DELETE /jobs/follows`.

**Third slice (2026-07-18): platform counters + follows oversight.**
- `GET /jobs/stats` — the same four counters aggregated over the whole
  platform, plus `businesses_hiring`. **Public**, and safe to be: these are
  aggregates that name nobody, so the visibility rule above is untouched.
  `/jobs/mine/stats` stays the authenticated per-business view.
- `AdminV2\JobFollowController` (`admin/job-follows`, CONTENT ability) — the
  counter cards, the follows list, and **most-followed fields next to that
  field's open-job count**. That pairing is the point: many followers + zero
  open jobs is demand with no supply, i.e. a category worth selling into.
  Read-only by design — a follow is the user's own subscription, managed from
  the app; an admin observes it, it does not edit it for them. Registered on
  its own `job-follows` path because the `jobs` resource above declares an
  unconstrained `GET jobs/{post}` that would otherwise swallow `jobs/…`.

A live check of `/jobs/stats` returns `jobs_posted=47, applicants_total=143,
jobs_open=0` — **`jobs_open=0` is correct, not a bug**: all 47 historical posts
are `is_active=1` but every one expired (latest `expire_at` is 2023-06-13), so
the open-jobs scope rightly excludes them.

Guarded by `JobsApiTest` + `JobFollowsAndApprovalApiTest` + `JobsPlatformStatsTest`
(the v2 API, counters asserted as **deltas** — the dev DB already holds the 47
posts and 143 applications) and `AdminV2JobsScreensTest`.

---

## 17. Posts (2026-07-18): the feed ported to v2, and the asset-URL bug

### The asset bug that made the panel look empty

`AppServiceProvider::configureUrl()` called `URL::forceRootUrl(config('app.url'))`
on **every request**. With `APP_URL=http://127.0.0.1:8000`, opening AdminV2 at
`http://localhost/testing/public/admin/...` still emitted
`http://127.0.0.1:8000/...` from `asset()` — so the stylesheet, the logo and
every post image were fetched from a second server. It only appeared to work
because `php artisan serve` happened to be running on :8000; with that stopped
the backend renders unstyled with blank images, which is exactly the reported
"the images have settings but don't show in the backend".

Now forced only when `runningInConsole()` (queued mail and commands have no
request to derive a root from) or in `production` (where APP_URL *is* the
public origin, and deriving from the request would expose generated links to a
poisoned Host header). Verified by curling `/admin/login` on both origins: each
now emits its own host, and both resolve 200.

This is the same landmine as the relative-AJAX rule (§ AdminV2 conventions) —
one cause, two symptoms. **Never assume an absolute URL generated by this app
matches the origin the user is browsing.**

The posts list also never had an image column at all; it now shows a thumbnail
(main image, falling back to the first gallery row, since most legacy rows only
ever got gallery images).

### The v2 port

v1's `Api\V1\PostController` is routed and live, and broken:

| Symptom | Cause |
|---|---|
| authenticated feed 500s | `User::byToken()` does not exist — confirmed against the running server |
| the audience helper throws | `getTargetsAndFollowersBusiness()` calls `followers()`, `categoryFollows()`, `targetsReverse()`, none of which exist on `App\Models\User` |
| guest feed degrades with table size | `Post::get()` — the whole table into PHP — to sort by distance |
| ~8 queries per row | `Posts\PostResource` re-reads the viewer with `User::whereApiToken()` inside four separate fields, and counts likes/applies/comments one query each |
| images never worked | `store()`/`update()` assign `$request->images` *strings* onto `Image->image`, and `Api\V1\ImageController` — which was to produce those strings — is imported in `routes/api.php` and **never routed** |

`Api\V2\PostController` rebuilds it: paginated in SQL, distance ordering via
`ORDER BY` haversine, one reaction query per page, and real multipart uploads.

**The audience rule is preserved, not invented.** The four pivot tables the v1
helper meant to read are real and populated — `category_target` (3153),
`follow_user` (3209), `category_user`, `target_user` (703) — so
`PostAudienceService` reads them directly instead of through the missing
relations. Faithful to v1: targeted/followed authors only, never your own
posts, and an account with no audience gets an **empty feed, not the whole
table**. Verified live: a guest sees 833 posts, user 1727 (following 202
accounts) sees 199.

`ImageUploadService` is the first file handling in `Api/V2` — there was none
(no `hasFile`, no `store`, no `move` anywhere under the namespace). It writes
to `public/files/uploads`, the same directory AdminV2 and every legacy row use,
stores **relative** paths, never reuses the client's filename (attacker
controlled — can carry separators or a second extension), and refuses to delete
anything outside its own directory.

Two deliberate departures from v1:
- **Uploading images appends**; wiping the gallery needs `replace_images=true`.
  v1 destroyed the whole gallery on any update that carried an image.
- `POST /posts/{post}` for edits, not PUT — PHP does not parse multipart
  bodies on PUT, so an image edit would arrive empty.

`POST /posts/{post}/react` finally makes the `likes` table writable; it has
existed all along with no endpoint able to touch it, so the feed showed counts
nobody could change.

Guarded by `PostsApiV2Test` (14). Note its `tearDown`: uploads write real files
and `DatabaseTransactions` rolls back the database but **not the filesystem**,
so every path a test creates is deleted explicitly.

---

## 18. Comments (2026-07-18): the visibility rule, and the write path that never existed

Comments could only be **read**. `Api\V1\CommentController::store()` and
`commentReplies()` are written but **never routed** — only the two read methods
are — so the API listed a `comments_count` nobody could add to. (Its
`commentList()` is dead outright: it queries `commentable_id` and `is_agree`,
neither of which is a column on `comments`.) The 81 existing rows came from the
old app, not the API.

### The rule

`comments.status` is `public|private`. Private means "between me and the
business" — a question you do not want the rest of the feed reading.

| Reader | Sees |
|---|---|
| the post's author | every comment on their post |
| any other signed-in reader | public comments **+ their own private ones** |
| a guest | public only |

`CommentVisibilityService` owns this as a **query scope**, so paging stays
correct. v1 applied it inline in two controller methods by fetching both sets
and `merge()`-ing them in PHP, and its private branch filtered on
`['parent_id' => 0, 'user_id' => $user->id]` — so a user's **own private
replies were hidden from the user who wrote them**. Regression-tested.

Two consequences worth knowing:

- **A reply can never be more visible than its parent.** Replying to a private
  comment always yields a private reply, whatever `status` the client asks for;
  otherwise a public reply would expose the private thread it hangs under.
  `GET /comments/{comment}/replies` 404s when the parent is not readable by you.
- **`comments_count` on a post counts PUBLIC top-level comments only** — one
  uniform, honest figure. Counting private ones would advertise a number most
  readers cannot open, and a per-viewer count would cost a query per feed row.

### Moderation

Deleting is allowed for the comment's author **or the post's author** (you
moderate your own thread), and takes the replies with it — orphaned replies
would be unreachable. Editing is author-only: the post owner moderates, but
does not rewrite somebody's words.

Notifications go through `NotificationDispatcherService` like everything else —
new keys `post_commented` and `comment_replied` in
`NotificationChannelRule::defaultEventKeys()` — **not** v1's
`$post->notifications()->create()` against the legacy morph table. Nobody is
ever notified of their own action.

Verified live against the real data: on post 6 (4 public + 3 private) a guest
sees 4 and the post owner sees 7.

Guarded by `CommentsApiV2Test` (14), most of it about who can see what.
