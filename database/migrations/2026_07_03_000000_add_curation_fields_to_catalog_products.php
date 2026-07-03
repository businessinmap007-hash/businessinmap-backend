<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds curation / de-duplication support to catalog_products.
 *
 * - dedup_key      : normalized fingerprint used to group duplicates.
 * - curation_status: null (untouched) | 'kept' | 'archived'.
 * - curation_score : ranking score produced by bim:catalog-curate-egypt.
 *
 * Also adds indexes on the columns the admin catalog filter queries against,
 * so filtering/searching stays fast at 50k+ rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_products')) {
            return;
        }

        Schema::table('catalog_products', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_products', 'dedup_key')) {
                $table->string('dedup_key', 191)->nullable()->index();
            }
            if (! Schema::hasColumn('catalog_products', 'curation_status')) {
                $table->string('curation_status', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('catalog_products', 'curation_score')) {
                $table->integer('curation_score')->nullable();
            }
            if (! Schema::hasColumn('catalog_products', 'curated_at')) {
                $table->timestamp('curated_at')->nullable();
            }
        });

        // Indexes for the admin catalog filter columns. Guard each one so the
        // migration is safe to re-run and tolerant of pre-existing indexes.
        $this->addIndexIfMissing('catalog_products', 'catalog_products_child_idx', ['product_category_child_id']);
        $this->addIndexIfMissing('catalog_products', 'catalog_products_brand_idx', ['brand_id']);
        $this->addIndexIfMissing('catalog_products', 'catalog_products_active_idx', ['is_active']);
        $this->addIndexIfMissing('catalog_products', 'catalog_products_approval_idx', ['approval_status']);
        $this->addIndexIfMissing('catalog_products', 'catalog_products_duplicate_idx', ['duplicate_status']);
        $this->addIndexIfMissing('catalog_products', 'catalog_products_barcode_idx', ['default_barcode']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalog_products')) {
            return;
        }

        $this->dropIndexIfExists('catalog_products', 'catalog_products_child_idx');
        $this->dropIndexIfExists('catalog_products', 'catalog_products_brand_idx');
        $this->dropIndexIfExists('catalog_products', 'catalog_products_active_idx');
        $this->dropIndexIfExists('catalog_products', 'catalog_products_approval_idx');
        $this->dropIndexIfExists('catalog_products', 'catalog_products_duplicate_idx');
        $this->dropIndexIfExists('catalog_products', 'catalog_products_barcode_idx');

        Schema::table('catalog_products', function (Blueprint $table) {
            foreach (['dedup_key', 'curation_status', 'curation_score', 'curated_at'] as $column) {
                if (Schema::hasColumn('catalog_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return; // column doesn't exist on this install; skip silently.
            }
        }

        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics '
            . 'WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return $result && (int) $result->c > 0;
    }
};
