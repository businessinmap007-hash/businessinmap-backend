# BIM Egypt Supermarket Catalog Phase 2

هذه الدفعة مخصصة للاستيراد عبر:

```bash
php artisan bim:catalog-import supermarket --dry-run
php artisan bim:catalog-import supermarket
```

## الملفات النصية

يجب أن تكون داخل:

```text
storage/app/catalog_import/supermarket/
```

## الصور

الصور لا تُخزن في قاعدة البيانات، ويتم نسخها إلى:

```text
public/files/uploads/catalog/products/supermarket/
```

الصور الحالية Placeholder آمنة للاختبار فقط وليست صور منتجات رسمية.

## ملاحظة قانونية

لا يتم استخدام صور متاجر تجارية بدون إذن أو API. الصور الرسمية يتم استبدالها لاحقًا من مصادر مرخصة أو مفتوحة أو عبر رفع صاحب المتجر.
