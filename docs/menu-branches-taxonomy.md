# Menu Branches Taxonomy — تقسيم فروع خدمة المنيو

Third application of the branch pattern
([delivery](delivery-branches-taxonomy.md) · [booking](booking-branches-taxonomy.md)).
Applied 2026-07-12.

## 1. What was wrong

Menu had two real branches: `restaurant_menu` (14 types, fine) and `supermarket`
— a **53-type dump** mixing two generations of imports (duplicates like
`canned_food`/`canned_food_2`, three «مشروبات» variants). No category child had
any menu config at all.

## 2. The division — umbrella + 5 specialised branches

`supermarket` **keeps all 53 types** as the umbrella (hyper/mini market children
genuinely stock everything). Subsets are **cross-listed** (m2m) into 5 new
branches so specialised children get only what fits:

| Branch (key) | الاسم | Types |
|---|---|---|
| `restaurant_menu` | منيو المطاعم | 14 (unchanged) |
| `supermarket` | سوبر ماركت | 53 (unchanged, umbrella) |
| `fresh_market` ➕ | طازج (خضار وفاكهة ولحوم وأسماك) | 17 |
| `bakery_sweets` ➕ | مخبوزات وحلويات | 9 |
| `beverages_drinks` ➕ | مشروبات وعصائر | 5 |
| `grocery_pantry` ➕ | بقالة ومواد غذائية | 14 |
| `household_personal` ➕ | منظفات وعناية ومنزلية | 7 |

Left untouched on purpose: placeholder type `menu` («منيو», ungrouped).

Legacy cleanup (`MenuBranchesSeeder::cleanupLegacyTypes()`): the stray `3dmax`
menu-type was detached from the *training* branch but **kept active** — business
212 («فندق الاندلس») holds a live price on it (price row id 13; also a stray
`five_side_field` price, id 18 — flagged for manual review, clearly test data on
a hotel account). `ultra_modern` (no references anywhere) was detached from
supermarket and deactivated; the supermarket children re-expanded 53 → 52 types.

## 3. Child mapping (applied, 19 children)

| Root | Children | Branches |
|---|---|---|
| مطاعم وكافيهات (16) | all 6 | restaurant_menu |
| المحلات أو أونلاين (17) | سوبر/مني/هايبر ماركت | supermarket |
| | مخابز، حلويات | bakery_sweets |
| | عصائر | beverages_drinks |
| | بن | beverages_drinks + grocery_pantry |
| | أسماك، دواجن، خضروات، فواكة، مجمدات | fresh_market |
| | منظفات | household_personal |
| | the other 52 (non-food shops) | skipped |

Writes are merge-style (only `item_groups` + `allowed_item_types` owned; menu
behaviour flags preserved/defaulted).

## 4. Reproducibility

```
php artisan db:seed --class=MenuBranchesSeeder        # branches + cross-listing
php artisan db:seed --class=MenuChildBranchesSeeder   # child → branch layout
```

`MenuChildBranchesSeeder` extends `DeliveryChildBranchesSeeder` over
`data/menu_child_branches.php`. Verified: re-running all three services' child
seeders leaves a byte-identical fingerprint (620 config rows).
