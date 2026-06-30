<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\File;
use RuntimeException;

class CatalogDataCollectorService
{
    protected array $errors = [];
    protected array $stats = [];

    public function collect(string $section, ?string $basePath = null): array
    {
        $this->errors = [];
        $this->stats = [];

        $section = trim($section, '/\\');
        $basePath = $basePath ?: storage_path('app/catalog_data');
        $sourcePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $section . DIRECTORY_SEPARATOR . 'raw';
        $exportPath = storage_path('app/catalog_import/' . $section);

        if (! File::isDirectory($sourcePath)) {
            throw new RuntimeException("Raw catalog folder not found: {$sourcePath}");
        }

        File::ensureDirectoryExists($exportPath);

        $products = $this->readCsvIfExists($sourcePath . DIRECTORY_SEPARATOR . 'products_raw.csv');
        $brands = [];
        $manufacturers = [];
        $categories = [];
        $children = [];
        $units = [];
        $attributes = [];
        $images = [];
        $barcodes = [];
        $attributeValues = [];
        $cleanProducts = [];

        foreach ($products as $index => $row) {
            try {
                $clean = $this->cleanProductRow($row, $index + 2);

                $categories[$clean['product_category_slug']] = [
                    'slug' => $clean['product_category_slug'],
                    'name_ar' => $clean['product_category_name_ar'] ?: $clean['product_category_slug'],
                    'name_en' => $clean['product_category_name_en'] ?: $clean['product_category_slug'],
                    'is_active' => 1,
                    'sort_order' => 10,
                ];

                $children[$clean['product_category_child_slug']] = [
                    'category_slug' => $clean['product_category_slug'],
                    'slug' => $clean['product_category_child_slug'],
                    'name_ar' => $clean['product_category_child_name_ar'] ?: $clean['product_category_child_slug'],
                    'name_en' => $clean['product_category_child_name_en'] ?: $clean['product_category_child_slug'],
                    'is_active' => 1,
                    'sort_order' => 10,
                ];

                if ($clean['brand_slug']) {
                    $brands[$clean['brand_slug']] = [
                        'slug' => $clean['brand_slug'],
                        'name_ar' => $clean['brand_name_ar'] ?: $clean['brand_slug'],
                        'name_en' => $clean['brand_name_en'] ?: $clean['brand_slug'],
                        'country_code' => $clean['brand_country_code'] ?: 'EG',
                        'is_active' => 1,
                        'is_verified' => 0,
                        'sort_order' => 10,
                    ];
                }

                if ($clean['manufacturer_slug']) {
                    $manufacturers[$clean['manufacturer_slug']] = [
                        'slug' => $clean['manufacturer_slug'],
                        'name_ar' => $clean['manufacturer_name_ar'] ?: $clean['manufacturer_slug'],
                        'name_en' => $clean['manufacturer_name_en'] ?: $clean['manufacturer_slug'],
                        'country_code' => $clean['manufacturer_country_code'] ?: 'EG',
                        'is_active' => 1,
                        'sort_order' => 10,
                    ];
                }

                if ($clean['unit_code']) {
                    $units[$clean['unit_code']] = [
                        'code' => $clean['unit_code'],
                        'name_ar' => $clean['unit_name_ar'] ?: $clean['unit_code'],
                        'name_en' => $clean['unit_name_en'] ?: $clean['unit_code'],
                        'unit_type' => $clean['unit_type'] ?: 'custom',
                        'is_active' => 1,
                        'sort_order' => 10,
                    ];
                }

                if ($clean['main_image']) {
                    $images[] = [
                        'product_bim_code' => $clean['bim_code'],
                        'image_path' => $clean['main_image'],
                        'image_type' => 'main',
                        'width' => null,
                        'height' => null,
                        'size_bytes' => null,
                        'alt_ar' => $clean['name_ar'],
                        'alt_en' => $clean['name_en'],
                        'is_primary' => 1,
                        'sort_order' => 10,
                        'source_name' => $clean['source_name'],
                        'source_url' => $clean['source_url'],
                        'license_note' => $clean['image_license_note'] ?: 'Needs review before production use',
                    ];
                }

                if ($clean['default_barcode']) {
                    $barcodes[] = [
                        'product_bim_code' => $clean['bim_code'],
                        'barcode' => $clean['default_barcode'],
                        'barcode_type' => $this->barcodeType($clean['default_barcode']),
                        'package_label_ar' => $clean['package_label_ar'],
                        'package_label_en' => $clean['package_label_en'],
                        'is_primary' => 1,
                        'is_verified' => $clean['barcode_verified'] ? 1 : 0,
                        'source_name' => $clean['source_name'],
                    ];
                }

                if ($clean['package_value'] && $clean['unit_code']) {
                    $attributeCode = in_array($clean['unit_code'], ['ml', 'liter', 'l'], true) ? 'volume' : 'weight';
                    $attributes[$attributeCode] = [
                        'code' => $attributeCode,
                        'name_ar' => $attributeCode === 'volume' ? 'الحجم' : 'الوزن',
                        'name_en' => $attributeCode === 'volume' ? 'Volume' : 'Weight',
                        'data_type' => 'number',
                        'unit_code' => $clean['unit_code'],
                        'is_filterable' => 1,
                        'is_variant_axis' => 0,
                        'is_required' => 0,
                        'sort_order' => 10,
                    ];

                    $attributeValues[] = [
                        'product_bim_code' => $clean['bim_code'],
                        'attribute_code' => $attributeCode,
                        'value_text_ar' => null,
                        'value_text_en' => null,
                        'value_number' => $clean['package_value'],
                        'value_bool' => null,
                        'value_json' => null,
                        'unit_code' => $clean['unit_code'],
                        'sort_order' => 10,
                    ];
                }

                $cleanProducts[] = $this->exportProductRow($clean);
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'file' => 'products_raw.csv',
                    'line' => $index + 2,
                    'message' => $e->getMessage(),
                    'row' => $row,
                ];
            }
        }

        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'units.csv', array_values($units));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'categories.csv', array_values($categories));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'category_children.csv', array_values($children));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'brands.csv', array_values($brands));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'manufacturers.csv', array_values($manufacturers));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'attributes.csv', array_values($attributes));
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'products.csv', $cleanProducts);
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'product_images.csv', $images);
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'product_barcodes.csv', $barcodes);
        $this->writeCsv($exportPath . DIRECTORY_SEPARATOR . 'product_attribute_values.csv', $attributeValues);

        $this->stats = [
            'raw_products' => count($products),
            'export_products' => count($cleanProducts),
            'brands' => count($brands),
            'manufacturers' => count($manufacturers),
            'categories' => count($categories),
            'children' => count($children),
            'units' => count($units),
            'attributes' => count($attributes),
            'images' => count($images),
            'barcodes' => count($barcodes),
            'attribute_values' => count($attributeValues),
        ];

        return [
            'section' => $section,
            'source_path' => $sourcePath,
            'export_path' => $exportPath,
            'stats' => $this->stats,
            'errors' => $this->errors,
        ];
    }

    protected function cleanProductRow(array $row, int $line): array
    {
        $bimCode = $this->value($row, 'bim_code');
        $nameAr = $this->value($row, 'name_ar');

        if (! $bimCode) {
            throw new RuntimeException('bim_code is required.');
        }

        if (! $nameAr) {
            throw new RuntimeException('name_ar is required.');
        }

        return [
            'bim_code' => $bimCode,
            'product_category_slug' => $this->value($row, 'product_category_slug', 'supermarket'),
            'product_category_name_ar' => $this->value($row, 'product_category_name_ar', 'سوبر ماركت'),
            'product_category_name_en' => $this->value($row, 'product_category_name_en', 'Supermarket'),
            'product_category_child_slug' => $this->value($row, 'product_category_child_slug', 'general'),
            'product_category_child_name_ar' => $this->value($row, 'product_category_child_name_ar', 'عام'),
            'product_category_child_name_en' => $this->value($row, 'product_category_child_name_en', 'General'),
            'brand_slug' => $this->value($row, 'brand_slug'),
            'brand_name_ar' => $this->value($row, 'brand_name_ar'),
            'brand_name_en' => $this->value($row, 'brand_name_en'),
            'brand_country_code' => $this->value($row, 'brand_country_code', 'EG'),
            'manufacturer_slug' => $this->value($row, 'manufacturer_slug') ?: $this->value($row, 'brand_slug'),
            'manufacturer_name_ar' => $this->value($row, 'manufacturer_name_ar') ?: $this->value($row, 'brand_name_ar'),
            'manufacturer_name_en' => $this->value($row, 'manufacturer_name_en') ?: $this->value($row, 'brand_name_en'),
            'manufacturer_country_code' => $this->value($row, 'manufacturer_country_code', 'EG'),
            'product_type' => $this->value($row, 'product_type', 'simple'),
            'name_ar' => $nameAr,
            'name_en' => $this->value($row, 'name_en'),
            'short_name_ar' => $this->value($row, 'short_name_ar'),
            'short_name_en' => $this->value($row, 'short_name_en'),
            'model' => $this->value($row, 'model'),
            'sku' => $this->value($row, 'sku'),
            'default_barcode' => $this->value($row, 'default_barcode'),
            'barcode_verified' => $this->truthy($this->value($row, 'barcode_verified')),
            'description_ar' => $this->value($row, 'description_ar'),
            'description_en' => $this->value($row, 'description_en'),
            'main_image' => $this->value($row, 'main_image'),
            'unit_code' => $this->value($row, 'unit_code'),
            'unit_name_ar' => $this->value($row, 'unit_name_ar'),
            'unit_name_en' => $this->value($row, 'unit_name_en'),
            'unit_type' => $this->value($row, 'unit_type'),
            'package_value' => $this->number($this->value($row, 'package_value')),
            'package_label_ar' => $this->value($row, 'package_label_ar'),
            'package_label_en' => $this->value($row, 'package_label_en'),
            'country_code' => $this->value($row, 'country_code', 'EG'),
            'market_scope' => $this->value($row, 'market_scope', 'egypt'),
            'is_verified_egypt' => $this->truthy($this->value($row, 'is_verified_egypt')) ? 1 : 0,
            'verification_source' => $this->value($row, 'verification_source', 'collector'),
            'search_keywords' => $this->value($row, 'search_keywords'),
            'specs_json' => $this->validJson($this->value($row, 'specs_json')),
            'source_name' => $this->value($row, 'source_name'),
            'source_url' => $this->value($row, 'source_url'),
            'image_license_note' => $this->value($row, 'image_license_note'),
            'is_active' => 1,
            'approval_status' => $this->value($row, 'approval_status', 'approved'),
            'sort_order' => (int) ($this->value($row, 'sort_order') ?: 10),
        ];
    }

    protected function exportProductRow(array $clean): array
    {
        return [
            'bim_code' => $clean['bim_code'],
            'product_category_slug' => $clean['product_category_slug'],
            'product_category_child_slug' => $clean['product_category_child_slug'],
            'brand_slug' => $clean['brand_slug'],
            'manufacturer_slug' => $clean['manufacturer_slug'],
            'product_type' => $clean['product_type'],
            'name_ar' => $clean['name_ar'],
            'name_en' => $clean['name_en'],
            'short_name_ar' => $clean['short_name_ar'],
            'short_name_en' => $clean['short_name_en'],
            'model' => $clean['model'],
            'sku' => $clean['sku'],
            'default_barcode' => $clean['default_barcode'],
            'description_ar' => $clean['description_ar'],
            'description_en' => $clean['description_en'],
            'main_image' => $clean['main_image'],
            'image_alt_ar' => $clean['name_ar'],
            'image_alt_en' => $clean['name_en'],
            'unit_code' => $clean['unit_code'],
            'package_value' => $clean['package_value'],
            'package_label_ar' => $clean['package_label_ar'],
            'package_label_en' => $clean['package_label_en'],
            'country_code' => $clean['country_code'],
            'market_scope' => $clean['market_scope'],
            'is_verified_egypt' => $clean['is_verified_egypt'],
            'verification_source' => $clean['verification_source'],
            'search_keywords' => $clean['search_keywords'],
            'specs_json' => $clean['specs_json'],
            'is_active' => $clean['is_active'],
            'approval_status' => $clean['approval_status'],
            'sort_order' => $clean['sort_order'],
        ];
    }

    protected function readCsvIfExists(string $file): array
    {
        if (! File::exists($file)) {
            return [];
        }

        $handle = fopen($file, 'rb');
        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($h) => trim((string) $h), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $data = array_slice(array_pad($data, count($header), null), 0, count($header));
            $rows[] = array_combine($header, $data) ?: [];
        }

        fclose($handle);
        return $rows;
    }

    protected function writeCsv(string $file, array $rows): void
    {
        File::ensureDirectoryExists(dirname($file));
        $handle = fopen($file, 'wb');

        if (! $handle) {
            throw new RuntimeException("Cannot write CSV file: {$file}");
        }

        if (empty($rows)) {
            fclose($handle);
            return;
        }

        fputcsv($handle, array_keys(reset($rows)));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    protected function value(array $row, string $key, mixed $default = null): mixed
    {
        $value = $row[$key] ?? $default;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? $default : $value;
    }

    protected function truthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    protected function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function validJson(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $value : null;
    }

    protected function barcodeType(string $barcode): string
    {
        return strlen($barcode) === 13 ? 'ean13' : 'custom';
    }
}
