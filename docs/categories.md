# Categories & Category Children — Data Model

This document explains how root categories, sub-categories (children), their
service links, options and fees fit together in AdminV2. Read this before
touching anything under `admin/categories` or `admin/category-children`.

## The two levels

| Concept | Table | Model | Controller |
|---------|-------|-------|------------|
| Root category | `categories` (rows with `parent_id = 0`) | `Category` | `CategoryController` |
| Sub-category (child) | `category_children_master` | `CategoryChild` | `CategoryChildController` |

A **child is not** a `categories` row with `parent_id > 0`. It lives in its own
table and is linked to one or more roots through the pivot below. The old
"legacy child" (`categories.parent_id > 0`) still exists on the model
(`Category::legacyChildren()`) **only** for historical data — do not build new
features on it.

### Parent ↔ child link — `category_parent_child`

Many-to-many pivot: a child can belong to several roots at once.

- `Category::children()` / `activeChildren()` → the new children of a root.
- `CategoryChild::parents()` → the roots a child belongs to.
- Attaching/detaching is done via `->sync()` in
  `CategoryChildController::store/update/syncChildren/detachParent`.
- When a child loses its **last** parent it is deleted entirely (see
  `detachParent`).

## Which controller owns what

- **`CategoryController`** — root CRUD only (create/edit/delete roots, toggle
  active, reorder). Its `index()` *also* lists the children of a selected root,
  but that listing is read-only; it does not write children.
- **`CategoryChildController`** — all child CRUD, the parent↔child sync, and the
  child→service links. After a child write we redirect back to
  `admin.categories.index` (scoped to the root) because children are always
  managed in the context of a root — see `redirectToCategories()`.

> Historical note: child CRUD used to live inside `CategoryController` as
> `categoryChildren*` methods, with a second, unused `CategoryChildController`
> sitting dead beside it. The dead duplicate was removed and the live logic
> moved into `CategoryChildController`. Route **names** did not change
> (`admin.category-children.*`), so views were untouched.

## Services attached to a child — TWO tables, different jobs

This is the part that most easily confuses newcomers. There are two separate
tables and they answer two different questions.

### 1. `category_platform_services` — *"does this child offer this service?"*

- Columns: `category_id` (fallback root), `child_id`, `platform_service_id`,
  `is_active`, `sort_order`, `meta`.
- Written by `CategoryChildController::syncChildServices()`.
- Read via `CategoryChild::activePlatformServices()`.
- Emptying the service list **soft-disables** (`is_active = 0`) the rows rather
  than deleting them.

### 2. `category_service_config` (`CategoryServiceConfig`) — *"how does the service behave for this child?"*

- Holds the per-service configuration JSON: e.g. booking `allowed_item_types`,
  `booking_modes`, etc.
- This is what `BookableItemController::allowedItemTypesFor()` reads to decide
  which item types are valid for a business+service pair.
- `Category::getServiceConfig()` / `bookingAllowedItemTypes()` /
  `bookingModes()` read the root-level fallback config.

**Rule of thumb:** presence/on-off of a service → `category_platform_services`.
Behaviour/settings of that service → `category_service_config`.

### 3. `category_child_service_fees` (`CategoryChildServiceFee`) — fees

Third, separate table: the business/client fee split per child+service. Managed
by `CategoryChildServiceFeeController` (+ its bulk variant). Read via
`CategoryChild::serviceFees()` / `feeSnapshotFor()`.

## Options link — `category_child_option`

Children carry Options through the `category_child_option` pivot
(`CategoryChild::options()` / `activeOptions()`), managed by
`CategoryChildOptionController`. Deleting a child cleans this pivot in the same
transaction.

## Deletes

Deletes are hard deletes performed inside a `DB::transaction`, with the child's
pivots (`category_parent_child`, `category_child_option`) and its
`category_platform_services` rows cleaned up manually first. There is no
`SoftDeletes` on these models and no reliance on FK cascade — keep new
deletion paths consistent with this.

## Gotchas

- `CategoryChild` has **no `is_active` column** (unlike root `Category`). A
  child cannot be globally deactivated; activation is per-service via the
  `category_platform_services.is_active` pivot flag.
- Product catalog categories are a **different** feature —
  `product-categories` / `product-category-children` routes map to
  `ProductCategoryController` / `ProductCategoryChildController`, unrelated to
  the booking/service categories described here.
