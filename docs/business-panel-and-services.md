# Business Panel & Services — Implementation Reference

A practical map of the business-owner panel and the services/pricing system, as
actually built. For the *why* (design decisions), see
[services-blueprint.md](services-blueprint.md); for the category data model see
[categories.md](categories.md).

---

## 0. Big picture

There are now **two panels**, separated by audience:

- **AdminV2 (`/admin`)** — superadmin. Defines what is *possible*: platform
  services, item types, **branches**, and what each subcategory may offer.
- **Business owner panel (`/business`)** — the owner manages only their own
  offering: units, prices, menu, bookings, orders. Everything is scoped to
  `business_id = auth id`.

A booking can act as a **unified invoice**: a dine-in table booking carries food
lines (from the owner's menu) and the total combines the table charge + food +
deposit. Standalone menu orders (delivery/pickup) exist without a booking.

---

## 1. Business owner panel (`/business`)

### 1.1 Auth & isolation

| Piece | File | Notes |
|-------|------|-------|
| Gate middleware | `app/Http/Middleware/BusinessPanelMiddleware.php` (alias `business.panel`) | authenticated + `type=business` + not suspended, else → `business.login` |
| Login | `app/Http/Controllers/Business/Auth/LoginController.php` | web session; rejects non-business accounts |
| Routes file | `routes/business.php` | registered in `app/Providers/RouteServiceProvider.php` under `web` |
| Layout | `resources/views/business/layouts/master.blade.php` | reuses `public/admin-v2/css/admin.css` |

Everything behind `business.panel` uses `business_id = Auth::id()` (a business
owner **is** a `User` of type `business`; their `category_child_id` drives the
catalog).

### 1.2 Shared scoping — `ResolvesOwnerCatalog`

`app/Http/Controllers/Business/Concerns/ResolvesOwnerCatalog.php` (trait used by
the units & prices controllers):

- `businessId()` → `Auth::id()`
- `childId()` → the owner's `category_child_id`
- `servicesForChild()` → only services the owner's child offers (active
  `category_platform_services`), incl. `supports_deposit`
- `allowedTypesByService(services)` → `[serviceId => [{key,label}]]`, restricted
  by `category_service_configs.allowed_item_types` when configured
- `assertAllowed(serviceId, itemType)` → 422 if outside the owner's catalog
  (re-validates posted select values — never trust the client)

### 1.3 Screens

| Screen | Controller | Route name prefix | What it does |
|--------|-----------|-------------------|--------------|
| Dashboard | `Business\DashboardController` | `business.dashboard` | owner's own counts |
| My Units | `Business\BookableItemController` | `business.bookable-items.*` | inventory CRUD (code/capacity/qty/active), **no price** |
| My Prices | `Business\BusinessServicePriceController` | `business.prices.*` | price + deposit + discount + **charge mode** per (service, type) |
| My Menu | `Business\MenuItemController` | `business.menu.*` | menu items CRUD (name/price/desc/**section**/active/sort) + inline **variants & extras** management |
| Menu Sections | `Business\MenuSectionController` | `business.menu-sections.*` | named groups (مقبلات/رئيسي/حلويات) that organise the menu |
| My Products | `Business\CatalogListingController` | `business.products.*` | list catalog masters with own price/stock (`business_catalog_listings`); **retail-scoped** |
| My Bookings | `Business\BookingController` | `business.bookings.*` | list + show; **add/remove dine-in food** + live unified invoice |
| Menu Orders | `Business\OrderController` | `business.orders.*` | standalone delivery/pickup orders + food + total |

**My Products retail scoping.** `CatalogListingController` uses
`ResolvesOwnerCatalog` and the retail mirror: `retailScope()` reads the child's
allowed retail item-type keys → `product_category_children.slug` →
`catalog_products.product_category_child_id`. `productLookup`/`store` restrict to
that id set (owners of non-retail children get 403); `store` re-applies the
filter in the `catalog_product_id` exists-rule so a crafted id can't bypass the
picker. See [retail-branches-taxonomy.md](retail-branches-taxonomy.md).

**Menu sections + variants/extras.** `menu_items.menu_section_id` → `menu_sections`
(per-business, migration `2026_07_14_000000`; legacy `category_id` untouched). The
owner manages sections on their own screen and picks one per item; variants
(`menu_item_variants`, sizes) and extras (`menu_item_extras`, add-ons) are managed
inline on the menu-item edit page (`business.menu.variants.*` / `business.menu.extras.*`,
scoped to the owner's items).

**Customer menu browse.** `GET /api/v2/discovery/menu/{business}` (public,
`Api\V2\MenuDiscoveryController`) returns the active menu grouped by sections,
each item with `base_price`, `variants` (price via `MenuItemVariant::resolvePrice`)
and `extras`. The customer then adds to the cart with optional `size_id` + `extras[]`
(`POST /api/v2/cart/items`); `CustomerCartService` prices the line server-side
(variant + Σ extras), stores `order_items.size_id`/`addons`, and merges only lines
with an identical selection signature (size + sorted extra ids). See
[menu-customer-experience.md](menu-customer-experience.md).

All views live under `resources/views/business/<screen>/`.

### 1.4 Route reference (`/business`)

```
GET  /business/login · POST /business/login · POST /business/logout
GET  /business                                   business.dashboard

GET/POST/…  /business/bookable-items[/{id}...]   business.bookable-items.{index,create,store,edit,update,destroy}
GET/POST/…  /business/prices[/{id}...]           business.prices.{index,create,store,edit,update,destroy}
GET/POST/…  /business/menu[/{id}...]             business.menu.{index,create,store,edit,update,destroy}

GET  /business/bookings                          business.bookings.index
GET  /business/bookings/{id}                     business.bookings.show
POST /business/bookings/{id}/food                business.bookings.food.add
DEL  /business/bookings/{id}/food                business.bookings.food.remove

GET  /business/orders · GET /business/orders/create · POST /business/orders
GET  /business/orders/{id}                        business.orders.show
POST /business/orders/{id}/food · DEL /business/orders/{id}/food
DEL  /business/orders/{id}                         business.orders.destroy
```

---

## 2. Services & pricing

### 2.1 Item-type branches

Item types are grouped into **branches** under a service (e.g. under `booking`:
hotel / clinic / sports / restaurant_table / training).

- Table `platform_service_item_groups` + `group_id` on
  `platform_service_item_types`.
- Model `app/Models/PlatformServiceItemGroup.php`; `group()` on
  `PlatformServiceItemType`.
- Admin CRUD: `AdminV2\PlatformServiceItemGroupController`
  (`admin.platform-service-item-groups.*`), sidebar "Item Type Branches" under
  Service Setup. The item-types screen shows a branch column + filter.
- Backfilled from the pre-existing `meta->domain_key` (8 branches created; rows
  with no domain_key stay ungrouped).

### 2.2 Pricing authority — single source

`app/Services/ServiceExecutionEngine.php`:

- `resolvePriceBreakdown()` **always** prices from `BusinessServicePrice` (the
  row already resolved for the unit's item type). The unit's own price is not
  used; discounts apply even when a unit is selected.
- `resolveDepositPolicy()` bases the deposit on the `BusinessServicePrice`
  amount too.
- `resolveBusinessPriceForBooking(Booking)` (public) reuses the resolution
  priority for an existing booking.

**Rule:** price/deposit live only in `business_service_prices` (per item type).
A premium unit = a distinct item type. Admin & owner bookable-items are
inventory-only.

### 2.3 Table charge modes

On `business_service_prices` (`charge_mode` + `charge_amount`), managed on the
owner "My Prices" screen. `app/Models/BusinessServicePrice.php`:

| mode | meaning | `resolveBaseCharge` / `unifiedTotal(food)` |
|------|---------|--------------------------------------------|
| `standard` | normal price | `price` / `price(+disc) + food` |
| `free` | only food is charged | `0` / `food` |
| `reservation_fee` | fixed booking fee | `amount` / `amount + food` |
| `minimum_charge` | minimum spend | `amount` / `max(amount, food)` |

- `resolveBaseCharge($food=0)` — the unit's own charge (used by the engine).
- `unifiedTotal($food, $qty)` — the combined table+food invoice total.

### 2.4 Unified invoice (dine-in food)

`app/Services/BookingFoodService.php`:

- `orderForBooking(Booking)` → the single dine-in `Order` for a booking
  (`fulfillment_type=dine_in`, `booking_id` set).
- `addLine` / `removeLine` / `syncFoodLines` — manage the food `order_items`.
- `recalcOrder()` → `order.total` = Σ line totals, `final_total` = +delivery −discount.
- `unifiedInvoice(Booking)` → `{table_charge, food_total, total, deposit_amount, charge_mode, currency}`.
- `refreshBookingInvoice()` → writes `booking.price` = total and a
  `meta.unified_invoice` snapshot.

The owner's **My Bookings → show** page adds/removes food (prices taken from the
menu item, never posted) and shows this live.

### 2.5 Standalone menu orders (delivery / pickup)

`app/Services/MenuOrderService.php` + `Business\OrderController`: orders with
`booking_id = null` and a `fulfillment_type` of `delivery`/`pickup`. Total =
food + delivery fee − discount. No deposit (a booking concept).

### 2.6 Deposit

Deposit is a **hold/guarantee** (deducted at execution, not an extra charge),
gated by `platform_services.supports_deposit`. It is **single-source** (Phase 4):
resolved solely from `business_deposit_policies` via `BookingDepositPolicyResolver`
→ `BookingDepositCalculator`. The unified invoice shows the booking's actually
resolved deposit (`Booking::depositAmount()`), so the displayed amount always
matches what the engine holds. The old per-price `business_service_prices`
`deposit_enabled`/`deposit_percent` columns were retired.

---

## 3. Data-model changes (this workstream)

| Migration | Change |
|-----------|--------|
| `…create_platform_service_item_groups_table` | new table + `group_id` on item types + backfill from `meta->domain_key` |
| `…add_charge_mode_to_business_service_prices` | `charge_mode` (default `standard`) + `charge_amount` |
| `…add_booking_and_fulfillment_to_orders` | `fulfillment_type` (default `delivery`) + `booking_id` (FK, nullOnDelete) |

Model fixes: `Order` and `OrderItem` fillable/relations were mismatched with
their tables (e.g. `menu_id`/`size_id`/`addons`/`total_price`, and
`total`/`final_total` — there is no `subtotal`); corrected (tables were empty).

New relations: `Booking::orders()` + `foodTotal()`, `Order::booking()` +
`isDineIn()` + `foodTotal()`.

---

## 4. Verified behaviors (measured)

- Pricing: with a unit priced 9999 but a `BusinessServicePrice` of 500 (10%
  off, qty 2) → final **900**, source `business_service_price` (unit ignored).
- Charge modes (price 500, food 300): standard→800, free→300, reservation_fee(50)→350, minimum_charge(200)→300.
- Dine-in via the owner controller: table minimum 200 + pizza 150 → total 200
  (table 50); + another → food 300 → total 300 (table 0). Foreign menu item
  rejected.
- Standalone delivery order: shawarma×3 (180) + delivery 25 → final **205**.
- Admin bulk-create posts price 9999 → stored **0** (inventory-only).

---

## 5. Known follow-ups (from the blueprint)

- ✅ **Dropped `bookable_items.price/deposit_*` columns** — migration
  `2026_07_06_000001_drop_legacy_price_deposit_columns_from_bookable_items`
  removes `price` + the full legacy `deposit_*` cluster (guarded). The per-unit
  deposit override was removed from `BookingDepositPolicyResolver` (deposit is
  single-source via the business deposit policy); `OperationPresenter` and the
  admin booking payloads read price/deposit from the resolved snapshot only.
- ✅ **`BookablePricingService`** now bases the per-day calendar off
  `business_service_prices` (via `BusinessServicePriceResolver`) for the unit's
  item type; price rules apply on top. Still not wired into the booking-price
  path (the engine resolves price itself); the calendar is its live consumer.
- **Full menu ordering UX** beyond the current add/remove line (variants/extras,
  customer-facing flow) if needed.
