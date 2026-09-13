# _leftovers — حجر صحي للكود المهجور (للمراجعة اليدوية ثم الحذف)

هذه ملفات **ليست مربوطة بأي راوت ولا مُشار إليها بأي مكان** في الكود. نُقلت
هنا — لا حُذفت — كي نراجعها يدويًا ونقرّر: نعيد جزءًا للاستخدام، أو نحذف الباقي
لاحقًا.

المجلد **خارج `app/`** فلا يفحصه composer (PSR-4 يربط `App\` بـ `app/` فقط)،
أي أن هذه الأصناف لم تعد تُحمَّل — وهو المقصود.

> ملاحظة: هذه دفعة **ثانية** (2026-09-13) بعد أن حُذفت الدفعة الأولى نهائيًا
> (كانت من 2026-07-23 و2026-09-05) — راجع تاريخ الـgit لو احتجت أصلها.

## المنهجية

كل ملف هنا اتفحص بالاسم الكامل (FQCN) مقابل `route:list` وكامل شجرة الكود
(بما فيها bim_app للـFlutter)، واتستبعد عمدًا أي شيء اتلمس بعد 2026-08-15 —
حتى لو بيبان مش مستخدم — لأنه ممكن يكون شغل شغال لسه مش متوصل بواجهة (زي نظام
QR/الدليفري اللي اتبنى في نفس الفترة ومقصود إنه يفضل حي).

## ما بداخله

### كنترولرز
| الملف | ليه ميت |
|---|---|
| `app/Http/Controllers/Auth/RegisterController.php` | راوت `register` الافتراضي متقفل صراحة في `web.php` (`Auth::routes(['register' => false])`)؛ التسجيل الحقيقي يمر بـ `RegistrationController@signup` لأن ده كان بيتخطى فحص الحظر (BlockedIdentity) |

### سيرفيسز (zero callers في كل الكود)
| الملف | ليه ميت |
|---|---|
| `app/Services/UserRegistrationService.php` | استُبدل بمنطق التسجيل الحالي |
| `app/Services/PaymentConfirmService.php` | استُبدل بـ `PaymentGatewayFactory`/`FawryGateway`/`WalletTopupService` |
| `app/Services/Catalog/CatalogCurationService.php` | رسالة الكوميت نفسها: "scaffolding, paused, not wired up" |
| `app/Services/BusinessClientRelationshipService.php` | الـhooks بتاعته ماتربطتش أبدًا بمسار الحجوزات |
| `app/Services/Commercial/OfferBoostMaintenanceService.php` | `expireDueBoosts()` مش مجدولة في أي مكان |
| `app/Services/PlatformServiceFeePromotionService.php` | zero callers |

### موديلز
| الملف | ليه ميت |
|---|---|
| `app/Models/CartItime.php` | خطأ تسمية قديم — الكلاس جواه `CartItem` لكن `App\Models\CartItem` الحقيقي والمستخدم فعليًا موجود في `CartItem.php` الصح |
| `app/Models/Orderdetail.php` | نفس النمط — الكلاس جواه `OrderItem`، والنسخة الحقيقية في `OrderItem.php` |
| `app/Models/Banner.php` | zero references، مفيش migration لجدول بانرات |
| `app/Models/BusinessGift.php` | zero references، مفيش migration |
| `app/Models/Wishlist.php` | zero references، مفيش migration (مرتبط بفيوز wishlists الميتة تحت) |
| `app/Models/ServiceOrderRejection.php` | zero references، مفيش migration للجدول أصلاً |

### قوالب Blade ميتة (من نفس جيل الدفعة الأولى، zero references)
طبقة الويب القديمة للعملاء (Tier 1) اللي التطبيق حل محلها بالكامل:
`users/agents/*` (3) · `users/campaigns/*` (3) · `users/clients/*` (4) ·
`users/providers/signup` · `users/busniess/index` (كذا الاسم فيه غلطة إملائية
أصلية) · `wishlists/*` (2) · `products/{details, index-old, reviews/review}` ·
`cart/index` · `home/index` · `payment/{after-redirect, cashu}` ·
`profile/addresses` (فيه تعليق في `web.php` بيوثق إنها متروكة عمدًا) ·
`emails/{notifyadmins, subscription, suspend}` · `layouts/_partials/header`
(partial قديم، اتأكد يدويًا إن مفيش أي `@include` ليه في أي مكان).

## استُثنيت من الحذف عمدًا رغم إنها بتبان غير مستخدمة

- **`app/Services/UserGuaranteeService.php`** — مفيش أي راوت أو كنترولر بيستدعيها
  فعليًا، لكن `tests/Feature/ServiceFeeConsentEnforcementTest.php` بيختبرها
  كجزء من قاعدة منع التهرب من الرسوم (شراء ضمان يجبر المستخدم على برنامج
  الرسوم+التقييم). دي منطق عمل حقيقي مُختبر، مجرد مش متوصل بواجهة شراء فعلية
  لسه — نفس حالة نظام QR: شغل جاهز مستني ربط، مش كود مهجور.

## لاستعادة ملف
```
git mv _leftovers/<المسار> <المسار الأصلي>
```
ثم `composer dump-autoload` (للأصناف؛ القوالب لا تحتاجه).
