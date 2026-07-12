<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema capture for the Product Catalog V3 tables.
 *
 * These 14 tables were originally created by an external SQL dump and had no
 * in-repo migration, so a fresh install could never recreate them. This
 * migration is the faithful capture of the live schema (SHOW CREATE TABLE,
 * 2026-07-12) including the curation columns/indexes that
 * 2026_07_03_000000_add_curation_fields_to_catalog_products.php adds — that
 * migration is fully guarded and no-ops when this one has run first.
 *
 * Every create is guarded with Schema::hasTable so the migration is a pure
 * no-op on databases that already carry the dump. Dated 2026_07_02 on purpose:
 * it must sort before the 07_03 curation migration and the 07_08
 * business_catalog_listings FK that references catalog_products.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_units')) {
            Schema::create('catalog_units', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name_ar', 80);
                $table->string('name_en', 80)->nullable();
                $table->enum('unit_type', ['weight', 'volume', 'length', 'count', 'storage', 'other'])->default('other');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['unit_type', 'is_active'], 'catalog_units_type_active_idx');
            });
        }

        if (! Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name_ar', 180);
                $table->string('name_en', 180)->nullable();
                $table->string('slug', 190)->unique();
                $table->string('image')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['is_active', 'sort_order'], 'product_categories_active_sort_idx');
                $table->index('deleted_at', 'product_categories_deleted_idx');
            });
        }

        if (! Schema::hasTable('product_category_children')) {
            Schema::create('product_category_children', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_category_id');
                $table->string('name_ar', 180);
                $table->string('name_en', 180)->nullable();
                $table->string('slug', 190)->unique();
                $table->string('image')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
                // Backs the composite FK on catalog_products (cp_child_matches_parent_fk).
                $table->unique(['product_category_id', 'id'], 'pcc_category_id_id_unique');
                $table->index(['product_category_id', 'is_active', 'sort_order'], 'pcc_category_active_sort_idx');
                $table->index('deleted_at', 'pcc_deleted_idx');
                $table->index(['product_category_id', 'id'], 'pcc_category_id_id_idx');
                $table->foreign('product_category_id', 'pcc_category_fk')
                    ->references('id')->on('product_categories')->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_brands')) {
            Schema::create('catalog_brands', function (Blueprint $table) {
                $table->id();
                $table->string('name_ar', 180)->nullable();
                $table->string('name_en', 180);
                $table->string('slug', 190)->unique();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->char('country_code', 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_verified')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('is_active', 'catalog_brands_active_idx');
                $table->index('deleted_at', 'catalog_brands_deleted_idx');
            });
        }

        if (! Schema::hasTable('catalog_manufacturers')) {
            Schema::create('catalog_manufacturers', function (Blueprint $table) {
                $table->id();
                $table->string('name_ar', 180)->nullable();
                $table->string('name_en', 180);
                $table->string('slug', 190)->unique();
                $table->char('country_code', 2)->nullable();
                $table->string('website')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('is_active', 'catalog_manufacturers_active_idx');
                $table->index('deleted_at', 'catalog_manufacturers_deleted_idx');
            });
        }

        if (! Schema::hasTable('catalog_attributes')) {
            Schema::create('catalog_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name_ar', 120);
                $table->string('name_en', 120)->nullable();
                $table->enum('data_type', ['text', 'number', 'boolean', 'select', 'multi_select', 'date'])->default('text');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->boolean('is_filterable')->default(true);
                $table->boolean('is_variant_axis')->default(false);
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_filterable', 'is_variant_axis'], 'ca_filter_variant_idx');
                $table->foreign('unit_id', 'ca_unit_fk')
                    ->references('id')->on('catalog_units')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_attribute_options')) {
            Schema::create('catalog_attribute_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attribute_id');
                $table->string('value_ar', 160);
                $table->string('value_en', 160)->nullable();
                $table->string('slug', 190)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['attribute_id', 'slug'], 'cao_attribute_slug_unique');
                $table->index(['attribute_id', 'is_active', 'sort_order'], 'cao_attribute_active_idx');
                $table->foreign('attribute_id', 'cao_attribute_fk')
                    ->references('id')->on('catalog_attributes')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_import_batches')) {
            Schema::create('catalog_import_batches', function (Blueprint $table) {
                $table->id();
                $table->string('source_name', 120);
                $table->enum('source_type', ['manual', 'csv', 'api', 'open_data', 'admin'])->default('csv');
                $table->string('file_name')->nullable();
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('skipped_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('finished_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('status', 'cib_status_idx');
                $table->index(['source_name', 'source_type'], 'cib_source_idx');
                $table->foreign('created_by', 'cib_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('bim_code', 40)->unique();
                $table->unsignedBigInteger('product_category_id');
                $table->unsignedBigInteger('product_category_child_id');
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('manufacturer_id')->nullable();
                $table->enum('product_type', ['simple', 'variable'])->default('simple');
                $table->string('name_ar');
                $table->string('normalized_name_ar', 500)->nullable();
                $table->string('name_en')->nullable();
                $table->string('normalized_name_en', 500)->nullable();
                $table->string('short_name_ar', 180)->nullable();
                $table->string('short_name_en', 180)->nullable();
                $table->string('model', 160)->nullable();
                $table->string('sku', 120)->nullable()->unique();
                $table->string('default_barcode', 80)->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->string('main_image')->nullable();
                $table->string('image_alt_ar', 180)->nullable();
                $table->string('image_alt_en', 180)->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('package_value', 12, 3)->nullable();
                $table->string('package_label_ar', 80)->nullable();
                $table->string('package_label_en', 80)->nullable();
                $table->char('country_code', 2)->nullable();
                $table->enum('market_scope', ['egypt', 'arab', 'global'])->default('egypt');
                $table->boolean('is_verified_egypt')->default(false);
                $table->string('verification_source', 120)->nullable();
                $table->text('search_keywords')->nullable();
                $table->string('duplicate_group_key', 700)->nullable();
                $table->unsignedBigInteger('duplicate_master_id')->nullable();
                $table->enum('duplicate_status', ['unique', 'master', 'duplicate', 'review'])->nullable()->default('unique');
                $table->json('specs_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->enum('approval_status', ['draft', 'pending', 'approved', 'rejected'])->default('approved');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                // Curation columns; the guarded 2026_07_03 migration no-ops when present.
                $table->string('dedup_key', 191)->nullable()->index();
                $table->string('curation_status', 20)->nullable()->index();
                $table->integer('curation_score')->nullable();
                $table->timestamp('curated_at')->nullable();

                $table->index(['product_category_id', 'product_category_child_id', 'is_active'], 'cp_category_child_active_idx');
                $table->index(['brand_id', 'is_active'], 'cp_brand_active_idx');
                $table->index('manufacturer_id', 'cp_manufacturer_idx');
                $table->index('default_barcode', 'cp_barcode_idx');
                $table->index(['product_type', 'approval_status', 'is_active'], 'cp_type_status_idx');
                $table->index(['market_scope', 'is_verified_egypt'], 'cp_market_verified_idx');
                $table->index('deleted_at', 'cp_deleted_idx');
                // Admin-filter indexes owned by the guarded 2026_07_03 migration.
                $table->index('product_category_child_id', 'catalog_products_child_idx');
                $table->index('brand_id', 'catalog_products_brand_idx');
                $table->index('is_active', 'catalog_products_active_idx');
                $table->index('approval_status', 'catalog_products_approval_idx');
                $table->index('duplicate_status', 'catalog_products_duplicate_idx');
                $table->index('default_barcode', 'catalog_products_barcode_idx');
                $table->fullText(['name_ar', 'name_en', 'model', 'search_keywords'], 'cp_search_fulltext');

                $table->foreign('brand_id', 'cp_brand_fk')
                    ->references('id')->on('catalog_brands')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('product_category_child_id', 'cp_category_child_fk')
                    ->references('id')->on('product_category_children')->cascadeOnUpdate();
                $table->foreign('product_category_id', 'cp_category_fk')
                    ->references('id')->on('product_categories')->cascadeOnUpdate();
                // A product's child must belong to its parent category.
                $table->foreign(['product_category_id', 'product_category_child_id'], 'cp_child_matches_parent_fk')
                    ->references(['product_category_id', 'id'])->on('product_category_children')->cascadeOnUpdate();
                $table->foreign('manufacturer_id', 'cp_manufacturer_fk')
                    ->references('id')->on('catalog_manufacturers')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('unit_id', 'cp_unit_fk')
                    ->references('id')->on('catalog_units')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_product_images')) {
            Schema::create('catalog_product_images', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('image_path');
                $table->enum('image_type', ['main', 'gallery', 'thumbnail', 'source'])->default('gallery');
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->string('alt_ar', 180)->nullable();
                $table->string('alt_en', 180)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('source_name', 120)->nullable();
                $table->string('source_url', 500)->nullable();
                $table->string('license_note')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'is_primary', 'sort_order'], 'cpi_product_primary_idx');
                $table->index('image_type', 'cpi_type_idx');
                $table->foreign('product_id', 'cpi_product_fk')
                    ->references('id')->on('catalog_products')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_product_barcodes')) {
            Schema::create('catalog_product_barcodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('barcode', 80)->unique('cpb_barcode_unique');
                $table->enum('barcode_type', ['EAN13', 'EAN8', 'UPC', 'ISBN', 'QR', 'OTHER'])->default('EAN13');
                $table->string('package_note_ar', 120)->nullable();
                $table->string('package_note_en', 120)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('source_name', 120)->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamps();
                $table->index(['product_id', 'is_primary'], 'cpb_product_primary_idx');
                $table->foreign('product_id', 'cpb_product_fk')
                    ->references('id')->on('catalog_products')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_product_attribute_values')) {
            Schema::create('catalog_product_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('attribute_id');
                $table->unsignedBigInteger('option_id')->nullable();
                $table->string('value_text_ar')->nullable();
                $table->string('value_text_en')->nullable();
                $table->decimal('value_number', 14, 4)->nullable();
                $table->boolean('value_bool')->nullable();
                $table->json('value_json')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('value_boolean')->nullable();
                $table->date('value_date')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->timestamps();
                $table->unique(['product_id', 'attribute_id', 'option_id'], 'cpav_product_attribute_option_unique');
                $table->index(['attribute_id', 'option_id'], 'cpav_attribute_filter_idx');
                $table->index(['attribute_id', 'value_number'], 'cpav_number_idx');
                $table->foreign('attribute_id', 'cpav_attribute_fk')
                    ->references('id')->on('catalog_attributes')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('option_id', 'cpav_option_fk')
                    ->references('id')->on('catalog_attribute_options')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('product_id', 'cpav_product_fk')
                    ->references('id')->on('catalog_products')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('unit_id', 'cpav_unit_fk')
                    ->references('id')->on('catalog_units')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_product_variants')) {
            Schema::create('catalog_product_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('bim_variant_code', 50)->unique('cpv_bim_variant_code_unique');
                $table->string('sku', 120)->nullable()->unique('cpv_sku_unique');
                $table->string('barcode', 80)->nullable()->unique('cpv_barcode_unique');
                $table->string('name_suffix_ar', 160)->nullable();
                $table->string('name_suffix_en', 160)->nullable();
                $table->string('main_image')->nullable();
                $table->json('specs_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['product_id', 'is_active', 'sort_order'], 'cpv_product_active_idx');
                $table->index('deleted_at', 'cpv_deleted_idx');
                $table->foreign('product_id', 'cpv_product_fk')
                    ->references('id')->on('catalog_products')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('catalog_product_variant_attribute_values')) {
            Schema::create('catalog_product_variant_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('variant_id');
                $table->unsignedBigInteger('attribute_id');
                $table->unsignedBigInteger('option_id')->nullable();
                $table->string('value_text_ar')->nullable();
                $table->string('value_text_en')->nullable();
                $table->decimal('value_number', 14, 4)->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->timestamps();
                $table->unique(['variant_id', 'attribute_id', 'option_id'], 'cpvav_variant_attribute_option_unique');
                $table->index(['attribute_id', 'option_id'], 'cpvav_attribute_filter_idx');
                $table->foreign('attribute_id', 'cpvav_attribute_fk')
                    ->references('id')->on('catalog_attributes')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('option_id', 'cpvav_option_fk')
                    ->references('id')->on('catalog_attribute_options')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('unit_id', 'cpvav_unit_fk')
                    ->references('id')->on('catalog_units')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('variant_id', 'cpvav_variant_fk')
                    ->references('id')->on('catalog_product_variants')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Reverse creation order (children before parents).
        Schema::dropIfExists('catalog_product_variant_attribute_values');
        Schema::dropIfExists('catalog_product_variants');
        Schema::dropIfExists('catalog_product_attribute_values');
        Schema::dropIfExists('catalog_product_barcodes');
        Schema::dropIfExists('catalog_product_images');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_import_batches');
        Schema::dropIfExists('catalog_attribute_options');
        Schema::dropIfExists('catalog_attributes');
        Schema::dropIfExists('catalog_manufacturers');
        Schema::dropIfExists('catalog_brands');
        Schema::dropIfExists('product_category_children');
        Schema::dropIfExists('catalog_units');
        Schema::dropIfExists('product_categories');
    }
};
