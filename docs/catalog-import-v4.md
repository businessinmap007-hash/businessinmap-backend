# BIM Catalog Import V4

هذا النظام يستورد كتالوج المنتجات في جداول Product Catalog V3 بدون لمس جداول البيزنس القديمة:

- `categories`
- `category_children_master`
- `products`

## مكان الملفات

ضع ملفات كل قسم داخل:

```text
storage/app/catalog_import/{section}/
```

مثال:

```text
storage/app/catalog_import/supermarket/
├── units.csv
├── categories.csv
├── category_children.csv
├── brands.csv
├── manufacturers.csv
├── attributes.csv
├── products.csv
├── product_images.csv
├── product_barcodes.csv
└── product_attribute_values.csv
```

كل ملف اختياري. لو الملف غير موجود، سيتم تخطيه.

## أمر التشغيل

تجربة بدون كتابة في قاعدة البيانات:

```bash
php artisan bim:catalog-import supermarket --dry-run
```

استيراد فعلي:

```bash
php artisan bim:catalog-import supermarket
```

مسار مخصص:

```bash
php artisan bim:catalog-import supermarket --base="D:/bim-catalog-import"
```

## فكرة التشغيل

الأمر يعمل بطريقة idempotent، أي يمكن تشغيله أكثر من مرة بدون تكرار البيانات:

- التصنيفات يتم تحديثها حسب `slug`.
- البراندات يتم تحديثها حسب `slug`.
- الوحدات يتم تحديثها حسب `code`.
- المنتجات يتم تحديثها حسب `bim_code`.
- الصور يتم تحديثها حسب `product_id + image_path`.
- الباركود يتم تحديثه حسب `barcode`.

## ترتيب الاستيراد الداخلي

1. `units.csv`
2. `categories.csv`
3. `category_children.csv`
4. `brands.csv`
5. `manufacturers.csv`
6. `attributes.csv`
7. `products.csv`
8. `product_images.csv`
9. `product_barcodes.csv`
10. `product_attribute_values.csv`

## الصور

الـ CSV يحتوي على المسار فقط، مثل:

```text
catalog/products/supermarket/milk/juhayna-full-cream-1l.webp
```

والملف نفسه يوضع داخل:

```text
public/files/uploads/catalog/products/supermarket/...
```

لا يتم تخزين الصور داخل قاعدة البيانات.

## أهم الأعمدة

### products.csv

```csv
bim_code,product_category_slug,product_category_child_slug,brand_slug,manufacturer_slug,product_type,name_ar,name_en,short_name_ar,short_name_en,model,sku,default_barcode,description_ar,description_en,main_image,image_alt_ar,image_alt_en,unit_code,package_value,package_label_ar,package_label_en,country_code,market_scope,is_verified_egypt,verification_source,search_keywords,specs_json,is_active,approval_status,sort_order
```

### product_attribute_values.csv

```csv
product_bim_code,attribute_code,value_text_ar,value_text_en,value_number,value_bool,value_json,unit_code,sort_order
```

## ملاحظات مهمة

- لا تستخدم IDs ثابتة في ملفات CSV.
- استخدم `slug` و`code` و`bim_code` فقط.
- الباركود غير المؤكد يترك فارغًا.
- الصور التجارية الرسمية لا تستخدم إلا بترخيص أو مصدر مسموح.
