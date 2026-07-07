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
- **Options are redundant** for discovery/classification — the filter is
  `category_child` (specialty) + `item_types` (subjects offered). Options retire.
- **Branches** are an organisational grouping only; booking/pricing always keys
  on the item-type `key`, never the branch.
- Premium/variant of a type = a **distinct item type** (keeps pricing single-source).

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
   pseudo-classification). **Retire fully**: migrate meaningful groups into item
   types + branches (in progress); keep at most a small, single-purpose
   "attributes" concept if any genuine attributes remain.
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
2. **Options retire.** Discovery/filtering = `category_child` + `item_types`.
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
- **3.0 Catalog dedup (careful pre-step).** Build a dedup key
  (`normalized_name_ar` + brand + package/barcode), pick a master per group
  (verified / highest `curation_score` / lowest id), set duplicates'
  `duplicate_master_id` + `duplicate_status`, merge/relink attribute values,
  soft-delete duplicates. **Dry-run + review counts before applying** (49k rows,
  hard to reverse). Keep the master; do **not** wipe.
- **3a Order layer.** `order_items` reference an offering
  (`offering_type`/`offering_id`) instead of `menu_id` (orders empty → safe).
- **3b Menu → bespoke offerings.** Model menu dishes through the offering model
  (empty → easy).
- **3c Retail listings (the new value).** `business_catalog_listings` + an owner
  UI to search the deduped master and list + price + stock; customer browse of
  listings.
- **3d Unified UX.** One owner "my offerings" screen (source-aware presets) + one
  customer cart/browse across bespoke + retail.
- **Accept:** a business can sell both a bespoke item and a catalog product
  through one cart/order; the catalog shows deduped masters.

### Phase 4 — Single-source the deposit config
- Consolidate `business_deposit_policies` and `business_service_prices.deposit_*`
  into one resolution path; keep the engine's behaviour identical.
- **Accept:** deposit resolves from one documented source; existing bookings
  unchanged.

### Phase 5 — Cleanup
- Remove dead relations/controllers/views left by the above (e.g. emptied
  Options CRUD), prune stale docs, add tests around the discovery + pricing +
  deposit paths (the cross-cutting gap noted in review).
- **Accept:** no references to retired concepts; green smoke tests.

---

## 7. Guardrails (unchanged from the existing docs)

- Pricing/deposit single-source in `business_service_prices`; premium = distinct
  item type.
- Branches never affect pricing/booking logic (organisational only).
- Every structural change: work on the worktree branch, migrate with
  `Schema::hasColumn`/`hasTable` guards, merge to main, run `migrate` + clear
  caches, and verify before moving on.
