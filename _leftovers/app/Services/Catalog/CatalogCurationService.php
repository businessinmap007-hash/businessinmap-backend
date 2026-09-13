<?php

namespace App\Services\Catalog;

use App\Helpers\ArabicNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplicates catalog_products and curates an Egypt-focused shortlist.
 *
 * Pipeline:
 *   1. Load a lightweight projection of every product.
 *   2. Group products into duplicate clusters (exact barcode first, then a
 *      normalized name+brand+package+unit fingerprint) via union-find.
 *   3. Pick a "master" per cluster (highest score) and flag the rest as
 *      duplicates.
 *   4. Rank the masters — Egypt-verified first, then by completeness score —
 *      and keep the top N. Everything else is archived (never hard-deleted).
 *
 * Nothing is deleted here. In apply mode products are only flagged
 * (curation_status / is_active / duplicate_status). Hard deletion is a
 * separate, deliberate step (bim:catalog-purge-archived).
 */
class CatalogCurationService
{
    /** Weight that guarantees Egypt-verified products always outrank the rest. */
    private const EGYPT_VERIFIED_WEIGHT = 1000;

    /**
     * @return array{
     *   total:int, clusters:int, duplicates:int, masters:int,
     *   kept:int, archived_low_score:int, archived_total:int,
     *   applied:bool, limit:int, report_path:?string
     * }
     */
    public function curate(int $limit = 10000, bool $apply = false, bool $writeReport = true): array
    {
        $rows = $this->loadProducts();
        $total = count($rows);

        if ($total === 0) {
            return $this->emptyResult($limit, $apply);
        }

        // ---- union-find over duplicate signals -----------------------------
        $parent = [];
        foreach ($rows as $id => $row) {
            $parent[$id] = $id;
        }

        $barcodeSeen = [];
        $fingerprintSeen = [];

        foreach ($rows as $id => $row) {
            if ($row['barcode'] !== '') {
                $key = 'b:' . $row['barcode'];
                if (isset($barcodeSeen[$key])) {
                    $this->union($parent, $barcodeSeen[$key], $id);
                } else {
                    $barcodeSeen[$key] = $id;
                }
            }

            if ($row['fingerprint'] !== '') {
                $key = 'f:' . $row['fingerprint'];
                if (isset($fingerprintSeen[$key])) {
                    $this->union($parent, $fingerprintSeen[$key], $id);
                } else {
                    $fingerprintSeen[$key] = $id;
                }
            }
        }

        // ---- collect clusters ---------------------------------------------
        $clusters = [];
        foreach ($rows as $id => $row) {
            $root = $this->find($parent, $id);
            $clusters[$root][] = $id;
        }

        // ---- pick a master per cluster ------------------------------------
        $masters = [];       // masterId => clusterMemberIds[]
        $masterOfDuplicate = []; // duplicateId => masterId
        $duplicates = 0;

        foreach ($clusters as $memberIds) {
            $masterId = $this->pickMaster($rows, $memberIds);
            $masters[$masterId] = $memberIds;

            foreach ($memberIds as $memberId) {
                if ($memberId !== $masterId) {
                    $masterOfDuplicate[$memberId] = $masterId;
                    $duplicates++;
                }
            }
        }

        // ---- rank masters: Egypt-verified first, then score ---------------
        $ranked = array_keys($masters);
        usort($ranked, function ($a, $b) use ($rows) {
            if ($rows[$a]['score'] !== $rows[$b]['score']) {
                return $rows[$b]['score'] <=> $rows[$a]['score'];
            }
            return $a <=> $b; // stable-ish: lower id wins ties
        });

        $keptMasters = array_slice($ranked, 0, max(0, $limit));
        $keptSet = array_flip($keptMasters);

        $archivedLowScore = count($ranked) - count($keptMasters);

        // ---- build the per-product decision list --------------------------
        $decisions = []; // id => ['status'=>..., 'master'=>?, 'score'=>int, 'reason'=>string]

        foreach ($rows as $id => $row) {
            if (isset($masterOfDuplicate[$id])) {
                $decisions[$id] = [
                    'status' => 'archived',
                    'duplicate_of' => $masterOfDuplicate[$id],
                    'score' => $row['score'],
                    'reason' => 'duplicate',
                ];
                continue;
            }

            // this id is a master
            if (isset($keptSet[$id])) {
                $decisions[$id] = [
                    'status' => 'kept',
                    'duplicate_of' => null,
                    'score' => $row['score'],
                    'reason' => $row['is_verified_egypt'] ? 'kept_verified_egypt' : 'kept_by_score',
                ];
            } else {
                $decisions[$id] = [
                    'status' => 'archived',
                    'duplicate_of' => null,
                    'score' => $row['score'],
                    'reason' => 'over_limit',
                ];
            }
        }

        $kept = count($keptMasters);
        $archivedTotal = $total - $kept;

        $reportPath = null;
        if ($writeReport) {
            $reportPath = $this->writeReport($rows, $decisions);
        }

        if ($apply) {
            $this->apply($rows, $decisions);
        }

        return [
            'total' => $total,
            'clusters' => count($clusters),
            'duplicates' => $duplicates,
            'masters' => count($masters),
            'kept' => $kept,
            'archived_low_score' => $archivedLowScore,
            'archived_total' => $archivedTotal,
            'applied' => $apply,
            'limit' => $limit,
            'report_path' => $reportPath,
        ];
    }

    /**
     * Load a lightweight projection of every product, keyed by id, with the
     * derived fingerprint / barcode / score already computed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadProducts(): array
    {
        $hasVerified = Schema::hasColumn('catalog_products', 'is_verified_egypt');
        $hasMarket = Schema::hasColumn('catalog_products', 'market_scope');
        $hasCountry = Schema::hasColumn('catalog_products', 'country_code');
        $hasBarcode = Schema::hasColumn('catalog_products', 'default_barcode');

        $columns = ['id', 'name_ar', 'name_en', 'brand_id', 'unit_id', 'package_value', 'main_image', 'approval_status', 'product_category_child_id'];
        if ($hasVerified) $columns[] = 'is_verified_egypt';
        if ($hasMarket) $columns[] = 'market_scope';
        if ($hasCountry) $columns[] = 'country_code';
        if ($hasBarcode) $columns[] = 'default_barcode';

        $rows = [];

        DB::table('catalog_products')
            ->select($columns)
            ->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$rows, $hasVerified, $hasMarket, $hasCountry, $hasBarcode) {
                foreach ($chunk as $r) {
                    $id = (int) $r->id;
                    $verified = $hasVerified ? (int) ($r->is_verified_egypt ?? 0) === 1 : false;
                    $market = $hasMarket ? (string) ($r->market_scope ?? '') : '';
                    $country = $hasCountry ? (string) ($r->country_code ?? '') : '';
                    $barcode = $hasBarcode ? trim((string) ($r->default_barcode ?? '')) : '';

                    $rows[$id] = [
                        'id' => $id,
                        'name_ar' => (string) ($r->name_ar ?? ''),
                        'name_en' => (string) ($r->name_en ?? ''),
                        'brand_id' => $r->brand_id !== null ? (int) $r->brand_id : 0,
                        'unit_id' => $r->unit_id !== null ? (int) $r->unit_id : 0,
                        'child_id' => $r->product_category_child_id !== null ? (int) $r->product_category_child_id : 0,
                        'main_image' => trim((string) ($r->main_image ?? '')),
                        'approval_status' => (string) ($r->approval_status ?? ''),
                        'is_verified_egypt' => $verified,
                        'market_scope' => $market,
                        'country_code' => $country,
                        'barcode' => $barcode,
                        'fingerprint' => $this->buildFingerprint($r),
                        'score' => 0, // filled below
                    ];

                    $rows[$id]['score'] = $this->scoreRow($rows[$id]);
                }
            });

        return $rows;
    }

    private function buildFingerprint(object $r): string
    {
        $name = ArabicNormalizer::fingerprint((string) ($r->name_ar ?? '') ?: (string) ($r->name_en ?? ''));
        if ($name === '') {
            return ''; // no usable name → never fingerprint-match (barcode may still group it)
        }

        $brand = $r->brand_id !== null ? (int) $r->brand_id : 0;
        $unit = $r->unit_id !== null ? (int) $r->unit_id : 0;

        $package = '';
        if (($r->package_value ?? null) !== null && $r->package_value !== '') {
            $package = rtrim(rtrim(number_format((float) $r->package_value, 3, '.', ''), '0'), '.');
        }

        return $name . '|' . $brand . '|' . $package . '|' . $unit;
    }

    /**
     * Completeness / relevance score. Egypt-verified dominates so verified
     * products always rank above unverified ones.
     */
    private function scoreRow(array $row): int
    {
        $score = 0;

        if ($row['is_verified_egypt']) {
            $score += self::EGYPT_VERIFIED_WEIGHT;
        }
        if ($row['market_scope'] === 'egypt') {
            $score += 200;
        }
        if ($row['country_code'] === 'EG') {
            $score += 100;
        }
        if ($row['barcode'] !== '') {
            $score += 50;
        }
        if ($row['main_image'] !== '') {
            $score += 40;
        }
        if ($row['approval_status'] === 'approved') {
            $score += 30;
        }
        if ($row['brand_id'] > 0) {
            $score += 30;
        }
        if (mb_strlen($row['name_ar']) >= 3) {
            $score += 20;
        }
        if ($row['name_en'] !== '') {
            $score += 10;
        }
        if ($row['unit_id'] > 0) {
            $score += 10;
        }

        return $score;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param int[] $memberIds
     */
    private function pickMaster(array $rows, array $memberIds): int
    {
        $bestId = $memberIds[0];
        foreach ($memberIds as $id) {
            if ($rows[$id]['score'] > $rows[$bestId]['score']
                || ($rows[$id]['score'] === $rows[$bestId]['score'] && $id < $bestId)) {
                $bestId = $id;
            }
        }
        return $bestId;
    }

    /**
     * Persist decisions in chunks. Only flags rows — never deletes.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $decisions
     */
    private function apply(array $rows, array $decisions): void
    {
        $now = now();
        $hasDuplicateStatus = Schema::hasColumn('catalog_products', 'duplicate_status');
        $hasDuplicateMaster = Schema::hasColumn('catalog_products', 'duplicate_master_id');

        // Bucket ids by identical update payload to minimise the number of
        // UPDATE statements over 50k rows.
        $keptIds = [];
        $archivedOverLimit = [];
        $duplicateByMaster = []; // masterId => [dupIds]

        foreach ($decisions as $id => $d) {
            if ($d['status'] === 'kept') {
                $keptIds[] = $id;
            } elseif ($d['reason'] === 'duplicate') {
                $duplicateByMaster[$d['duplicate_of']][] = $id;
            } else {
                $archivedOverLimit[] = $id;
            }
        }

        DB::transaction(function () use (
            $rows, $keptIds, $archivedOverLimit, $duplicateByMaster,
            $now, $hasDuplicateStatus, $hasDuplicateMaster
        ) {
            // 1) fingerprint + score for every row (kept and archived alike)
            foreach (array_chunk(array_keys($rows), 1000, true) as $idsChunk) {
                foreach ($idsChunk as $id) {
                    DB::table('catalog_products')->where('id', $id)->update([
                        'dedup_key' => $rows[$id]['fingerprint'] !== '' ? $rows[$id]['fingerprint'] : null,
                        'curation_score' => $rows[$id]['score'],
                        'curated_at' => $now,
                    ]);
                }
            }

            // 2) kept masters
            foreach (array_chunk($keptIds, 1000) as $chunk) {
                $update = ['curation_status' => 'kept', 'is_active' => 1];
                if ($hasDuplicateStatus) {
                    $update['duplicate_status'] = 'unique';
                }
                if ($hasDuplicateMaster) {
                    $update['duplicate_master_id'] = null;
                }
                DB::table('catalog_products')->whereIn('id', $chunk)->update($update);
            }

            // 3) archived over-limit masters
            foreach (array_chunk($archivedOverLimit, 1000) as $chunk) {
                DB::table('catalog_products')->whereIn('id', $chunk)->update([
                    'curation_status' => 'archived',
                    'is_active' => 0,
                ]);
            }

            // 4) duplicates, grouped by their master so we can set master id
            foreach ($duplicateByMaster as $masterId => $dupIds) {
                foreach (array_chunk($dupIds, 1000) as $chunk) {
                    $update = ['curation_status' => 'archived', 'is_active' => 0];
                    if ($hasDuplicateStatus) {
                        $update['duplicate_status'] = 'duplicate';
                    }
                    if ($hasDuplicateMaster) {
                        $update['duplicate_master_id'] = $masterId;
                    }
                    DB::table('catalog_products')->whereIn('id', $chunk)->update($update);
                }

                // ensure the master is flagged as a master when it owns duplicates
                if ($hasDuplicateStatus) {
                    DB::table('catalog_products')->where('id', $masterId)->update([
                        'duplicate_status' => 'master',
                    ]);
                }
            }
        });
    }

    /**
     * Write a CSV report of every decision under storage/app/catalog_curation.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $decisions
     */
    private function writeReport(array $rows, array $decisions): string
    {
        $dir = storage_path('app/catalog_curation');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir . '/curation_' . date('Ymd_His') . '.csv';
        $handle = fopen($path, 'w');

        // UTF-8 BOM so Excel renders Arabic correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['id', 'name_ar', 'name_en', 'brand_id', 'child_id', 'barcode', 'score', 'status', 'reason', 'duplicate_of']);

        foreach ($decisions as $id => $d) {
            $row = $rows[$id];
            fputcsv($handle, [
                $id,
                $row['name_ar'],
                $row['name_en'],
                $row['brand_id'] ?: '',
                $row['child_id'] ?: '',
                $row['barcode'],
                $d['score'],
                $d['status'],
                $d['reason'],
                $d['duplicate_of'] ?? '',
            ]);
        }

        fclose($handle);

        return $path;
    }

    private function union(array &$parent, int $a, int $b): void
    {
        $ra = $this->find($parent, $a);
        $rb = $this->find($parent, $b);
        if ($ra !== $rb) {
            // attach larger id under smaller to keep roots stable/low
            if ($ra < $rb) {
                $parent[$rb] = $ra;
            } else {
                $parent[$ra] = $rb;
            }
        }
    }

    private function find(array &$parent, int $x): int
    {
        while ($parent[$x] !== $x) {
            $parent[$x] = $parent[$parent[$x]]; // path halving
            $x = $parent[$x];
        }
        return $x;
    }

    private function emptyResult(int $limit, bool $apply): array
    {
        return [
            'total' => 0, 'clusters' => 0, 'duplicates' => 0, 'masters' => 0,
            'kept' => 0, 'archived_low_score' => 0, 'archived_total' => 0,
            'applied' => $apply, 'limit' => $limit, 'report_path' => null,
        ];
    }
}
