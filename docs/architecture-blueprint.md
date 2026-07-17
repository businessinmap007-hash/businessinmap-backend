# BIM Architecture Blueprint & Reorganization Plan

The agreed core model for the platform and a **phased plan** to remove the
accumulated complexity without touching the healthy core. Read this before any
structural work. Companion docs: [services-blueprint.md](services-blueprint.md)
(booking/menu/pricing detail) · [business-panel-and-services.md](business-panel-and-services.md)
(owner panel as built) · [categories.md](categories.md) (category data model).

---

## 1. Core idea (one sentence)

> A business is **classified** (category → child), which unlocks the **platform
> services** it may offer; the platform defines **item types** (organised into
> **branches**); the business **selects and prices** the types it offers — and
> those priced item types are simultaneously its **offer**, the customer's
> **filter**, and the **search index**. Customers **book / order** with a
> **deposit** held via the **wallet**.

The core flow (classification → offering → transaction) is sound. Complexity has
come only from **legacy overlaps** layered beside it — this plan retires them.

---

## 2. The layers (source of truth per concern)

| Layer | Concern | Where it lives |
|-------|---------|----------------|
| 1. Classification | What kind of business (its specialty) | `categories` → `category_children` |
| — | Which services a child may offer | `category_platform_services` |
| — | Which item types a (child, service) may offer | `category_service_configs.config.allowed_item_types` |
| 2. Platform definition | Services (fulfilment kinds) | `platform_services` (booking / menu / delivery …) |
| — | Item types (the specialties / products) | `platform_service_item_types` |
| — | Branches that group item types | `platform_service_item_groups` (+ `platform_service_item_group_type` pivot, **many-to-many**) |
| 3. Business offering | Price / deposit / discount **per item type** | `business_service_prices` |
| — | Physical inventory (room 101, table 5) | `bookable_items` |
| — | Food/menu lines | `menu_items` |
| 4. Transactions | A reservation / order | `bookings`, `orders` |
| — | Guarantee / hold | `deposits`, `wallets` |

---

## 3. The unifying principle

**`item_type` + its `business_service_price` = offer + filter + index.**

A business "subscribes to" a specialty simply by having a priced item type for
it. That single fact drives three things at once:

- **Offer** — what the business sells/serves (shown on its page).
- **Filter** — how a customer narrows results ("centres that teach English").
- **Index** — what the discovery search matches on.

Consequences (locked):
- **Options are redundant** for *classification* — the specialty filter is
  `category_child` + `item_types`. Options do **not** retire entirely: they are
  the platform's **attributes** axis (§3.1).
- **Branches** are an organisational grouping only; booking/pricing always keys
  on the item-type `key`, never the branch.
- Premium/variant of a type = a **distinct item type** (keeps pricing single-source).

### 3.1 The three axes (and the test that separates them)

Item types and options are not competing systems — they answer different
questions, and a business needs all three answered:

| Axis | Question | Source of truth |
|---|---|---|
| 1. Classification | **Who are you?** | `categories` → `category_children_master` |
| 2. Attributes | **How does the BUSINESS deal?** | `options` + `category_child_option` + `option_user` |
| 3. Offering | **What do you sell?** | `item_types` + `business_service_prices`, catalog |
| 4. Unit property | **What is true of ONE bookable unit?** | `bookable_items` (`capacity`, `meta`) |

The customer walks them: *محل موبيلات* (1) → *عنده تقسيط؟* (2) → *أنواع
الموبايلات المعروضة* (3) → *قاعة تسع 350* (4).

**The test, asked in order:**

> 1. Can the merchant put a **price** on it alone? → **item type** (`قاعة أفراح: 5000`)
> 2. No — does it describe the **whole business**? → **option** (`تقسيط`, `كاش`)
> 3. Does it describe **one unit**? → **unit row** (`bookable_items.capacity = 350`)

Getting this wrong is what makes the platform feel crowded: with no attributes
axis, everything is forced into item types, and a dimension like *capacity* turns
into 10 fake "types" nobody can price.

**Capacity is axis 4, not 2 — the correction that proves the axes matter.** It
was first moved into an option group «سعة القاعة». Wrong: a capacity is a
NUMBER, and an option on the business («this venue has a 300-500 hall») still
leaves the customer hunting inside a multi-hall venue. It belongs on the unit —
`bookable_items.capacity` (existing column), filtered `>= 320`, exact. Class
likewise → `bookable_items.meta.class`. Only a **business-level yes/no**
(amenities: wifi) is a true option.

### Usage (both personas)

```
Customer                         Business owner
─ search by specialty (child)    ─ classify store (category child)
─ filter by item types           ─ enable services (booking/menu/delivery)
─ see matching businesses  ◄──── ─ select & price item types  (the offer = the filter)
─ pick + book / order            ─ add inventory / menu
─ pay + deposit (wallet)         ─ manage bookings / orders
```

---

## 4. What is legacy / being retired or unified

1. **Options / OptionGroups** — a legacy layer doing double duty (attributes +
   pseudo-classification). **Retire the classification half only**: specialty
   groups migrated into item types + branches (done, Phase 1). The attributes
   half **stays and is load-bearing** — see §3.1 and Phase 1b.
2. **Menu vs Catalog** — two systems for the same idea ("sellable items in
   categories, priced, orderable"). **Unify** into one *offerings* model that
   varies by: **fulfilment** (book / dine-in / pickup / delivery = the
   platform service) and **richness** (food modifiers vs retail
   brand/attributes/variants). One business "my offerings" screen; one customer
   browse experience. (Catalog is slated for a rebuild — rebuild it *as* this
   unified model, not as a parallel system.)
3. **Deposit configuration** — currently split between
   `business_deposit_policies` and `business_service_prices.deposit_*`.
   **Consolidate** to a single source.

---

## 5. Decisions locked

1. Core model = classification → services → **item types (offer = filter =
   index)** → transactions. Do not rebuild it.
2. **Options retire as classification, and survive as attributes** (§3.1). The
   specialty filter is `category_child` + `item_types`; the *attributes* filter
   (تقسيط، كاش، جملة، سعة القاعة) is `options`. The separator is the price test.
3. **Branches are organisational only** and are a shared, cross-service,
   many-to-many pool (a type may sit in several branches).
4. **Menu and Catalog unify** into one offerings model (fulfilment + richness).
5. **Deposit is single-source.**
6. Pricing/deposit live only in `business_service_prices` (per item type);
   `bookable_items` is inventory-only (already done).

---

## 6. Phased reorganization plan

Each phase is **small, independent, and safe** (one BIM code / one conversation),
with its own acceptance check. Order chosen so nothing downstream breaks.

### ✅ Phase 0 — Branches foundation (done)
- Item-type branches; shared cross-service branches (`platform_service_id`
  nullable); many-to-many `platform_service_item_group_type`.
- Service Branch Board (matrix + column picker) and branch-edit membership
  manager. Options→item-types migration started (training + 5 groups).

### ✅ Phase 1 — Finish Options retirement (specialty groups done)
Every option group that represented a **specialty** has been migrated into item
types + branches; what remains in `options` is only attributes + product
catalogs deferred to Phase 3. Final disposition:
- **Migrated → booking item types/branches:** #7 training, #2 → «خدمات ومهمات»,
  #13 → «قاعات ومناسبات», #5 → «صحة وطب», #6 → «تقنية ورقميات», #8 → «ترفيه
  وأنشطة», #14 → «خدمات ومهمات». #4 → menu/سوبر ماركت. (source groups deleted;
  #8's 4 legacy `option_user` tags dropped.)
- **Kept as attributes:** #12 «أنماط خدمة وتجارية» (cash/installment, wholesale/
  retail, takeaway, online…) — genuine service/payment modes, not specialties.
  This is the legitimate residual role for `options`.
- **Deferred to Phase 3 (product catalogs):** #1 vehicles, #3 furniture, #9 real
  estate, #10 fashion, #11 packaging — rebuild these in the unified offerings/
  catalog model, not as booking/menu item types.
- **Accept (met):** no specialty/classification lives in `options` anymore;
  remaining rows are attributes (#12) or Phase-3 catalog data. Retiring the
  Options admin screens waits until Phase 3 empties the catalog groups.

### ✅ Phase 1b — Redistribute item types vs options (done 2026-07-17)

Phase 1 moved specialties **out** of options. It never did the other direction:
sort the item types themselves. `TaxonomyRedistributionSeeder` (idempotent) does,
using the §3.1 price test. Guarded by `TaxonomyRedistributionTest`.

**The finding.** «قاعات ومناسبات» held **39 entries of which only 9 were
bookable** — the rest were a hall's **capacity (10)**, **class (7)** and a
meaningless **«مقاس» scale (13)**. A customer hunting a wedding hall scrolled 39
rows to reach 9 real ones. *That* is why the platform felt crowded; not "too many
services".

| | Before | After |
|---|---:|---:|
| Active item types | 405 | **334** |
| — booking | 249 | **202** |
| — menu | 68 | **44** |
| Halls branch | 39 | **8** |
| Option groups | 6 | **7** |

What moved, and why:
- **Amenities → new option group** «مرافق ومعدات» (wifi, whiteboard — a
  business-level yes/no, nobody buys wifi).
- **Capacity & class → the bookable unit, NOT options.** First (wrongly) turned
  into option groups «سعة القاعة»/«فئة القاعة»; corrected — they are axis 4
  (§3.1), living on `bookable_items.capacity` and `.meta.class`. The item-type
  definitions stay deactivated; `dropMisplacedDimensionGroups()` removes the
  option groups.
- **«مقاس 4..16» deleted** — meaningless scale, zero references (owner's call).
- **Products misfiled in booking retired** (12): «خدمات ومهمات» is a *craftsmen*
  branch and held لعب أطفال، خضروات، موبايل، كمبيوتر. retail already has each.
- **Import duplicates merged** (27): the `_2`/`_1` suffixes (`canned_food_2`,
  `pasta_2`, «مواد غذائية 1/2») are the fingerprint of an import that never
  deduped. Also `electricity`→`electrical`, `football_5_field`→`five_side_field`,
  `vip`→`hall_vip`. Distinct real products (فسيخ، رنجة، بهارات، فحم، عصائر) were
  deliberately **not** merged — specific is not duplicate.
- **Option group #12 cleaned**: 42 → 28, dropping specialties that wandered in
  (حجز طيران، حجز فنادق، شغالة، دادة أطفال، بترول، أخشاب، الكريتال، «spear 1»…).
  Kept on purpose: بيع وشراء · إستيراد · تصدير · تسليم أرض المصنع · شحن — those
  are commercial *modes*, not products.

**Rules that made it safe:**
- **Deactivate, never delete** an item type (`is_active=0` + unbranch). A live
  `business_service_prices` row may reference the key — precedent set by
  `MenuBranchesSeeder`, which kept `3dmax` active for exactly that reason.
  `options` has no `is_active` column, so retired options are deleted; safe only
  because just 4 `category_child_option` links and 2 `option_user` rows exist.
- **Remap references before retiring.** `business_service_prices.bookable_item_type`
  **and** `category_service_configs.config.allowed_item_types`. That second one is
  the trap: configs name item types by **key inside a JSON array**, so a retired
  key throws no error — the merchant is simply still offered it, silently.
- Business 212 is **«فندق الاندلس», a real 2020 account**, not test data. Its
  room types are asserted to survive.

### ✅ Phase 2 — Wire discovery on the offer=filter principle (done)
- `Api/V2/DiscoveryController` + public routes:
  - `GET /v2/discovery/filters?child_id=&service_id=` — the services a category
    child's businesses offer, and (per service) the item types they offer
    **grouped by branch**, each with a business count (non-empty filters only).
  - `GET /v2/discovery/businesses?child_id=&service_id=&item_types[]=&q=` — the
    businesses in that child that actually offer the chosen service/item types,
    each carrying its matched offered types.
- Keys entirely on `business_service_prices` (offer = filter = index). Existing
  indexes (`bsp_child_idx`, service index, the composite business/child/service/
  type, `idx_users_category_child` + type) already cover it — no new migration.
- **Accept (met):** verified the "training centre" journey — adding an `english`
  offering makes it appear under the «تدريب ودورات» branch filter and returns the
  centre when filtered by `item_types=[english]`.

### ✅ Phase 1c — Wire the attributes axis (done 2026-07-18)

Phase 1b sorted *what* is an attribute. Nothing **read** attributes before this
— the measurements said the axis had collapsed rather than being switched off:

| | Before | After |
|---|---|---|
| Children with any option | 2 of 304 | unchanged — see note below |
| Businesses with any option | 1 of 1,748 | unchanged (no bulk backfill run) |
| Attribute filter in `DiscoveryController` | none | `GET discovery/attributes` + `businesses(option_ids[])` |
| Merchant self-service | none (`option_user` admin-only) | `GET`/`PATCH /profile/options` |

**Why relinking wasn't a data-recovery job:** `temp_category_option_mapping`
(108 rows) only maps `old_category_id → new_child_id` — it never carried
`option_id`. `category_child_option` itself was already down to 3 rows (all
child 68) by the time this was checked, so there was no lost linkage to
restore, only a live mechanism to finish wiring. What shipped:

1. **Customer filter** — `DiscoveryController::attributes($child_id)` returns
   the option groups/options actually linked to that child (via
   `CategoryChild::activeOptions()`), each with a business count. `businesses()`
   now accepts `option_ids[]` and requires **all** of them (AND, not OR — a
   filter narrows). Found and fixed a live bug on the way:
   `CategoryChild::activeOptions()` hardcoded `where('options.is_active', 1)`
   but `options` has **no `is_active` column** — every call would have thrown
   "Unknown column" (nothing had ever called it).
2. **Merchant self-service** — `ProfileController::showOptions`/`updateOptions`.
   A business can only sync `option_ids` that are linked to its **own**
   `category_child_id` via `category_child_option` (422 otherwise) — it cannot
   claim an attribute outside its own specialty. Full-replace semantics
   (`option_ids: []` clears everything), ported from the v1
   `businessOptions`/`sync()` precedent.
3. **Relink (admin-driven, ongoing)** — no bulk backfill script was run. The
   `category-child-options` bulk editor (routing fixed last session) is now the
   live, correct destination, and it is *already being used*: while this slice
   was in progress, ~18 real-estate option rows (أرض, عمارة, شقة, تقسيط, كاش,
   إيجار…) were moved from group 12 into group 9 «عقارات وممتلكات» by a
   concurrent admin edit — the exact "review both and decide" work the owner
   named as needed. `TaxonomyRedistributionTest` was updated to pin group 12's
   canary to `تقسيط بدون فوائد`/`دفع مسبق` (survivors) instead of the
   now-relocated bare `تقسيط`/`كاش`.

Guarded by `tests/Feature/AttributesAxisApiTest.php` (5 tests): the endpoint
shape + counts, AND-filtering, scope-to-own-specialty rejection, full-replace
clearing, and a client account has no attributes to set.

**Still open:** «محل موبيلات» is still not a specialty — no
`category_children_master` row matches موبايل; it exists only as item types
(`mobiles_accessories` in retail). The journey *shop kind → تقسيط → product
types* on the owner's own example still can't run end to end until that
classification gap closes. Bulk-backfilling `category_child_option` beyond
child 68 is admin data-entry work, not a code gap.

### Phase 3 — Unify Menu / Catalog into one offerings layer  *(largest)*

**Findings that shape the design (measured 2026-07-08):**
- `menu_items` / variants / extras — **empty (0 rows)**; a light food scaffold.
- `catalog_products` — **49,494 real master rows** + **76,565 attribute values**,
  86 brands, 35 manufacturers. Rich, curated (dedup/verification fields), **but
  heavily duplicated** (same `normalized_name_ar` up to **596×**; 541 names
  repeat) and the dedup fields (`dedup_key`, `duplicate_master_id`) are **un-run**.
  No `business_id` — it is **admin/curator master data, not per-business listings**.
- `orders` / `order_items` — empty; `order_items` currently keys on `menu_id`.

**Design (agreed):** don't merge the tables. Menu and Catalog are different kinds
of thing — Menu = bespoke per-business items; Catalog = a **shared global product
master** (many sellers, one product). Unify only the **selling layer** above them:

```
 global catalog master        bespoke items (item types / menu dishes)
          \                          /
           →   Business Offering (business + source + price + stock + fulfilment)
                              ↓
                Cart → Order → Fulfilment (delivery/pickup/dine-in/booking)
```

**Storage (chosen — pragmatic):** keep `business_service_prices` as the
type-priced bespoke offering; add a new **`business_catalog_listings`**
(`business_id`, `catalog_product_id`, `price`, `currency`, `stock`, `is_active`)
for retail; unify only at the cart/order layer. (A single polymorphic
`business_offerings` table is the tidier long-term target but a bigger migration —
deferred.)

**Sub-phases (execute one per conversation):**
- **✅ 3.0 Catalog dedup (done 2026-07-08).** Key = `normalized_name_ar` + brand +
  package (no barcodes exist). Master = lowest id per group. Result:
  **49,494 → 636 masters**; **48,858 duplicates soft-deleted** and linked via
  `duplicate_master_id` + `duplicate_status='duplicate'` + `deleted_at`. Distinct
  attribute values relinked onto masters (2,567 → 2,893; the 74k on duplicates
  were redundant copies, left inert). **Reversible** (clear `deleted_at` where
  `duplicate_status='duplicate'`). Consumers must scope `whereNull('deleted_at')`.
  Optional later: hard-purge duplicates + their redundant attribute values.
- **✅ 3a Order layer (done).** `order_items` now carry a polymorphic offering
  ref (`offering_type`/`offering_id`); `OrderItem::offering()` morphTo; writers
  populate it (menu item now). `menu_id` kept for BC. Orders were empty → safe.
- **✅ Catalog tooling (done).** `CatalogDedupService` (canonical dedup key —
  barcode first, then normalized name+brand+package, hashed to `dedup_key`;
  `findMasterId`, `backfillDedupKeys`, idempotent `runBatchDedup`) + `bim:catalog-dedup
  --dry-run`. `CatalogImportService.upsertProduct` now dedups on insert: same
  `bim_code` updates; a barcode/name match of an existing master is ingested as a
  linked, soft-deleted duplicate; a new product becomes a master routed to
  curation (`curation_status=pending`). Prevents re-introducing the duplication.
  **Next for catalog scale:** add real barcodes (GS1), expand the category tree
  beyond grocery, and feed products via import files rather than manual entry.
- **✅ 3b Order lines accept any offering (done).** `order_items` now carries a
  polymorphic `offering_type`/`offering_id` (menu_id made nullable — retail lines
  have none). `MenuOrderService::addOffering()` adds any offering at a
  caller-sourced price; `addLine()` delegates for menu items. `recalc()` sums
  `total_price` so mixed orders total correctly. `Business\OrderController::show()`
  loads the owner's active listings, resolves a per-line `display_name` by
  offering type, and `addProduct()` adds a scoped active listing at its price
  (route `orders/{id}/product`). The order view shows `display_name` and a
  parallel "add retail product" form. Verified end-to-end: a mixed order (2×menu
  50 + 3×listing 12.5 + 15 delivery) totals 152.50 with correct offering types.
- **✅ 3c Retail listings (done).** `business_catalog_listings` (business + master
  product + price + stock, unique per business/product). `CatalogProduct`
  (SoftDeletes → masters only) + `BusinessCatalogListing`. Owner panel
  «منتجاتي» (`/business/products`): scoped CRUD + ajax product picker over the
  deduped master (excludes already-listed; only active, non-duplicate masters
  are listable). Verified end-to-end. Customer browse of listings → 3d.
- **✅ 3d Unified UX (done).**
  - **✅ Owner "my offerings" screen (done).** `Business\OfferingController` +
    `/business/offerings` («عروضي») aggregate everything the owner sells in one
    source-tagged table (bespoke `business_service_prices`, menu items, retail
    `business_catalog_listings` joined to the deduped master). Each row links to
    its source's own edit screen; adding stays per-source. Client-side source
    filter with per-source counts. Verified (biz #212: 10 bespoke + 1 retail).
  - **✅ Customer retail discovery (done).** `Api/V2/RetailDiscoveryController`
    applies offer=filter=index to `business_catalog_listings`: `retail/filters`
    (product categories + brands that have active listings, with counts),
    `retail/products` (paginated browse with child/brand/q filters; each product
    shows price range + businesses count), `retail/products/{id}` (one master +
    every business selling it, cheapest first). Masters scoped
    whereNull(deleted_at). Verified (2 businesses on one product → price range
    10–14.5, cheapest-first offers, 404 on missing).
  - **✅ Customer cart (done).** A cart is a draft `Order` (status='cart') per
    business; lines are the same polymorphic `order_items` as real orders, so
    checkout is a status flip with no data copy. `CustomerCartService` resolves
    business + price server-side from each offering, merges same-offering lines,
    reuses `MenuOrderService::recalc`. `Api/V2/CartController` (auth-scoped):
    `GET cart`, `POST cart/items`, `PATCH`/`DELETE cart/items/{item}`,
    `POST cart/{business}/checkout`. Verified end-to-end (merge, per-business
    split, qty edit, remove, checkout→pending, empty-cart rejected). Scope:
    goods offerings (retail + menu); bespoke booking stays on the mature booking
    rails (deposit/wallet/consent) rather than a goods cart.
- **Accept:** ✅ a business can sell both a menu item and a catalog product
  through one cart/order (Phase 3b order lines + 3d cart); the catalog shows
  deduped masters. (Bespoke booking intentionally kept on the booking flow.)

### ✅ Phase 4 — Single-source the deposit config (done)
- The engine already resolved deposit solely from `business_deposit_policies`
  (`BookingDepositPolicyResolver` → `BookingDepositCalculator` → engine). The
  only second source was `business_service_prices.deposit_*`, feeding a
  display/snapshot path (`BookingFoodService::unifiedInvoice`) that could
  disagree with the held amount.
- **Change:** `unifiedInvoice` now shows the booking's actually resolved deposit
  (`Booking::depositAmount()`), so the invoice always matches what is held; the
  AdminV2 booking payload derives deposit from the resolved policy. The per-price
  `deposit_enabled`/`deposit_percent` columns are retired (removed from model,
  both price controllers, the index `selectRaw` `deposit_hold_amount`, and the
  owner/admin price forms + lists; migration drops them). Engine behaviour
  unchanged.
- **Accept:** ✅ deposit resolves from one documented source
  (`business_deposit_policies`); existing bookings unchanged (verified booking
  invoice deposit now equals `Booking::depositAmount()`).

### ✅ Phase 5 — Cleanup (done)
- **✅ Smoke tests (done).** Feature tests around the paths built in Phases 2–4,
  using `DatabaseTransactions` (the dev `business` DB is rolled back, never
  mutated) and reusing stable rows while seeding the volatile parts:
  `DiscoveryTest` (bespoke offer=filter=index), `RetailDiscoveryTest` (browse
  price-range/count, offers cheapest-first, 404, inactive hidden),
  `CustomerCartTest` (per-business grouping, qty merge, checkout flip,
  server-sourced price), `DepositSingleSourceTest` (columns retired, invoice
  deposit == held policy deposit). 16 passing (45 assertions).
- **✅ Dead-code sweep (done).** Findings after per-file analysis: the big
  dead classes were already gone (`BookingEngine`, `BookingTestController` —
  commit aa35607); Phase 4 already stripped the deposit columns/UI; **Options
  CRUD is intentionally alive** (manages the kept attribute groups — #12
  «أنماط خدمة» + deferred product-catalog groups — so it was *not* removed).
  Genuinely dead and removed: `resources/views/admin-v2/categories/options.blade.php`
  (orphaned category-level options view, superseded by `category-child-options`,
  not returned by any controller and pointing at a non-existent route). Pruned
  stale deposit docs in `services-blueprint.md`. The pre-existing
  `bookableItemsLookup` dead-column bug is handled in a separate task.
- **Accept:** ✅ no live references to retired concepts; green smoke tests
  (16 passing).

---

## 7. Guardrails (unchanged from the existing docs)

- Pricing/deposit single-source in `business_service_prices`; premium = distinct
  item type.
- Branches never affect pricing/booking logic (organisational only).
- Every structural change: work on the worktree branch, migrate with
  `Schema::hasColumn`/`hasTable` guards, merge to main, run `migrate` + clear
  caches, and verify before moving on.
