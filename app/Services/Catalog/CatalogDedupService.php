<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * Canonical de-duplication for the product catalog. A product's identity key is
 * its **barcode** first (GS1/EAN — the reliable global identity), falling back
 * to normalized name + brand + package. Everything hashes to a fixed-length
 * `dedup_key` so imports can match existing masters cheaply and re-imports never
 * recreate the duplication the catalog started with.
 */
class CatalogDedupService
{
    /** SQL for the name-based key parts, reused by the batch pass + backfill. */
    private const NAME_KEY_SQL = "CONCAT('nm:', LOWER(COALESCE(normalized_name_ar,'')), '|', COALESCE(brand_id,0), '|', COALESCE(package_label_ar,''), '|', COALESCE(package_value,''))";

    public function normalizeName(?string $name): string
    {
        $n = trim((string) $name);
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;

        return mb_strtolower($n);
    }

    /**
     * Raw identity string for a product row (before hashing).
     * $row keys: barcode|default_barcode, normalized_name_ar or name_ar, brand_id,
     * package_label_ar, package_value.
     */
    public function rawKey(array $row): string
    {
        $barcode = trim((string) ($row['barcode'] ?? $row['default_barcode'] ?? ''));

        if ($barcode !== '' && $barcode !== '0') {
            return 'bc:' . $barcode;
        }

        $name = $row['normalized_name_ar'] ?? $this->normalizeName($row['name_ar'] ?? '');

        return 'nm:' . $this->normalizeName($name)
            . '|' . (int) ($row['brand_id'] ?? 0)
            . '|' . trim((string) ($row['package_label_ar'] ?? ''))
            . '|' . trim((string) ($row['package_value'] ?? ''));
    }

    public function dedupKey(array $row): string
    {
        return md5($this->rawKey($row));
    }

    /** Active master id for a dedup key, or null. */
    public function findMasterId(string $dedupKey): ?int
    {
        $id = DB::table('catalog_products')
            ->whereNull('deleted_at')
            ->where('dedup_key', $dedupKey)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Backfill dedup_key on active masters that lack it (fast — masters only).
     * Barcode-first would need a per-row expr; masters currently have no
     * barcodes, so the name key is used and matches the initial dedup pass.
     */
    public function backfillDedupKeys(): int
    {
        return DB::update(
            "UPDATE catalog_products
             SET dedup_key = MD5(" . self::NAME_KEY_SQL . "), updated_at = NOW()
             WHERE deleted_at IS NULL AND (dedup_key IS NULL OR dedup_key = '')"
        );
    }

    /**
     * Full batch pass over active rows: group by the name key, keep the lowest
     * id as master, link + soft-delete the rest. Idempotent (already-deduped
     * masters form singleton groups). Returns counts; dry-run only measures.
     */
    public function runBatchDedup(bool $dryRun = true): array
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        $active = DB::table('catalog_products')->whereNull('deleted_at')->count();
        $groups = (int) DB::table(DB::raw("(SELECT " . self::NAME_KEY_SQL . " gk FROM catalog_products WHERE deleted_at IS NULL GROUP BY gk) x"))->count();
        $duplicates = $active - $groups;

        if ($dryRun || $duplicates <= 0) {
            $this->backfillDedupKeys();

            return ['active' => $active, 'masters' => $groups, 'duplicates' => $duplicates, 'applied' => false];
        }

        DB::statement(
            "UPDATE catalog_products t
             JOIN (SELECT " . self::NAME_KEY_SQL . " AS gk, MIN(id) AS master_id
                   FROM catalog_products WHERE deleted_at IS NULL GROUP BY gk) m
               ON m.gk = " . self::NAME_KEY_SQL . "
             SET t.duplicate_master_id = m.master_id,
                 t.duplicate_status = IF(t.id = m.master_id, 'unique', 'duplicate'),
                 t.deleted_at = IF(t.id = m.master_id, t.deleted_at, NOW()),
                 t.updated_at = NOW()
             WHERE t.deleted_at IS NULL"
        );

        $this->backfillDedupKeys();

        return [
            'active' => DB::table('catalog_products')->whereNull('deleted_at')->count(),
            'masters' => $groups,
            'duplicates' => $duplicates,
            'applied' => true,
        ];
    }
}
