# Retail Branches Taxonomy — تقسيم فروع خدمة التجزئة

Fourth (and final planned) application of the branch pattern
([delivery](delivery-branches-taxonomy.md) · [booking](booking-branches-taxonomy.md)
· [menu](menu-branches-taxonomy.md)) and the resolution of §8.1 in
[services-blueprint.md](services-blueprint.md). Applied 2026-07-13.

## 1. The gap it closes

Coverage analysis surfaced the one real service gap: the **معارض root (29/29
children)** had no fitting platform service (showrooms sell goods — no
booking/menu semantics), and the **non-food محلات children** had delivery
enabled with no catalog service to generate the order (menu is food-only). Both
were halves of one missing flow. `retail` is registered as the **5th typed
platform service** (`PlatformService::KEY_RETAIL`, `key: retail`, id 10 on the
live DB), so it inherits branches → services-bulk assignment → owner catalog
scoping → offers enablement for free.

## 2. The mirror contract (the key idea)

Retail item types double as the catalog taxonomy. The invariant, enforced by
seeding both sides from one source file (`database/seeders/data/retail_taxonomy.php`):

```
platform_service_item_groups.key  ==  product_categories.slug           (branch)
platform_service_item_types.key   ==  product_category_children.slug     (item type)
```

That is what lets the owner panel scope "My Products" without any join table:

```
config.allowed_item_types (branch-expanded type keys)
  → product_category_children.slug
  → catalog_products.product_category_child_id
```

Verify it any time:

```sql
-- must return 0 rows
SELECT t.key FROM platform_service_item_types t
JOIN platform_services s ON s.id = t.platform_service_id AND s.key='retail'
LEFT JOIN product_category_children c ON c.slug = t.key
WHERE c.id IS NULL;
```

## 3. The division — 8 branches / 53 types

Hand-designed from the live معارض + non-food محلات children (not mirrored from
the old sample catalog). All keys ASCII snake_case; three that would have
collided with existing **booking** type keys were renamed for grep-ability
(`marble_stone`, `beauty_cosmetics`, `medical_retail`).

| Branch (key) | الاسم | Types |
|---|---|---|
| `fashion_textiles` | ملابس وأقمشة | 6 (ملابس جاهزة، أقمشة، جلود وشنط وأحذية، أصواف وخيوط، نظارات، مستلزمات أفراح) |
| `home_furnishings` | أثاث ومفروشات | 12 (أثاث، مفروشات، سجاد، مراتب، إسفنج، ستائر، نجف، أنتيكات، زجاج، صيني، ألمونتال، خشب وديكور) |
| `electronics_tech` | إلكترونيات وأجهزة | 6 (أجهزة كهربائية، قطع غيار أجهزة، كمبيوتر، موبايلات، ألعاب، أجهزة رياضية) |
| `vehicles_parts` | مركبات وقطع غيار | 6 (سيارات، موتوسيكلات، قطع غيار، إكسسوارات، جنوط وكاوتش، زيوت) |
| `building_hardware` | مواد بناء وعدد | 10 (حدايد وبويات، حديد تسليح، أسمنت، رخام، سيفتي، عدد، خراطيم، مفاتيح، نجارة، بلاستيك) |
| `beauty_health_retail` | تجميل وصحة | 3 (أدوات تجميل، عطور، مستلزمات طبية) |
| `jewelry` | مجوهرات | 2 (ذهب، فضة) |
| `hobbies_general` | هوايات ومتنوعات | 8 (لعب، كتب، مكتبية، صيد، نباتات، مطاعم/كافيهات، تدخين، منظفات منزلية) |

Pricing/stock are **not** item-type attributes — they live per business ×
catalog product on `business_catalog_listings`. Item types are for **scoping
only** (which catalog products a child may see and list).

## 4. Child assignment (81 children)

`RetailChildBranchesSeeder` (extends `DeliveryChildBranchesSeeder`) applies
`database/seeders/data/retail_child_branches.php`, keyed by root slug + child
`name_ar`:

- **exhibitions (root 21):** all 29 children — the previously service-less
  showrooms.
- **shops-online (root 17):** every non-food child. The 12 pure-food children
  (أسماك، بن، مخابز، حلويات، خضروات، دواجن، سوبر ماركت، عصائر، فواكة، مجمدات، مني
  ماركت، هايبر ماركت) and استوديوهات stay Menu-only and are omitted. **منظفات is
  included** (`household_cleaners`) and keeps its Menu link too.

Each child is given the single best-fit branch; the seeder expands it to that
branch's full type set in `config.allowed_item_types` (branch-level scope, same
granularity as the other three services). Duplicate-named children (e.g. أجهزة
رياضية ×2 under exhibitions) are all matched by name.

`BusinessOffersEnablementSeeder` now counts `retail` among the typed services,
so the 29 معارض children became offers-eligible (newly_enabled=29 on first run).

## 5. Catalog wipe (one-time)

The prior catalog was 569 sample rows (536 supermarket) with a duplicated
`product_categories` tree from two import batches. `RetailCatalogWipeSeeder`
(NOT in `DatabaseSeeder`; run once by hand, guarded by a listings-count check /
`RETAIL_WIPE_CONFIRM=1`) cleared `catalog_products` + the product taxonomy via
child→parent DELETE (TRUNCATE is impossible under the composite FK
`cp_child_matches_parent_fk`). The slug-keyed masters (86 brands, 35
manufacturers, 9 units, 13 attributes) were **kept** — the importer reuses them.

`RetailProductTaxonomySeeder` (idempotent, in `DatabaseSeeder`) then lays down
`product_categories` / `product_category_children` mirroring §3, upserting by
slug exactly like `CatalogImportService::upsertCategory/Child` — so a later
`bim:catalog-import` writes INTO these rows instead of duplicating them.

The catalog table schemas — which had no in-repo migration (external dump) — are
now captured in `database/migrations/2026_07_02_000000_capture_catalog_schema_tables.php`
(all creates guarded with `Schema::hasTable`, a no-op on the live DB). Five
orphan `business_*product*` tables (zero code references) were dropped by
`2026_07_12_000000_drop_orphan_business_catalog_tables.php`.

## 6. Adding a branch later (e.g. grocery for food shops)

Purely additive, no structural change:

1. Append the branch + its types to `database/seeders/data/retail_taxonomy.php`.
2. `php artisan db:seed` — `RetailBranchesSeeder` registers the branch/types and
   `RetailProductTaxonomySeeder` mirrors the catalog categories.
3. Add child assignments in `retail_child_branches.php` and re-run
   `RetailChildBranchesSeeder`.
4. Import products for the new section (`bim:catalog-import <section>`) with the
   branch/type keys as `product_category_slug` / `product_category_child_slug`.

## 7. Seeders (run order in `DatabaseSeeder`)

`PlatformServiceSeeder` (retail row) → … → `RetailBranchesSeeder` →
`RetailChildBranchesSeeder` → `RetailProductTaxonomySeeder` →
`BusinessOffersEnablementSeeder`. All idempotent and fingerprint-stable on
re-run.

> Note: the full `php artisan db:seed` currently aborts early on a **pre-existing
> unrelated break** — `CategoryPlatformServiceSeeder` references a deleted
> `CategoryBookingProfile` model. The retail seeders were verified by running
> them individually (as the other branch seeders have been). Fixing that stale
> seeder is out of scope here.
