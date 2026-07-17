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
| **Geography** | `countries` (249 — full ISO 3166-1), `governorates` (27), `cities` (1,339), `addresses`, `locations`, `location_id_mappings` |
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

## 4. Routes map — 873 routes

| Surface | Routes | File | Guard |
|---|---:|---|---|
| Admin panel (v2 + legacy) | 486 | `routes/admin_v2.php`, `routes/admin.php` | `admin.v2` / `admin` |
| Mobile API v2 | 153 | `routes/api_v2.php` | `auth:sanctum` (+ `business`) |
| Legacy API v1 | 101 | `routes/api.php` | sanctum |
| Business owner panel | 73 | `routes/business.php` | `business.panel` |
| Web/public | 60 | `routes/web.php` | — |

- **v2 is the self-sufficient app surface**; v1 is abandoned but kept (§10).
- **`/api/v2` is fully documented** in `docs/api/openapi-v2.yaml` (129 paths /
  153 operations) and `OpenApiSpecCoverageTest` **fails the build** if you add a
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
  import feed exists. Needs a data source decision, not code.
- **BIM-14.1** — AdminV2 has almost no per-action `->can()` checks. Not an active
  vulnerability (middleware + owner scoping hold), but a defence-in-depth gap,
  and a sweep across ~69 controllers. Worth its own session; do money/fees/
  disputes/users first.
- The older AdminV2 trip-schedules blade hardcodes its own Arabic label maps
  instead of using `TripSchedule::modeLabels()` etc.
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

**Done and worth not re-litigating:** the 5-phase architecture reorg (0–5), the
7-point v2 gap list (tests, wallet↔order states, order lifecycle, duplicate
subsystems, mail, authz, docs), BIM-13 QR, BIM-3.5, the platform treasury, and
BIM-15.1 account deletion.

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

72 test files, **434 passing / 3 skipped**. Conventions:

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

---

## 12. Maintenance rule

Update the doc nearest the change, then this file if a shape moved. Counts here
are a snapshot — if you are about to quote one in a decision, re-derive it:

```bash
php artisan route:list --path=api/v2
php artisan test
```
