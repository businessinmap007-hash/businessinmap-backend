# دورة عمل Retail — سجل التغييرات (2026-07-13)

ملخّص كل ما أُنجز في هذه الدورة على فرع `main` (كلها committed + merged + pushed
إلى GitHub). أربع كتل عمل مترابطة: بناء خدمة التجزئة الخامسة، ثم ثلاثة تنظيفات.

---

## 1. خدمة Retail — الخدمة الخامسة + إعادة بناء الكتالوج

**السبب:** تحليل التغطية (`docs/services-blueprint.md` §8.1) كشف الفجوة الحقيقية
الوحيدة: جذر **معارض (29/29 طفلاً بلا أي خدمة)** و**أطفال محلات غير الغذائية**
عندهم delivery بلا كتالوج يولّد الطلب. الحل: `retail` كخدمة خامسة مسجّلة ترث نمط
الفروع بالكامل، مع مسح الكتالوج التجريبي القديم وإعادة بنائه.

### الفكرة المعمارية — عقد المرآة 1:1
مصدر حقيقة واحد (`database/seeders/data/retail_taxonomy.php`) يغذّي جانبَي النظام
فلا انحراف ممكن:

```
platform_service_item_groups.key  ==  product_categories.slug          (فرع)
platform_service_item_types.key   ==  product_category_children.slug    (نوع عنصر)
```

فيصبح نطاق «منتجاتي» للمالك بلا جدول جسر:
`config.allowed_item_types → product_category_children.slug → catalog_products.product_category_child_id`.

### التقسيم: 8 فروع / 53 نوعاً
`fashion_textiles` (6) · `home_furnishings` (12) · `electronics_tech` (6) ·
`vehicles_parts` (6) · `building_hardware` (10) · `beauty_health_retail` (3) ·
`jewelry` (2) · `hobbies_general` (8). ثلاثة مفاتيح أُعيدت تسميتها لتفادي
التصادم مع أنواع booking: `marble_stone`, `beauty_cosmetics`, `medical_retail`.

### التعيين: 81 طفلاً
كل أطفال معارض الـ29 + كل أطفال محلات غير الغذائيين. مُستبعَد: 12 طفلاً غذائياً
(أسماك، بن، مخابز، حلويات، خضروات، دواجن، سوبر ماركت، عصائر، فواكة، مجمدات، مني
ماركت، هايبر ماركت) + استوديوهات — تبقى على menu. **منظفات مشمولة**
(`household_cleaners`) وتحتفظ برابط menu.

### التسعير
السعر والمخزون يبقيان على `business_catalog_listings` (لكل بزنس × منتج). أنواع
العناصر **للنطاق فقط** لا للتسعير.

### الملفات
- **Migrations:**
  - `2026_07_02_000000_capture_catalog_schema_tables.php` — التقاط مخطط 14 جدول
    كتالوج (كانت بلا migration؛ dump خارجي)، محروسة بـ`Schema::hasTable`
    (no-op على القاعدة الحية)، مؤرّخة قبل migration الـcuration وFK الـlistings.
  - `2026_07_12_000000_drop_orphan_business_catalog_tables.php` — حذف 5 جداول
    يتيمة بلا مراجع كود.
- **الموديل/الـseeders:** `PlatformService::KEY_RETAIL`؛ صف retail في
  `PlatformServiceSeeder`؛ `RetailBranchesSeeder`، `RetailChildBranchesSeeder`،
  `RetailProductTaxonomySeeder` (idempotent، في DatabaseSeeder)؛
  `RetailCatalogWipeSeeder` (تدميري، لمرة واحدة، خارج DatabaseSeeder، محروس
  بـ`RETAIL_WIPE_CONFIRM`)؛ ملفّا بيانات `retail_taxonomy.php`
  و`retail_child_branches.php`؛ `BusinessOffersEnablementSeeder` يعدّ retail الآن.
- **التحكّم:** ذراع `retailConfigPayload` في `CategoryServiceBulkController`
  (services-bulk، بلا تغيير blade)؛ `CatalogListingController` صار مقيّد النطاق
  عبر `ResolvesOwnerCatalog` + جسر الـslug (غير المؤهلين 403)؛ فلتر الفروع في
  `RetailDiscoveryController`.
- **الاختبارات:** trait `SeedsRetailCatalog`؛ تعديل RetailDiscovery/CustomerCart
  لتزرع fixtures خاصة؛ اختبار جديد `RetailOwnerScopingTest`.
- **التوثيق:** `docs/retail-branches-taxonomy.md` الجديد + تحديث
  services-blueprint §8.1 وbusiness-panel-and-services وcatalog-import-v4.

### المسح (لمرة واحدة)
`RetailCatalogWipeSeeder` مسح 569 صفاً بترتيب طفل→أب (TRUNCATE مستحيل تحت الـFK
المركّب `cp_child_matches_parent_fk`)، وأبقى الـmasters (86 براند/35 مصنّع/9
وحدات/13 خاصية). الكتالوج الآن **فارغ** حتى تُستورد أقسام حقيقية عبر
`bim:catalog-import` بمفاتيح الفروع/الأنواع كـslugs.

---

## 2. إصلاح: تقاعد `CategoryPlatformServiceSeeder` المعطل

كان يكتب في جدول `category_booking_profiles` (محذوف بـmigration `2026_03_19`،
وموديله ممسوح) فيُعطّل `php artisan db:seed` بالكامل. ولم يكن يكتب إلا صفوفاً
قديمة بـ`child_id` NULL لـrestaurant (hotel/sports غير موجودتين). تفعيل الخدمات
صار على مستوى الطفل عبر services-bulk وseeders الفروع، فحُذف الـseeder وأُزيل من
`DatabaseSeeder`. **`db:seed` الكامل يكتمل الآن (exit 0) وidempotent.**

---

## 3. تنظيف: صفوف `category_platform_services` القديمة

`2026_07_13_000000_purge_legacy_root_level_category_platform_services.php` —
يحذف صفوف `child_id` NULL/0 (النمط القديم من الـseeder المتقاعد؛ 4 صفوف لـمطعم
category_id 235). idempotent؛ كل مسارات القراءة الحية تعتمد `child_id > 0`.

---

## 4. دمج: حذف نظام wallet-hold الميت

فرع `claude/heuristic-edison-f14b58` — حذف `WalletHold` + `WalletHoldService`
(استبدلهما `WalletLedgerService`) + migration
`2026_07_06_000000_drop_wallet_holds_table.php`. الجدول كان فارغاً (0 صفوف) ولا
مستدعين خارجيين. 52 اختباراً (شاملة hold/release عبر الليدجر) تنجح بعد الحذف.

---

## حالة الفروع على GitHub

- **main** = مطابق تماماً للاّب، مدفوع بالكامل.
- **39 فرعاً محلياً** كلها مرفوعة إلى `origin` كنسخ احتياطية (40 = 40).
- **36 من 39** مدموجة أصلاً في main.
- **فرعان غير مدموجين عمداً** (قرار المستخدم — يتعارضان مع إعادة بناء retail):
  - `codex/catalog-admin-phase-2` — controllers كتالوج موجودة أصلاً في main.
  - `codex/catalog-supermarket-phase2-data` — يعيد إنشاء جداول الكتالوج المحذوفة
    ونموذج السوبر ماركت القديم. **لا تُدمج** — تُرجِع عمل retail.

## ملاحظة عن قاعدة البيانات
Git يحمل الكود والـmigrations/seeders فقط، لا البيانات. تغييرات البيانات هذه
الدورة (مسح الكتالوج، زرع retail، حذف الصفوف القديمة، إسقاط wallet_holds) على
MySQL المحلي فقط. للاحتفاظ بالبيانات: `mysqldump -u root DB > backup.sql`.

## أوامر إعادة البناء من الصفر (بيئة جديدة)
```bash
git clone … && composer install
php artisan migrate
php artisan db:seed          # يبني الخدمات الخمس + الفروع + تصنيف retail
# ثم استيراد منتجات فعلية:
php artisan bim:catalog-import <section>
```
