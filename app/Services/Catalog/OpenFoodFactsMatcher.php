<?php

namespace App\Services\Catalog;

/**
 * Decides which Open Food Facts row, if any, is the product already in the
 * catalog — so a barcode and a photograph can be attached to a row that was
 * written from knowledge alone.
 *
 * Three gates, in this order:
 *
 *  1. **The brand must agree.** Nothing is compared across brands.
 *  2. **The size must agree** when both sides state one. «جهينة لبن ١ لتر» and
 *     «جهينة لبن ١.٥ لتر» are two products by design — attaching one's barcode
 *     to the other is worse than attaching none.
 *  3. **The name must win clearly.** Not merely score well: beat the runner-up
 *     by a margin. Under one brand and one size sit «Mango Juice» and «Orange
 *     Juice», and a close call there means the source is not telling us which.
 *
 * Whatever fails a gate is not rejected — it is REPORTED. A human reading a
 * near-miss is the point; a machine silently picking the likelier of two
 * juices is the thing being avoided.
 */
class OpenFoodFactsMatcher
{
    /** Sizes agree within 2% — «330 ml» and «330ml» are one, «330» and «355» are not. */
    private const SIZE_TOLERANCE = 0.02;

    /** @var array<string,array<int,OpenFoodFactsRow>> brand key → rows */
    private array $byBrand = [];

    /** @param  iterable<OpenFoodFactsRow>  $rows */
    public function __construct(iterable $rows = [])
    {
        foreach ($rows as $row) {
            $this->add($row);
        }
    }

    public function add(OpenFoodFactsRow $row): void
    {
        $key = $row->brandKeyValue();

        if ($key === '' || $row->barcode === '') {
            return;
        }

        $this->byBrand[$key][] = $row;
    }

    public function brandCount(): int
    {
        return count($this->byBrand);
    }

    /**
     * `row` is the ACCEPTED match and is null unless every gate passed. `best`
     * is the closest candidate whatever the verdict — a review sheet that
     * shows only what was accepted tells a person nothing about what to
     * decide, and deciding is what the sheet is for.
     *
     * @param  array{value:float,type:string}|null  $size
     * @return array{
     *     row: OpenFoodFactsRow|null, best: OpenFoodFactsRow|null,
     *     score: float, runnerUp: float, reason: string, candidates: int
     * }
     */
    public function match(string $brand, string $name, ?array $size, float $minScore = 0.8, float $margin = 0.2): array
    {
        $none = fn (string $reason, int $candidates = 0) => [
            'row' => null, 'best' => null, 'score' => 0.0, 'runnerUp' => 0.0,
            'reason' => $reason, 'candidates' => $candidates,
        ];

        $key = OpenFoodFactsRow::brandKey($brand);

        if ($key === '') {
            return $none('no-brand');
        }

        $candidates = $this->candidatesFor($key);

        if ($candidates === []) {
            return $none('brand-not-in-source');
        }

        $wanted = OpenFoodFactsRow::tokens($name, $brand);

        if ($wanted === []) {
            return $none('no-name-tokens', count($candidates));
        }

        $scored = [];

        foreach ($candidates as $row) {
            if (! $this->sizesAgree($size, $row->size())) {
                continue;
            }

            $score = $this->similarity($wanted, $row->tokenSet());

            if ($score > 0) {
                $scored[] = ['row' => $row, 'score' => $score];
            }
        }

        if ($scored === []) {
            return $none($size ? 'no-size-and-name-agreement' : 'no-name-agreement', count($candidates));
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $best = $scored[0];
        // A second row scoring the SAME as the best is not a runner-up worth
        // separating if it is the same product under two barcodes; but we
        // cannot know that, so an equal second is exactly the tie to refuse.
        $runnerUp = isset($scored[1]) ? $scored[1]['score'] : 0.0;

        $verdict = fn (?OpenFoodFactsRow $accepted, string $reason) => [
            'row' => $accepted, 'best' => $best['row'],
            'score' => $best['score'], 'runnerUp' => $runnerUp,
            'reason' => $reason, 'candidates' => count($scored),
        ];

        if ($best['score'] < $minScore) {
            return $verdict(null, 'below-threshold');
        }

        if ($best['score'] - $runnerUp < $margin) {
            return $verdict(null, 'ambiguous');
        }

        return $verdict($best['row'], $size ? 'matched' : 'matched-without-size');
    }

    /**
     * Rows under this brand, plus rows whose brand key contains it or is
     * contained by it — «nestle» reaches «nestlepurelife», and the catalog's
     * own sub-brand slugs reach the plain parent. The size and name gates are
     * what keep that widening honest.
     *
     * @return array<int,OpenFoodFactsRow>
     */
    private function candidatesFor(string $key): array
    {
        $rows = $this->byBrand[$key] ?? [];

        foreach ($this->byBrand as $other => $group) {
            if ($other === $key || strlen($other) < 4 || strlen($key) < 4) {
                continue;
            }

            if (str_contains($other, $key) || str_contains($key, $other)) {
                $rows = array_merge($rows, $group);
            }
        }

        return $rows;
    }

    /**
     * @param  array{value:float,type:string}|null  $a
     * @param  array{value:float,type:string}|null  $b
     */
    private function sizesAgree(?array $a, ?array $b): bool
    {
        // One side silent is not a disagreement. 419 of the catalog's rows say
        // «عبوة» and nothing more; refusing those outright would throw away
        // every unsized product in the catalog.
        if ($a === null || $b === null) {
            return true;
        }

        if ($a['type'] !== $b['type']) {
            return false;
        }

        $max = max($a['value'], $b['value']);

        return $max > 0 && abs($a['value'] - $b['value']) / $max <= self::SIZE_TOLERANCE;
    }

    /**
     * Symmetric overlap — every word that is on ONE side only counts against
     * the match, whichever side it is on.
     *
     * This was containment at first («the catalog name is inside the source
     * name, so it is 1.0») and the first live run showed exactly why that is
     * wrong. A subset does not mean the same product described at two lengths;
     * it means the source row is **more specific** — a different variant:
     *
     *   «جهينة زبادي يوناني»   → «Greek MIXED BERRIES Yoghurt»
     *   «هاينز مايونيز»        → «LIGHT mayonnaise»
     *   «شيبسي ملح»            → «salt AND VINEGAR chips»
     *   «نيفيا رول-أون»        → «Nivea FOR MEN Cool Kick»
     *
     * Five of fourteen automatic matches were wrong that way. A wrong barcode
     * is worse than no barcode: a scanner then returns the wrong product
     * confidently. So the extra word has to cost, and at a 0.8 threshold what
     * passes is near-identity — «Nescafé gold» meeting «Nescafe Gold».
     *
     * Everything else is not lost, it is REPORTED. The review sheet is where a
     * person decides whether «Penne» and «Penne Pasta» are one thing.
     *
     * @param  array<int,string>  $a
     * @param  array<int,string>  $b
     */
    private function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $shared = count(array_intersect($a, $b));

        if ($shared === 0) {
            return 0.0;
        }

        return $shared / count(array_unique(array_merge($a, $b)));
    }
}
