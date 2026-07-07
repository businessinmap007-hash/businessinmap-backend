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

### Phase 1 — Finish Options retirement
- Classify the remaining option groups: migrate the ones that map to a service
  into item types + branches; for the **used** groups (#8 entertainment, #12
  service modes) decide keep-as-attributes vs migrate; for pure product/brand
  catalogs (vehicles, furniture, fashion, packaging, real estate) route to the
  offerings/catalog model (Phase 3) or drop.
- Retire or repurpose the Options admin screens once empty.
- **Accept:** no discovery/classification path reads `options`.

### Phase 2 — Wire discovery on the offer=filter principle
- Customer API (Api/V2): filter businesses by `category_child` + `item_types`;
  add the supporting indexes. Verify the "training centre" journey end-to-end.
- **Accept:** searching a specialty then filtering by item types returns the
  right businesses.

### Phase 3 — Unify Menu / Catalog into one offerings model  *(largest)*
- Design the offering entity: `item_type` (under a service) + optional
  **attributes** (retail: brand/size/colour) + optional **modifiers** (food:
  extras/sizes) + **variants** + inventory. Business-type preset chooses which
  fields show.
- Migrate `menu_items` and rebuild catalog onto it incrementally (tables are
  low-volume / slated for rebuild).
- **Accept:** one owner "my offerings" screen and one customer browse flow serve
  both restaurant menus and retail catalogs.

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
