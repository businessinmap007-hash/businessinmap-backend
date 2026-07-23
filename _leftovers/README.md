# _leftovers — حجر صحي للكود المهجور (للمراجعة اليدوية ثم الحذف)

هذه ملفات **ليست مربوطة بأي راوت ولا مُشار إليها بأي مكان** في الكود (فُحصت
بالاسم الكامل FQCN مقابل `route:list` وكامل الشجرة). نُقلت هنا — لا حُذفت — كي
نراجعها يدويًا ونقرّر: نعيد جزءًا للاستخدام، أو نحذف الباقي لاحقًا.

المجلد **خارج `app/`** فلا يفحصه composer (PSR-4 يربط `App\` بـ `app/` فقط)،
أي أن هذه الأصناف لم تعد تُحمَّل — وهو المقصود.

## ما بداخله (نُقل بتاريخ 2026-07-23)

طبقة الويب القديمة للعملاء (Tier 2) التي حلّت محلها واجهات v2:

| الملف | البديل الحيّ |
|---|---|
| `app/Http/Controllers/HomeController.php` | لا شيء (واجهة العميل صارت التطبيق) |
| `app/Http/Controllers/ProductController.php` | `Api\V2\*` / قوائم المنيو |
| `app/Http/Controllers/CategoryController.php` | `Api\V2\CategoryController` + `AdminV2\CategoryController` |
| `app/Http/Controllers/AddressController.php` | `Api\V2\AddressController` |
| `app/Http/Controllers/OfferController.php` | `Api\V2\BusinessOfferController` |
| `app/Http/Controllers/AlbumController.php` | `AdminV2\AlbumController` |
| `app/Http/Controllers/WishlistController.php` | — |
| `app/Http/Controllers/RateController.php` | نظام التقييم في v2 |
| `app/Http/Controllers/NotificationsController.php` | `Api\V2\*` للإشعارات |
| `app/Http/Controllers/FilesController.php` | — |
| `app/Http/Controllers/Api/OrderController.php` | `Api\V2\OrderController` (بقايا v1) |

## قوالب عرض ميتة (نُقلت 2026-07-23)

| الملف | الحالة |
|---|---|
| `resources/views/layouts/master-Old.blade.php` | قالب قديم غير مُشار إليه إطلاقًا (استبدله `layouts/master`) |
| `resources/views/layouts/app-old.blade.php` | نفس الشيء |

## لاستعادة ملف
```
git mv _leftovers/<المسار> <المسار الأصلي>
```
ثم `composer dump-autoload` (للأصناف؛ القوالب لا تحتاجه).
