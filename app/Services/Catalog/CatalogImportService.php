<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CatalogImportService
{
    protected array $stats = [];
    protected array $errors = [];
    protected bool $dryRun = false;

    public function __construct(protected CatalogDedupService $dedup)
    {
    }

    public function import(string $section, ?string $basePath = null, bool $dryRun = false): array
    {
        $this->stats = [];
        $this->errors = [];
        $this->dryRun = $dryRun;

        $section = trim($section, '/\\');
        $basePath = $basePath ?: storage_path('app/catalog_import');
        $path = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $section;

        if (! File::isDirectory($path)) {
            throw new RuntimeException("Catalog import folder not found: {$path}");
        }

        $files = [
            'units.csv' => 'upsertUnit',
            'categories.csv' => 'upsertCategory',
            'category_children.csv' => 'upsertCategoryChild',
            'brands.csv' => 'upsertBrand',
            'manufacturers.csv' => 'upsertManufacturer',
            'attributes.csv' => 'upsertAttribute',
            'products.csv' => 'upsertProduct',
            'product_images.csv' => 'upsertProductImage',
            'product_barcodes.csv' => 'upsertProductBarcode',
            'product_attribute_values.csv' => 'upsertProductAttributeValue',
        ];

        foreach ($files as $file => $method) {
            $this->importFile($path, $file, fn (array $row) => $this->{$method}($row));
        }

        return [
            'section' => $section,
            'path' => $path,
            'dry_run' => $dryRun,
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }

    protected function importFile(string $path, string $fileName, callable $callback): void
    {
        $file = $path . DIRECTORY_SEPARATOR . $fileName;
        $key = pathinfo($fileName, PATHINFO_FILENAME);

        if (! File::exists($file)) {
            $this->stats[$key] = ['skipped' => true, 'reason' => 'file_not_found'];
            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($this->readCsv($file) as $index => $row) {
            try {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                if (! $this->dryRun) {
                    DB::transaction(fn () => $callback($row));
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->errors[] = [
                    'file' => $fileName,
                    'line' => $index + 2,
                    'message' => $e->getMessage(),
                    'row' => $row,
                ];
            }
        }

        $this->stats[$key] = ['skipped' => false, 'processed' => $processed, 'failed' => $failed];
    }

    protected function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');

        if (! $handle) {
            throw new RuntimeException("Cannot open CSV file: {$file}");
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $data = array_slice(array_pad($data, count($header), null), 0, count($header));
            $row = [];

            foreach ($header as $i => $column) {
                $row[$column] = isset($data[$i]) ? $this->cleanValue($data[$i]) : null;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    protected function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?: $value;
        return Str::snake($value);
    }

    protected function cleanValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return ($value === '' || strtolower($value) === 'null') ? null : $value;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    protected function write(string $table, array $keys, array $values): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $query = DB::table($table);

        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }

        if ($query->exists()) {
            DB::table($table)->where($keys)->update(array_merge($values, ['updated_at' => $now]));
            return;
        }

        DB::table($table)->insert(array_merge($keys, $values, ['created_at' => $now, 'updated_at' => $now]));
    }

    protected function bool(mixed $value, bool $default = false): int
    {
        if ($value === null || $value === '') {
            return $default ? 1 : 0;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
    }

    protected function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function json(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? (string) $value : null;
    }

    protected function slug(?string $value, ?string $fallback = null): string
    {
        $source = $value ?: $fallback ?: Str::random(8);
        return Str::slug($source) ?: Str::random(8);
    }

    protected function idBy(string $table, string $column, ?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $id = DB::table($table)->where($column, $value)->value('id');
        return $id ? (int) $id : null;
    }

    protected function upsertUnit(array $row): void
    {
        $code = $row['code'] ?? null;
        if (! $code) {
            throw new RuntimeException('Unit code is required.');
        }

        $this->write('catalog_units', ['code' => $code], [
            'name_ar' => $row['name_ar'] ?? $code,
            'name_en' => $row['name_en'] ?? $code,
            'unit_type' => $row['unit_type'] ?? 'custom',
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
        ]);
    }

    protected function upsertCategory(array $row): void
    {
        $slug = $row['slug'] ?? $this->slug($row['name_en'] ?? null, $row['name_ar'] ?? null);

        $this->write('product_categories', ['slug' => $slug], [
            'name_ar' => $row['name_ar'] ?? $slug,
            'name_en' => $row['name_en'] ?? $slug,
            'image' => $row['image'] ?? null,
            'description_ar' => $row['description_ar'] ?? null,
            'description_en' => $row['description_en'] ?? null,
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'meta' => $this->json($row['meta'] ?? null),
        ]);
    }

    protected function upsertCategoryChild(array $row): void
    {
        $categorySlug = $row['category_slug'] ?? null;
        $categoryId = $this->idBy('product_categories', 'slug', $categorySlug);

        if (! $categoryId) {
            throw new RuntimeException("Product category not found: {$categorySlug}");
        }

        $slug = $row['slug'] ?? $this->slug($row['name_en'] ?? null, $row['name_ar'] ?? null);

        $this->write('product_category_children', ['product_category_id' => $categoryId, 'slug' => $slug], [
            'name_ar' => $row['name_ar'] ?? $slug,
            'name_en' => $row['name_en'] ?? $slug,
            'image' => $row['image'] ?? null,
            'description_ar' => $row['description_ar'] ?? null,
            'description_en' => $row['description_en'] ?? null,
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'meta' => $this->json($row['meta'] ?? null),
        ]);
    }

    protected function upsertBrand(array $row): void
    {
        $slug = $row['slug'] ?? $this->slug($row['name_en'] ?? null, $row['name_ar'] ?? null);

        $this->write('catalog_brands', ['slug' => $slug], [
            'name_ar' => $row['name_ar'] ?? $slug,
            'name_en' => $row['name_en'] ?? $slug,
            'logo' => $row['logo'] ?? null,
            'website' => $row['website'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'is_verified' => $this->bool($row['is_verified'] ?? 0),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'meta' => $this->json($row['meta'] ?? null),
        ]);
    }

    protected function upsertManufacturer(array $row): void
    {
        $slug = $row['slug'] ?? $this->slug($row['name_en'] ?? null, $row['name_ar'] ?? null);

        $this->write('catalog_manufacturers', ['slug' => $slug], [
            'name_ar' => $row['name_ar'] ?? $slug,
            'name_en' => $row['name_en'] ?? $slug,
            'website' => $row['website'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'meta' => $this->json($row['meta'] ?? null),
        ]);
    }

    protected function upsertAttribute(array $row): void
    {
        $code = $row['code'] ?? null;
        if (! $code) {
            throw new RuntimeException('Attribute code is required.');
        }

        $this->write('catalog_attributes', ['code' => $code], [
            'name_ar' => $row['name_ar'] ?? $code,
            'name_en' => $row['name_en'] ?? $code,
            'data_type' => $row['data_type'] ?? 'text',
            'unit_id' => $this->idBy('catalog_units', 'code', $row['unit_code'] ?? null),
            'is_filterable' => $this->bool($row['is_filterable'] ?? 0),
            'is_variant_axis' => $this->bool($row['is_variant_axis'] ?? 0),
            'is_required' => $this->bool($row['is_required'] ?? 0),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
        ]);
    }

    protected function upsertProduct(array $row): void
    {
        $bimCode = $row['bim_code'] ?? null;
        if (! $bimCode) {
            throw new RuntimeException('Product bim_code is required.');
        }

        $categoryId = $this->idBy('product_categories', 'slug', $row['product_category_slug'] ?? null);
        $childId = $this->idBy('product_category_children', 'slug', $row['product_category_child_slug'] ?? null);

        if (! $categoryId || ! $childId) {
            throw new RuntimeException("Product category/child not found for {$bimCode}");
        }

        $brandId = $this->idBy('catalog_brands', 'slug', $row['brand_slug'] ?? null);
        $normalized = $row['normalized_name_ar'] ?? $this->dedup->normalizeName($row['name_ar'] ?? $bimCode);
        $barcode = $row['default_barcode'] ?? null;

        $dedupKey = $this->dedup->dedupKey([
            'default_barcode' => $barcode,
            'normalized_name_ar' => $normalized,
            'brand_id' => $brandId,
            'package_label_ar' => $row['package_label_ar'] ?? null,
            'package_value' => $row['package_value'] ?? null,
        ]);

        $values = [
            'product_category_id' => $categoryId,
            'product_category_child_id' => $childId,
            'brand_id' => $brandId,
            'manufacturer_id' => $this->idBy('catalog_manufacturers', 'slug', $row['manufacturer_slug'] ?? null),
            'product_type' => $row['product_type'] ?? 'simple',
            'name_ar' => $row['name_ar'] ?? $bimCode,
            'normalized_name_ar' => $normalized,
            'name_en' => $row['name_en'] ?? null,
            'short_name_ar' => $row['short_name_ar'] ?? null,
            'short_name_en' => $row['short_name_en'] ?? null,
            'model' => $row['model'] ?? null,
            'sku' => $row['sku'] ?? null,
            'default_barcode' => $barcode,
            'description_ar' => $row['description_ar'] ?? null,
            'description_en' => $row['description_en'] ?? null,
            'main_image' => $row['main_image'] ?? null,
            'image_alt_ar' => $row['image_alt_ar'] ?? null,
            'image_alt_en' => $row['image_alt_en'] ?? null,
            'unit_id' => $this->idBy('catalog_units', 'code', $row['unit_code'] ?? null),
            'package_value' => $this->decimal($row['package_value'] ?? null),
            'package_label_ar' => $row['package_label_ar'] ?? null,
            'package_label_en' => $row['package_label_en'] ?? null,
            'country_code' => $row['country_code'] ?? 'EG',
            'market_scope' => $row['market_scope'] ?? 'egypt',
            'is_verified_egypt' => $this->bool($row['is_verified_egypt'] ?? 0),
            'verification_source' => $row['verification_source'] ?? null,
            'search_keywords' => $row['search_keywords'] ?? null,
            'specs_json' => $this->json($row['specs_json'] ?? null),
            'is_active' => $this->bool($row['is_active'] ?? 1, true),
            'approval_status' => $row['approval_status'] ?? 'approved',
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'dedup_key' => $dedupKey,
        ];

        $now = now()->format('Y-m-d H:i:s');

        // Same natural key → plain update (re-import of the same product).
        $existingId = DB::table('catalog_products')->where('bim_code', $bimCode)->value('id');
        if ($existingId) {
            DB::table('catalog_products')->where('id', $existingId)->update($values + ['updated_at' => $now]);
            return;
        }

        // Barcode/name matches an existing master → ingest as a linked, soft-
        // deleted duplicate instead of polluting the active catalog.
        $masterId = $this->dedup->findMasterId($dedupKey);
        if ($masterId) {
            DB::table('catalog_products')->insert($values + [
                'bim_code' => $bimCode,
                'duplicate_master_id' => $masterId,
                'duplicate_status' => 'duplicate',
                'curation_status' => 'duplicate',
                'deleted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        // New master → route to curation review unless already verified.
        DB::table('catalog_products')->insert($values + [
            'bim_code' => $bimCode,
            'duplicate_status' => 'unique',
            'curation_status' => $this->bool($row['is_verified_egypt'] ?? 0) ? 'verified' : 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function upsertProductImage(array $row): void
    {
        $productId = $this->idBy('catalog_products', 'bim_code', $row['product_bim_code'] ?? null);
        $imagePath = $row['image_path'] ?? null;

        if (! $productId || ! $imagePath) {
            throw new RuntimeException('Product and image_path are required for image.');
        }

        $this->write('catalog_product_images', ['product_id' => $productId, 'image_path' => $imagePath], [
            'image_type' => $row['image_type'] ?? 'gallery',
            'width' => $this->int($row['width'] ?? 0),
            'height' => $this->int($row['height'] ?? 0),
            'size_bytes' => $this->int($row['size_bytes'] ?? 0),
            'alt_ar' => $row['alt_ar'] ?? null,
            'alt_en' => $row['alt_en'] ?? null,
            'is_primary' => $this->bool($row['is_primary'] ?? 0),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
            'source_name' => $row['source_name'] ?? null,
            'source_url' => $row['source_url'] ?? null,
            'license_note' => $row['license_note'] ?? null,
        ]);
    }

    protected function upsertProductBarcode(array $row): void
    {
        $barcode = $row['barcode'] ?? null;
        if (! $barcode) {
            return;
        }

        $productId = $this->idBy('catalog_products', 'bim_code', $row['product_bim_code'] ?? null);
        if (! $productId) {
            throw new RuntimeException('Product not found for barcode.');
        }

        $this->write('catalog_product_barcodes', ['barcode' => $barcode], [
            'product_id' => $productId,
            'barcode_type' => $row['barcode_type'] ?? 'ean13',
            'package_label_ar' => $row['package_label_ar'] ?? null,
            'package_label_en' => $row['package_label_en'] ?? null,
            'is_primary' => $this->bool($row['is_primary'] ?? 0),
            'is_verified' => $this->bool($row['is_verified'] ?? 0),
            'source_name' => $row['source_name'] ?? null,
        ]);
    }

    protected function upsertProductAttributeValue(array $row): void
    {
        $productId = $this->idBy('catalog_products', 'bim_code', $row['product_bim_code'] ?? null);
        $attributeId = $this->idBy('catalog_attributes', 'code', $row['attribute_code'] ?? null);

        if (! $productId || ! $attributeId) {
            throw new RuntimeException('Product or attribute not found for attribute value.');
        }

        $this->write('catalog_product_attribute_values', [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'value_text_ar' => $row['value_text_ar'] ?? null,
            'value_text_en' => $row['value_text_en'] ?? null,
            'value_number' => $this->decimal($row['value_number'] ?? null),
        ], [
            'unit_id' => $this->idBy('catalog_units', 'code', $row['unit_code'] ?? null),
            'value_bool' => array_key_exists('value_bool', $row) ? $this->bool($row['value_bool']) : null,
            'value_json' => $this->json($row['value_json'] ?? null),
            'sort_order' => $this->int($row['sort_order'] ?? 0),
        ]);
    }
}
