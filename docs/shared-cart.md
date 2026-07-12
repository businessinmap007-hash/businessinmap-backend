# السلة الجماعية المشتركة (Shared / Group Cart) — 2026-07-15

حالة الاستخدام: أصدقاء/عائلة على مطعم واحد، سلة واحدة ينضم إليها الجميع ويختار
كلٌّ بنفسه، مع تتبّع «من طلب ماذا»، وفاتورة موحّدة يدفعها المضيف. MVP عبر API
(واجهة العميل تطبيق).

## نموذج البيانات
سلة مشتركة = نفس الـ`Order` المسودة (مالكها المضيف `orders.user_id`)، مع:
- `orders.share_token` (فريد) + `orders.is_shared`.
- جدول `order_participants` (order_id, user_id, role host|member).
- `order_items.added_by_user_id` — من أضاف السطر (null = سلة شخصية، توافق رجعي).

migration: `2026_07_15_000000_create_shared_cart.php` (كله محروس/idempotent).

## التدفّق (API، كله `auth:sanctum`)
| Endpoint | من | الفعل |
|---|---|---|
| `POST /api/v2/cart/{business}/share` | المضيف | يفتح سلته للمشاركة → `share_token` (idempotent) |
| `POST /api/v2/cart/join/{token}` | صديق | ينضمّ (توكن خطأ → 404) |
| `GET /api/v2/cart/shared/{order}` | مشارك | عرض: participants + أنصبة + items منسوبة |
| `POST /api/v2/cart/shared/{order}/items` | مشارك | يضيف صنفاً (size_id/extras) منسوباً له |
| `PATCH/DELETE .../items/{item}` | صاحب السطر أو المضيف | تعديل/حذف |
| `POST .../checkout` | **المضيف فقط** | إتمام → طلب pending، فاتورة واحدة |
| `POST .../leave` | عضو | يغادر وتُحذف سطوره (المضيف لا يغادر) |

## المنطق (`CustomerCartService`)
- إعادة تشكيل: `mergeOrCreateLine(cart, offeringId, resolved, qty, addedBy)`
  يخدم المسارين (الشخصي addedBy=null، المشترك addedBy=المستخدم).
- **بصمة الدمج تضمّ `added_by`** → صنف واحد من شخصين = سطران منفصلان منسوبان،
  وتكرار الشخص نفسه يندمج.
- التسعير خادمي دائماً (حجم + إضافات)، لا يُقبل من العميل.
- الصلاحيات: `participantOrFail` (403 لغير المشارك)، `editableSharedLine`
  (صاحب السطر أو المضيف)، checkout/leave يفحصان الدور.
- `placeOrder` مشترك بين checkout الشخصي والمشترك.

## الدفع والفوترة (كاش عند الاستلام)
الدفع **كله كاش عند وصول الطلب**، وكل مشارك يرى **فاتورته الخاصة** محسوبة على
**طلبه هو فقط**:
- `items_subtotal` = مجموع أصنافه.
- `service_fee` = رسم المنصة (client fee من `CategoryChildServiceFee` لـ(طفل
  البزنس + خدمة menu)، fixed أو percent) — يُعاد استخدام `amountFor(PAYER_CLIENT)`.
- `tax` = نسبة عامة (config `bim.menu_tax_rate_percent`، افتراضي 14% مصر) على
  (أصنافه + رسم خدمته).
- `total` = المجموع.

المنطق في `MenuBillingService` (`feeRowForBusiness` + `bill`). `SharedCartController`
يفرض `payment_method='cash'` عند checkout.

## العرض
`participants[]{user_id,name,role,items_count,items_subtotal,service_fee,tax,total}`
(فاتورة كل شخص) + `items[]{...,added_by,options}` +
`totals{items,items_subtotal,service_fee,tax,grand_total}` +
`payment_method:'cash'`. `grand_total` = مجموع فواتير الجميع.

## الاختبارات
`SharedCartTest` (8): مشاركة idempotent، انضمام/توكن خطأ، نسب السطور والإجمالي،
منع غير المشارك (403)، منع تعديل سطر الغير، checkout للمضيف فقط، مغادرة العضو.
الإجمالي ذو الصلة 71 أخضر.

## توسعات مؤجّلة (خارج MVP)
- تطبيق نفس الفوترة (رسم خدمة + ضريبة) على **الطلبات الشخصية** (`CartController`) —
  الآن مطبّقة على السلة المشتركة فقط؛ الطلب الشخصي ما زال طعاماً خاماً (تفادياً
  لكسر اختبارات cart القائمة). يُنسّق مع المستخدم لاحقاً.
- ضريبة يحددها المطعم (بدل النسبة العامة) لو اختلف التسجيل الضريبي.
- إشعار المضيف عند انضمام عضو (`InAppNotificationService` جاهز)؛ إلغاء السلة؛ واجهة ويب.
