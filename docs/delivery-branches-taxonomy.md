# Delivery Branches Taxonomy — تقسيم فروع خدمة التوصيل

Scope: **the Delivery platform service only** (`platform_services.key = delivery`,
id 3). This is a review + design document — **no code / schema changes are made
by it.** It defines (1) how Delivery should be split into branches
(`platform_service_item_groups`) that fit the different business subcategories,
and (2) the requested enhancement to `categories/services-bulk` so an admin can
pick the branches (grouped item types) appropriate to a child / set of children.

Read first: [categories.md](categories.md) (category → child → service data model)
and [services-blueprint.md](services-blueprint.md) (services / branches / types).

---

## 1. Current state (as-is)

Delivery today is modelled for **consumer last-mile only**:

| Layer | Current content |
|-------|-----------------|
| Branches (`platform_service_item_groups`, service 3) | **one real branch**: `delivery` / «توصيل» (id 8) |
| Item types (`platform_service_item_types`, service 3) | **6 types**, all consumer-facing: `delivery`, `restaurant_delivery`, `grocery_delivery`, `pharmacy_delivery`, `scheduled_delivery`, `express_delivery` |

The 6 types are cross-listed under the `supermarket` branch (id 7, which belongs
to the Menu service) — an organizational overlap, not a Delivery branch.

**Gap.** These types fit restaurants / shops / pharmacies. They do **not** serve
the delivery/shipping needs of the other business roots that also offer
"Delivery":

- **مصانع / Factories** (47 children: أثاث، حديد تسليح، اسمنت، رخام، أخشاب، زجاج…) → need **heavy freight / full & partial loads**, not last-mile.
- **شركات / Companies** (70+ children incl. نقل دولي، شحن بري وبحري وجوى، استيراد وتصدير) → need **international / B2B logistics**.
- **الصحة / Health** (صيدلية، معمل تحاليل، مراكز أشعة) → pharmacy last-mile **+ medical-sample courier / cold chain**.
- **شحن وتوصيل / Shipping & Delivery** (children: شركة، مكتب، مندوب) → these are the **providers themselves**; need documents / on-demand courier branches.

**Model note.** There is currently **no link between a branch and a
`category_child`.** The only per-child restriction is the flat
`category_service_configs.config.allowed_item_types` list. "Each child picks its
appropriate branches directly" therefore means: use the **branch as the grouping
unit** when authoring that list (see §4).

---

## 2. Proposed division — 6 Delivery branches

Relationship confirmed with product owner: **one child → many branches** (a child
takes the union of every branch that fits it). Keys below are proposals for
`platform_service_item_groups.key`; `platform_service_id` stays `NULL` (global
pool) exactly like the existing branches.

| # | Branch (key) | الاسم | Serves (roots / children) | Item types — existing = ✅, proposed = ➕ |
|---|---|---|---|---|
| 1 | `delivery_lastmile` | توصيل استهلاكي (ميل أخير) | مطاعم؛ محلات (سوبرماركت، مخابز، حلويات، عصائر)؛ صحة → صيدلية | ✅ `restaurant_delivery`, `grocery_delivery`, `pharmacy_delivery`, `express_delivery`, `scheduled_delivery` |
| 2 | `delivery_freight` | شحن بضائع ثقيل / حمولات | مصانع + شركات (أثاث، حديد تسليح، اسمنت، رخام، أخشاب، زجاج، معدات ثقيلة) | ➕ `full_truckload` (حمولة كاملة)، `partial_load` (حمولة جزئية)، `crane_winch` (ونش/رافعة) |
| 3 | `delivery_international` | شحن دولي / استيراد وتصدير | شركات (نقل دولي، شحن بري وبحري وجوى، استيراد وتصدير) | ➕ `sea_freight` (شحن بحري)، `air_freight` (شحن جوي)، `land_freight` (شحن بري)، `customs_clearance` (تخليص جمركي) |
| 4 | `delivery_coldchain` | سلسلة تبريد / مبرّد | مصانع/شركات (مجمدات، أسماك، دواجن، مواد غذائية)؛ صحة (مواد دوائية، عينات معمل) | ➕ `refrigerated_delivery` (توصيل مبرّد)، `frozen_transport` (نقل مجمّد)، `medical_sample_courier` (نقل عينات طبية) |
| 5 | `delivery_courier_ondemand` | مناديب ومهمّات (عند الطلب) | شحن وتوصيل → مندوب؛ خدمات ومهمّات | ➕ `rep_errand` (مندوب/مشوار)، `same_day_pickup` (استلام وتسليم بنفس اليوم) |
| 6 | `delivery_documents` | مستندات وطرود صغيرة | شحن وتوصيل → مكتب/شركة؛ شركات؛ مكاتب | ➕ `document_courier` (كوريير مستندات)، `small_parcel` (طرد صغير) |

The bare `delivery` type (id 19, generic «توصيل») stays as a catch-all under
branch 1 (or is deprecated once the specific types exist — see §6).

### Additions I recommend beyond the 6 (اقتراحات إضافية)

- **`bulk_reservation` / حجز حمولة مسبق** as an item type inside branch 2 — factories
  often book a truck ahead of production output; keeps freight separate from
  same-day courier semantics.
- Keep **cold chain (4) distinct from freight (2)** even though both can be heavy:
  the vehicle constraint (refrigeration) is a different fulfilment capability and
  a different price basis, and per the pricing rule a distinct capability = a
  distinct item type/branch, not a flag.
- Do **not** create a per-city or per-weight branch — those are **pricing**
  attributes (`business_service_prices` / delivery config `max_radius_km`), not
  taxonomy. Branches describe *what kind of shipment*, never *how far / how much*.

---

## 3. Child → branch mapping (from live data)

Union model: a child gets every branch whose nature matches it. Representative
mappings (not exhaustive — full list is authored in the bulk screen, §4):

| Root | Child (example) | Branches |
|------|-----------------|----------|
| شحن وتوصيل | مندوب | 5 |
| شحن وتوصيل | مكتب | 6 |
| شحن وتوصيل | شركة | 2, 3, 5, 6 |
| مصانع | آثاث / أخشاب / زجاج | 2 |
| مصانع | حديد تسليح / اسمنت / رخام | 2 |
| مصانع | مجمدات / أسماك / مواد غذائية | 2, 4 |
| شركات | نقل دولي / شحن بري وبحري وجوى / استيراد وتصدير | 3 |
| شركات | مواد دوائية | 3, 4, 6 |
| الصحة | صيدلية | 1, 4 |
| الصحة | معمل تحاليل / مراكز أشعة | 4, 5 |
| مطاعم وكافيهات | مطعم / كافيه / أكل بيتى | 1 |
| المحلات أو أونلاين | سوبر ماركت / مخابز / حلويات | 1 |

Children shared across several roots (e.g. «سجاد» under محلات/شركات/مصانع) receive
the **union** of the branches assigned in each root context.

---

## 4. Requested enhancement — grouped branch selection in `categories/services-bulk`

**Goal.** In the bulk screen, after choosing a service for the selected
child(ren), let the admin pick the **item groups (branches)** appropriate to that
child instead of hand-picking flat item types — "select the appropriate
`item_groups` for this child / these children, grouped."

### How it plugs into what exists

Owner: [CategoryServiceBulkController](../app/Http/Controllers/AdminV2/CategoryServiceBulkController.php)
· View: [services-bulk.blade.php](../resources/views/admin-v2/categories/services-bulk.blade.php).

Today the per-service config is built by `serviceConfigPayload()`:

- Booking → `bookingConfigPayload()` already reads a flat `allowed_item_types[]`.
- **Delivery → `deliveryConfigPayload()` has *no* item-type / branch field at all.**

Proposed data flow (spec — not yet implemented):

1. **UI.** Under each *selected* service, render its branches as collapsible
   groups (source: `platform_service_item_groups` for that service + global pool).
   Each branch is one checkbox that means "all item types in this branch"; expand
   to fine-tune individual types. This mirrors the existing service-branch board
   matrix, reused read-only here.
2. **Submit.** Post the chosen branch ids per service, e.g.
   `item_groups[<service_id>][] = <group_id>` (and optionally an explicit
   `allowed_item_types[<service_id>][]` for fine-tuned picks).
3. **Server.** In `serviceConfigPayload()` (add the field to the delivery + menu
   payloads too), **expand each chosen branch to its member item-type keys** via
   `platform_service_item_group_type` and store the union in
   `config.allowed_item_types`. Persist the chosen `config.item_groups = [...]`
   as well so the UI can re-check the branch boxes on reload.
4. **Read.** `config.allowed_item_types` remains the single source the booking /
   delivery read paths already consume — no downstream change. Branches are just
   the **authoring convenience**; the stored truth stays the flat type list.

### Why store both

`allowed_item_types` stays authoritative (existing readers unchanged). Storing the
`item_groups` selection alongside is purely for round-tripping the checkboxes; if
a branch later gains a new type, the admin re-applies to pick it up — an explicit,
auditable action rather than silent membership drift.

### Implementation status — ✅ done

Shipped in `CategoryServiceBulkController` + `services-bulk.blade.php`:

- `index()` builds `serviceBranches` (per-service branch tree with member types)
  and `configMatrix` (existing `item_groups` / `allowed_item_types` per
  child+service) for pre-filling.
- The bulk view renders, inside each selected service's card, a **branch picker**:
  each branch is one checkbox (= all its types) with an expandable per-type list;
  ungrouped types fall under a «بدون فرع» bucket. A "mixed" warning shows when the
  selected children differ. Empty selection = no restriction (owner sees all).
- On submit, `serviceConfigPayload()` (all services now, not just booking) stores
  `config.item_groups` (ticked branch ids) + `config.allowed_item_types` (the
  branch-expanded union via `resolveAllowedItemTypes()`, joined through
  `platform_service_item_group_type`). Validation added in `apply()`.
- Read path unchanged: the owner panel (`ResolvesOwnerCatalog::allowedTypesByService`)
  already filters pickable types against `config.allowed_item_types`, so the owner
  picks from exactly what the admin allowed.

Verified against live data: branch 8 («توصيل») expands to the 6 delivery type
keys; page renders 200 for admin; `apply()` writes the expanded set.

---

## 5. What is NOT changing

- No new branch↔child table. The link stays via `config.allowed_item_types`;
  branches are an authoring grouping, consistent with the current model.
- Pricing/deposit stay single-source in `business_service_prices`. Branches never
  carry price.
- The service-branch board (`admin/service-branches`) remains the place to define
  which item types belong to which branch; the bulk screen only *consumes* that.

---

## 6. Open questions (need product decision before build)

1. **Branch count** — are the 6 final, or split «مواد بناء» out of freight (2) /
   merge documents (6) into on-demand (5)?
2. **Deprecate the generic `delivery` type (id 19)?** Once branch-specific types
   exist, keep it as a catch-all or retire it (existing rows would need remap).
3. **Seeding** — should the 6 branches + proposed item types ship as a seeder /
   migration, or be created by hand in the service-branch board?
4. Should the same grouped-branch selection also replace Booking's current flat
   `allowed_item_types` picker in the bulk screen, for consistency?
