# Booking Branches Taxonomy — تقسيم فروع خدمة الحجز

The Booking counterpart of [delivery-branches-taxonomy.md](delivery-branches-taxonomy.md)
— same pattern: divide the service into branches, map each category child to its
branches, expose the selection through the services-bulk branch picker, and keep
it reproducible via seeders. Applied 2026-07-12.

## 1. What was wrong

Booking had 10 branches but **3 were empty** (clinic, restaurant_table,
entertainment_leisure) and **77 active item types belonged to no branch** — the
branch picker couldn't reach them.

## 2. The branch division (13 at first pass, +1 later = 14)

10 existing branches (now fully populated) + 3 new ones the data demanded,
plus `business_consulting` added 2026-07-12 (see below):

| Branch (key) | الاسم | Notes |
|---|---|---|
| `clinic` | عيادات ومواعيد طبية | appointment types: كشف، متابعة، جلسة، إجراء، أونلاين، تحاليل، أشعة، زيارة منزلية |
| `hotel` | فنادق ووحدات سكنية | + executive/royal suite، شقة |
| `restaurant_table` | طاولات المطاعم | the 9 table types |
| `sports` | ملاعب رياضية | + بادل، خماسي/سباعي/قانوني، تنس، سلة، طائرة، حارة سباحة |
| `training` | تدريب ودورات | + دورة، ورشة، حصة خاصة، محاضرة، أونلاين، مدرب |
| `services_tasks` | خدمات ومهمات | crafts/tasks catch-all (80 types) |
| `halls_events` | قاعات ومناسبات | + قاعة عادية/VIP، شرائح السعة، إقامة حفلات |
| `health_medical` | صحة وطب | business-type level (مستشفى، نادي صحي…) — complements `clinic` |
| `technology_digital` | تقنية ورقميات | + موبايلات، تطبيقات، إلكترونيات |
| `entertainment_leisure` | ترفيه وأنشطة | aqua park، بولينج، بلايستيشن، صالة ألعاب… |
| `tourism_travel` ➕ | سياحة ورحلات | حج وعمرة، سياحة دولية، سياحة علاجية |
| `real_estate` ➕ | عقارات ووحدات | شقة/فيلا/شاليه/استوديو — **cross-listed with `hotel`** (m2m) |
| `beauty_care` ➕ | تجميل وعناية | 6 new types (حلاقة، تصفيف، صبغة، مكياج، عرايس، سبا) for the كوافير root |
| `business_consulting` ➕ | استشارات وأعمال | 6 new consultation types (قانونية، محاسبية، تسويقية، تقنية، أعمال، معاينة) + cross-listed generic/online slots + the 4 business types; serves the 17 pure-service children (تسويق، محاماة، تأمين، برمجيات، مقاولات…) previously excluded from everything — سياحة/رحلات also get tourism_travel, تنسيق حفلات also gets halls_events |

The legacy placeholder type `category` («افتراضي») stays ungrouped on purpose.

Curation note: `services_tasks` used to carry the two old sports-field types
(`five_side_field`, `full_field`) from an earlier import — **cleaned**: they are
detached from services_tasks (sports-only now) by
`BookingBranchesSeeder::cleanupLegacyMemberships()`, and the affected child
configs were re-expanded (80 → 78 types).

## 3. Root → branch mapping (applied, 160 children)

| Root | Branches |
|---|---|
| فنادق سياحية (24) | hotel |
| مطاعم وكافيهات (16) | restaurant_table |
| الرياضة (7) | sports |
| فنون وترفيه (9) | entertainment (+ tourism للرحلات البحرية/النيلية/الصيد) |
| قاعات (11) | halls_events |
| دورات وتدريب (12) | training |
| عقارات وأراضي (18) | real_estate |
| كوافير (443) | beauty_care |
| الصحة (20) | clinic + health_medical |
| مهن وحرفيين (6) | services_tasks (كوافير → beauty_care) |
| ورش ومراكز صيانة (10) | services_tasks |

Skipped for booking: goods roots (محلات، مصانع، شركات… already delivery-side)
and معارض (product showrooms; no booking semantics yet).

## 4. Writes are merge-style, not replace

Existing booking configs (notably root 24, authored by
`ServiceCatalogMatrixController` with `catalog_source: service_catalog_matrix`)
were **merged**: only `config.item_groups` + `config.allowed_item_types` are
owned by this layout; behaviour flags, catalog markers and fees are untouched.

## 5. Reproducibility

```
php artisan db:seed --class=BookingBranchesSeeder        # 13 branches + type filing
php artisan db:seed --class=BookingChildBranchesSeeder   # child → branch layout
```

`BookingChildBranchesSeeder` extends `DeliveryChildBranchesSeeder` (shared
merge/idempotency logic) and reads `data/booking_child_branches.php`. Verified:
re-running both leaves a byte-identical config fingerprint (525 rows across both
services).
