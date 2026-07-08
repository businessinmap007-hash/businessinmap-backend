# خصائص تطبيق BIM — الدليل الشامل

منصّة سوق/حجوزات (Laravel) تربط العملاء بالأنشطة التجارية عبر خدمات منصّة موحّدة،
مع نظام مالي (محفظة + تأمين + ضمان) وطبقة عروض موحّدة (حجز مخصّص + منيو + تجزئة).
هذا الملف يشرح كل خاصية، ونماذجها/خدماتها/نقاطها الأساسية.

> النموذج الموحّد: **تصنيف → خدمات منصّة → أنواع عناصر ضمن فروع (العرض = الفلتر = الفهرس) → معاملات (حجز/طلب + تأمين/محفظة)**.
> تفصيل معماري: [architecture-blueprint.md](architecture-blueprint.md) · لوحة البزنس والتسعير: [business-panel-and-services.md](business-panel-and-services.md) · مخطط الخدمات: [services-blueprint.md](services-blueprint.md).

## الفهرس
1. [التصنيف وخدمات المنصّة](#1-التصنيف-وخدمات-المنصّة)
2. [أنواع العناصر والفروع](#2-أنواع-العناصر-والفروع)
3. [التسعير (business_service_prices)](#3-التسعير)
4. [طبقة العروض الموحّدة](#4-طبقة-العروض-الموحّدة)
5. [الكتالوج ومنتجات التجزئة](#5-الكتالوج-ومنتجات-التجزئة)
6. [لوحة صاحب البزنس (/business)](#6-لوحة-صاحب-البزنس)
7. [اكتشاف العميل + السلة](#7-اكتشاف-العميل--السلة)
8. [دورة حياة الحجز (ServiceExecutionEngine)](#8-دورة-حياة-الحجز)
9. [نظام التأمين (Deposit)](#9-نظام-التأمين)
10. [نظام الضمان (Guarantee)](#10-نظام-الضمان)
11. [المحفظة والرسوم](#11-المحفظة-والرسوم)
12. [النزاعات](#12-النزاعات)
13. [لوحة الأدمن (AdminV2)](#13-لوحة-الأدمن)
14. [التغطية الاختبارية](#14-التغطية-الاختبارية)

---

## 1. التصنيف وخدمات المنصّة
تسلسل التصنيف: **category → category child**. كل قسم فرعي (child) تُربط به **خدمات منصّة**
(`platform_services`) عبر `category_platform_service` — مثل الحجز (booking)، المنيو/الطلب.
- كل خدمة لها `key` واسم ومفتاح `supports_deposit` (هل تدعم التأمين).
- الخدمات المتاحة لقسم فرعي تُدار من **Service Catalog Matrix** في لوحة الأدمن.
- **Options متقاعدة** كآلية اكتشاف؛ ما تبقّى منها يُدار كسمات (attributes) فقط.

## 2. أنواع العناصر والفروع
- **نوع العنصر** (`platform_service_item_type`) = ما يقدّمه البزنس فعليًا داخل خدمة (غرفة، ملعب، طاولة...).
- **الفروع** (branches / groups) تنظيمية فقط، **many-to-many** عبر `platform_service_item_group_type`
  (نفس النوع قد يقع تحت أكثر من فرع، مثل "غرفة" تحت "فنادق" و"وحدات سكنية").
- **مبدأ العرض = الفلتر = الفهرس**: نوع العنصر المُسعّر لدى البزنس هو في آنٍ عرضُه، وفلترُ العميل، وفهرسُ البحث.
- تُدار من: Service Branch Board (مصفوفة) + مدير عضوية الفرع في لوحة الأدمن.

## 3. التسعير
- المصدر الوحيد للأسعار: **`business_service_prices`** (سطر لكل نوع عنصر يقدّمه البزنس).
- الأعمدة: `price`, `charge_mode` (standard/free/reservation_fee/minimum_charge), `charge_amount`, `currency`, `discount_enabled/percent`.
- **الحلّ** عبر [`BusinessServicePriceResolver`](../app/Services/BusinessServicePriceResolver.php) بأولوية:
  نفس القسم+نوع محدّد ← نفس القسم+النوع الافتراضي ← نفس القسم+أي ← بلا قسم (نفس التسلسل).
- الوحدات (`bookable_items`) = مخزون فقط (لا سعر/تأمين عليها — أُزيلت في التنظيف).
- **الفاتورة الموحّدة**: رسوم الوحدة + الطعام المرفق، مع تأمين مُشتقّ من السياسة ([`BookingFoodService`](../app/Services/BookingFoodService.php)).

## 4. طبقة العروض الموحّدة
كل ما يبيعه البزنس، بثلاثة مصادر موحّدة في سطور الطلب `order_items` متعددة الأشكال (`offering_type`/`offering_id`):
- **حجز مخصّص (bespoke)** ← `business_service_prices` (خدمات الحجز).
- **منيو (menu)** ← `menu_items` (طعام).
- **تجزئة (retail)** ← `business_catalog_listings` (منتجات من الكتالوج المشترك).
`MenuOrderService::addOffering()` يضيف أي عرض بسعر يأتي من المصدر (لا يُوثَق بالعميل)؛ `recalc()` يجمع الإجماليات.

## 5. الكتالوج ومنتجات التجزئة
- **`catalog_products`** = ماستر عالمي مشترك (بيانات مُنسّقة، ليست لكل بزنس)، مُنظّف من التكرار
  (dedup بالباركود ثم الاسم/البراند/العبوة). المكرر حُذف نهائيًا مع إبقاء الأقل id.
- **منتجاتي** (`business_catalog_listings`): البزنس يُدرِج منتجًا من الماستر بسعره ومخزونه
  ([`Business\CatalogListingController`](../app/Http/Controllers/Business/CatalogListingController.php)، `/business/products`) — فقط الماستر النشط غير المكرر قابل للإدراج.
- شاشة الأدمن `admin-v2/catalog-products` تصفّي المحذوف soft-delete.
- أدوات الاستيراد: [`CatalogImportService`](../app/Services/Catalog/CatalogImportService.php) + [`CatalogDedupService`](../app/Services/Catalog/CatalogDedupService.php) + الأمر `bim:catalog-dedup` — الاستيراد يدمج بالباركود/الاسم ويوجّه الجديد للتنسيق (curation).

## 6. لوحة صاحب البزنس
`/business` (middleware `business.panel`، محصورة بـ `business_id = Auth::id()`):
- **عروضي** (`OfferingController`, `/business/offerings`): جدول موحّد لكل ما يبيعه (خدمات + منيو + تجزئة) بوسم المصدر وتصفية.
- **أسعاري** (`prices`) · **المنيو** (`menu`) · **منتجاتي** (`products`) · **وحداتي** (`bookable-items`).
- **حجوزاتي** (`bookings`) · **الطلبات** (`orders`): طلب مختلط (منيو + تجزئة) في طلب واحد.

## 7. اكتشاف العميل + السلة
API عامة (Api/V2):
- **اكتشاف مخصّص** ([`DiscoveryController`](../app/Http/Controllers/Api/V2/DiscoveryController.php)): `discovery/filters` (خدمات + أنواع لها أسعار نشطة، بعدّادات) و`discovery/businesses` (البزنس الذي يقدّم الفلتر).
- **اكتشاف التجزئة** ([`RetailDiscoveryController`](../app/Http/Controllers/Api/V2/RetailDiscoveryController.php)): `retail/filters` · `retail/products` (تصفّح + نطاق سعر + عدد البائعين) · `retail/products/{id}` (كل بزنس يبيعه، الأرخص أولًا).

**سلة العميل** (مصادَقة، [`CartController`](../app/Http/Controllers/Api/V2/CartController.php)): السلة = طلب مسودّة (`Order` status='cart') لكل بزنس، عناصرها `order_items`.
- `GET cart` · `POST cart/items` (retail/menu) · `PATCH/DELETE cart/items/{item}` · `POST cart/{business}/checkout` (يقلب الحالة إلى pending).
- السعر دائمًا من الخادم؛ دمج نفس العرض؛ تقسيم لكل بزنس.

## 8. دورة حياة الحجز
[`ServiceExecutionEngine`](../app/Services/ServiceExecutionEngine.php):
- `prepare/preview`: يحلّ السعر + سياسة التأمين + توفّر الوحدة.
- `financialPreview` / `ensureFinancialReadiness`: يحسب المطلوب (تأمين + رسوم) والجاهزية، ويُرجِع **خيارات** عند القصور (طلب ضمان صديق / ترقية / زيادة رصيد).
- `moveBookingToInProgress`: حارس الحالة (pending/accepted فقط) + تأكيد الطرفين + تجميد التأمين + خصم الرسوم مرة واحدة + قلب الحالة إلى `in_progress`.
- الحالات: pending · accepted · rejected · cancelled · in_progress · completed.

## 9. نظام التأمين
- **مصدر واحد**: `business_deposit_policies` عبر [`BookingDepositPolicyResolver`](../app/Services/BookingDepositPolicyResolver.php) → [`BookingDepositCalculator`](../app/Services/BookingDepositCalculator.php) (نسبة من الأساس، بحدّ نظام 20%، حدّ أدنى/أقصى، أساس الحساب).
- **بديل الديبوزت جزئيًا**: تغطية الضمان (ذاتي + أصدقاء) تُطبَّق أولًا، والمحفظة تحجز **الباقي فقط** (مثال: ضمان 1500 + رصيد 200 يغطّي 1700).
- **الاحتجاز (escrow)** ([`DepositsEscrowService`](../app/Services/DepositsEscrowService.php)): freeze يحجز من المحفظة (متاح→محجوز)، release يعيده، refund للاسترداد — كلها idempotent؛ تحمّل طرفًا بقيمة 0.
- **إيداع خارجي**: submit → verify (يحسب المتبقّي).
- الرسوم **لا** تُخصَم من التأمين/الضمان — من الرصيد فقط.

## 10. نظام الضمان
نظام **يُشترى من المنصّة حصريًا**، بديل عن الديبوزت، **يُجمَّد ولا يُخصَم منه**:
- **المستويات** (`guarantee_levels`: برونزي/فضي/ذهبي/ماسي): لكل مستوى قيمة مطلوبة تُقفَل من المحفظة (`balance → locked_balance`) وسعة تغطية.
- **`user_guarantees`** (ذاتي بالكامل): `locked_amount`, `current/used_coverage_amount`, `status` (active/pending_operations/underfunded/suspended/cancelled).
  التغطية المتاحة = `current − used`؛ التجميد يرفع `used`، الإفراج يخفضه.
- **التفعيل** ([`GuaranteeActivationService`](../app/Services/Guarantees/GuaranteeActivationService.php)) · الترقية/التخفيض التلقائي · الانتهاء/فترة السماح.
- **ضمان الصديق لعملية** (co-guarantor) — [`OperationGuarantorService`](../app/Services/Guarantees/OperationGuarantorService.php) + جدول `operation_guarantors`:
  عند نقص تغطيتي، أدعو صديقًا فتُجمَع سعة ضمانه مع سعتي لهذه العملية فقط. القبول يجمّد حصّة الصديق؛ البدء يجمّد حصّتي؛ الإفراج عند الإتمام/حل النزاع. **التجميد بقيمة العملية فقط** (لا الضمان كله).
  - API: `GET/POST bookings/{booking}/guarantors` (العميل يدعو) · `POST guarantors/{id}/accept|decline` (الصديق) — تفويض صارم.
- **فكّ الضمان لرصيد** ([`GuaranteeUnlockService`](../app/Services/Guarantees/GuaranteeUnlockService.php)): يعكس التفعيل (`locked_balance → balance`) ويُلغي الضمان — **فقط** إن لم تكن أي تغطية محجوزة لعملية جارية. عبر `POST v2/guarantees/unlock` (العميل) أو زرّ في شاشة ضمان الأدمن.
- **`guarantee_transactions`**: سجل كل حركة (lock/unlock/upgrade/downgrade/penalty/suspend/activate/... + أفعال الأدمن) بمفتاح idempotency.

## 11. المحفظة والرسوم
- **المحفظة** (`wallets`: `balance` المتاح + `locked_balance` المحجوز) — [`WalletService`](../app/Services/WalletService.php): deposit/withdraw/hold/release/refund/transfer، كلها بـ idempotency وقفل صف.
- **رسوم المنصّة** ([`WalletFeeService`](../app/Services/WalletFeeService.php)): تُحسب من `category_child_service_fees` (+ عروض/إعفاءات `platform_service_fee_promotions`).
  - **بوابة الموافقة**: لا خصم تلقائي بلا `user_service_fee_consents.fee_auto_charge_enabled`.
  - idempotent بمفتاح `booking_fee:{bookingId}:{feeCode}:{payer}`؛ تُخصَم من الرصيد فقط.
- خدمة "الدفع لكل عملية" مرتبطة باشتراك البزنس في خدمة المنصّة (يظهر الترتيب/العروض حينها).

## 12. النزاعات
[`DisputeService`](../app/Services/DisputeService.php) + `disputes`:
- `openForBooking`: يُفتح عند عدم إتمام العملية (يتطلّب وديعة غير نهائية)، idempotent، بحالة `mutual_resolution` ومهلة/تحذيرات.
- طوال النزاع تبقى الوديعة **وتغطية الضمان (ذاتي + أصدقاء) مجمّدة**.
- `resolve` (release_business / refund_client / split / no_action): يُفرِج/يستردّ الوديعة **ويُطلق تغطية الضمان** فيعود لكل طرف ضمانه كما كان.

## 13. لوحة الأدمن
`/admin` (AdminV2، middleware `admin.v2`):
- إدارة التصنيفات وخدمات المنصّة وأنواع العناصر والفروع (المصفوفات).
- كتالوج المنتجات/البراندات/الوحدات/الفئات.
- الأسعار (`business-service-prices`)، المنيو، الوحدات وقواعد التسعير/التقويم.
- **الحجوزات**: إنشاء/عرض + إجراءات (قبول/بدء/تنفيذ) + معاينة مالية.
- **الضمانات** (`guarantees`): عرض + إجراءات (sync-coverage / suspend / reactivate / expire / auto-upgrade / auto-downgrade / **فكّ وتحويل لرصيد**) + مستويات الضمان.
- المحفظة والمعاملات والمدفوعات والنزاعات والشراكات (`business_partnerships`) والتخصيصات (`bookable_allocations`).

## 14. التغطية الاختبارية
`tests/Feature` + `tests/Unit` (تعمل على قاعدة التطوير بـ `DatabaseTransactions` — لا تمسّ البيانات):
- الاكتشاف (مخصّص + تجزئة) · سلة العميل · حلّ السعر.
- النواة المالية: حاسبة التأمين · دفتر المحفظة · رسوم المنصّة (+ بوابة الموافقة) · دورة الوديعة (freeze/release/refund/خارجي/نزاع) · دورة حياة الحجز (حرّاس + مسار سعيد بلا/مع تأمين) · تغطية الضمان.
- الضمان: ضمان الصديق (دعوة/قبول/تجميد/إفراج) · قفل الذاتي · تكامل النزاع · الفكّ لرصيد · إصلاحات enum/idempotency.
- الأمان: منع تلاعب الترتيب (`ranking_score`) · قفل إعادة البيع بالشراكة.

**أخطاء حقيقية اكتُشفت وأُصلحت أثناء بناء الاختبارات:** إضافة `platform_fee`/أفعال الأدمن لـ enums المعاملات؛ السماح بطرف escrow بقيمة 0؛ idempotency التخفيض؛ وإزالة أعمدة التأمين المحذوفة من استعلامات كنترولر الحجز.
