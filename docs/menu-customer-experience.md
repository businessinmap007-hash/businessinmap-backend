# تجربة منيو المطعم للعميل — 3 قطع (2026-07-14)

سدّت الفجوات الثلاث التي كشفها النقاش في تجربة العميل مع المنيو. العمود الفقري
(نموذج البيانات، التسعير من الخادم، السلة→الطلب→الفاتورة) كان موجوداً؛ هذه الدورة
أضافت الطبقة المواجهة للعميل.

## 1. أقسام المنيو (owner)

- جدول `menu_sections` (per-business) + `menu_items.menu_section_id`
  (migration `2026_07_14_000000_create_menu_sections_and_link.php`؛ العمود القديم
  `category_id` تُرك دون مساس).
- `Business\MenuSectionController` → `business.menu-sections.*` (CRUD مقيّد
  بالنشاط). حذف قسم يجعل أصنافه بلا قسم (`nullOnDelete`) لا يحذفها.
- شاشة «My Menu» تكسب قائمة اختيار القسم لكل صنف، وعمود القسم في الفهرس.

## 2. تصفّح العميل للمنيو (`MenuDiscoveryController`)

`GET /api/v2/discovery/menu/{business}` (عام، بلا auth) — نظير
`RetailDiscoveryController`. يرجّع:
```
data.business { id, name, logo }
data.sections[] { id, name, items[] {
  id, name, description, image, base_price,
  variants[] { id, name, type, price, is_default },   // price عبر MenuItemVariant::resolvePrice
  extras[]   { id, name, group_key, price, max_qty }
} }
```
النشطة فقط؛ الأصناف بلا قسم (أو قسم غير نشط) تحت دلو «أخرى».

## 3. الأحجام/الإضافات في لوحة المالك + السلة

- **المالك:** `Business\MenuItemVariantController` + `MenuItemExtraController`
  (`business.menu.variants.*` / `business.menu.extras.*`) — إدارة سطرية في صفحة
  تعديل الصنف، مقيّدة بأن الصنف يخصّ المالك.
- **السلة:** `POST /api/v2/cart/items` يقبل اختيارياً `size_id` + `extras[]`.
  `CustomerCartService::resolveOffering` يتحقق أن الـvariant/extras تخصّ الصنف
  ونشطة، ويحسب سعر الوحدة خادمياً = `variant.resolvePrice(base)` + Σ(extra.price)
  — **لا يُقبل أي سعر من العميل**. يخزّن `order_items.size_id` + `addons`
  (JSON [{id,name,price,qty}]).
- **الدمج بالبصمة:** سطران بنفس الصنف واختيارات مختلفة يبقيان منفصلين؛ المتطابقان
  (بصمة = size_id + أزواج (extra id, qty) مرتّبة) يندمجان وتُجمع الكمية.
- عرض السلة يُظهر `options.size` و`options.extras[]` لكل سطر.

## التوافق الرجعي
retail غير متأثر (لا variants له)؛ كل استدعاءات `addItem` القديمة بلا
`size_id/extras` تعمل كما هي.

## الاختبارات
`tests/Concerns/SeedsMenu.php` (قالب زرع) + `MenuDiscoveryTest`،
`MenuCartCustomizationTest`، `MenuSectionOwnerTest` — 11 اختباراً. الإجمالي ذو
الصلة 63 أخضر.

## خارج النطاق (BIM-code تالٍ)
**السلة الجماعية المشتركة** (أصدقاء/عائلة يوحّدون طلبهم على سلة واحدة عبر
دعوة/انضمام مع تتبّع «من طلب ماذا») — ميزة كبيرة منفصلة لم تُبنَ. تصميم السلة الحالي
لا يعيقها: `orders.user_id` يبقى مالك السلة، والمشاركون يُضافون لاحقاً بجدول
`order_participants` + نسبة كل مشارك من السطور، دون كسر الحالي.
