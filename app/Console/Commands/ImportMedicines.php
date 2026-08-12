<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Load a drug list into the shared dictionary a doctor types against.
 *
 * The dictionary was built to grow by itself — every drug a doctor writes is
 * remembered — which is correct but leaves the FIRST doctor typing into an
 * empty box. This fills it from a real register (an EDA export, a supplier's
 * catalogue, a hospital's formulary) instead of from anyone's memory.
 *
 * **Nothing here invents a drug.** A wrong name or a wrong strength in a list a
 * doctor picks from is not a cosmetic bug, so the only entries that reach the
 * table are the ones in the file the operator hands it.
 *
 * CSV or JSON. A CSV needs a `name` column; `strength` is optional. Column
 * order does not matter and the header is matched case-insensitively, because
 * every register exports a different shape:
 *
 *     php artisan medicines:import storage/app/eda.csv
 *     php artisan medicines:import list.json --dry-run
 *
 * Re-running is safe: (name, strength) is the identity, so a second pass over
 * the same file updates nothing and creates nothing.
 */
class ImportMedicines extends Command
{
    protected $signature = 'medicines:import
        {file : Path to a .csv or .json file}
        {--dry-run : Report what would be imported without writing}
        {--chunk=500 : Rows per insert}
        {--source= : Label recorded on each row (defaults to the file name)}';

    protected $description = 'Import medicines into the shared prescription dictionary';

    /**
     * Header names a register might use for the drug itself.
     *
     * Long because every source spells it differently and the cost of a miss is
     * the whole import: the first real file said «Trade Name» and the command
     * reported "no name column" and wrote nothing. Matched after `key()`
     * normalises case and turns spaces and hyphens into underscores.
     */
    private const NAME_KEYS = [
        'name', 'drug_name', 'medicine', 'medicine_name', 'drug', 'trade', 'trade_name',
        'brand', 'brand_name', 'generic_name', 'product', 'product_name', 'item_name',
        // The EDA-shaped export: the registered commercial name, which is the
        // one a doctor actually writes — «PANADOL EXTRA 20 F.C.TABS.» — pack and
        // strength included, because that is how it appears on the box.
        'commercial_name_en', 'commercial_name', 'trade_name_en',
        'اسم', 'الاسم', 'الدواء', 'اسم_الدواء', 'الاسم_التجاري', 'الصنف',
    ];

    /** …and for its strength. */
    private const STRENGTH_KEYS = [
        'strength', 'dose', 'dosage', 'concentration', 'unit_strength', 'potency',
        'التركيز', 'الجرعة', 'التركيب',
    ];

    /**
     * The columns beyond the name, keyed by the DB column they fill.
     *
     * The first import read one column of seven and threw the rest away, which
     * left a doctor unable to search by active ingredient — the way medicine is
     * actually taught. Absent columns simply stay null.
     *
     * `name_ar` is a SEARCH ALIAS. Registers ship a transliterated Arabic name
     * that is not the registered brand, so it may be matched on and must never
     * be shown as the drug's name.
     */
    private const EXTRA_KEYS = [
        'scientific_name' => ['scientific_name', 'scientific', 'active_ingredient', 'ingredient',
            'generic', 'composition', 'المادة_الفعالة', 'المادة_الفعّالة', 'التركيب_الدوائي'],
        'name_ar' => ['commercial_name_ar', 'name_ar', 'arabic_name', 'الاسم_بالعربية', 'الاسم_العربي'],
        'manufacturer' => ['manufacturer', 'company', 'producer', 'الشركة', 'المصنع'],
        'drug_class' => ['drug_class', 'class', 'category', 'التصنيف', 'المجموعة'],
        'route' => ['route', 'form', 'dosage_form', 'الشكل', 'طريقة_الاستخدام'],
        'price_egp' => ['price_egp', 'price', 'egp', 'السعر'],
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $rows = str_ends_with(strtolower($path), '.json')
            ? $this->readJson($path)
            : $this->readCsv($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('The file holds no usable rows — is there a «name» column?');

            return self::FAILURE;
        }

        // Deduplicate within the file itself. A register that lists the same
        // drug once per pack size would otherwise spend the whole import
        // colliding with itself.
        $unique = [];
        foreach ($rows as $row) {
            $unique[mb_strtolower($row['name'] . '|' . (string) $row['strength'])] = $row;
        }
        $rows = array_values($unique);

        $existing = DB::table('medicines')->count();
        $this->line("File: " . count($rows) . " unique entries · dictionary holds {$existing}");

        if ($this->option('dry-run')) {
            // Which extra columns were actually FOUND, so an operator sees the
            // ingredient axis land (or notices it silently did not).
            $found = [];
            foreach (Medicine::ENRICHABLE as $column) {
                $filled = count(array_filter($rows, fn ($r) => ($r[$column] ?? null) !== null));

                if ($filled > 0) {
                    $found[] = $column . ' ' . round(100 * $filled / count($rows)) . '%';
                }
            }

            $this->line('Columns read: name' . ($found ? ', ' . implode(', ', $found) : ' only'));
            $this->newLine();

            foreach (array_slice($rows, 0, 8) as $row) {
                $this->line('  ' . $row['name'] . ($row['strength'] ? ' — ' . $row['strength'] : ''));

                if (! empty($row['scientific_name'])) {
                    $this->line('      ' . $row['scientific_name']
                        . (empty($row['manufacturer']) ? '' : '  ·  ' . $row['manufacturer'])
                        . (isset($row['price_egp']) ? '  ·  ' . $row['price_egp'] . ' EGP' : ''));
                }
            }

            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $created = 0;
        $enriched = 0;
        $untouched = 0;
        $captured = now()->toDateString();
        $source = $this->option('source') ?: basename($path);
        $bar = $this->output->createProgressBar(count($rows));

        foreach (array_chunk($rows, max((int) $this->option('chunk'), 1)) as $chunk) {
            // Row by row rather than one insertOrIgnore: the table's identity is
            // (name, strength) and NULL strength does not collide with NULL in a
            // unique index, so the check has to be explicit.
            foreach ($chunk as $row) {
                $extra = array_intersect_key($row, array_flip(Medicine::ENRICHABLE));

                if ($extra !== []) {
                    $extra['source'] = $source;

                    if (array_key_exists('price_egp', $extra)) {
                        // A price with no date is a claim nobody can check, and
                        // this register says its own prices change constantly.
                        $extra['price_captured_at'] = $captured;
                    }
                }

                $existing = Medicine::query()
                    ->where('name', $row['name'])
                    ->where('strength', $row['strength'])
                    ->first();

                if (! $existing) {
                    Medicine::query()->create($extra + [
                        'name' => $row['name'],
                        'strength' => $row['strength'],
                        'created_by' => null,
                        // Imported, not prescribed. Leaving it at zero keeps the
                        // typeahead's «most-used first» order honest: what
                        // doctors actually reach for still rises above the
                        // twenty-five thousand rows nobody has written yet.
                        'uses_count' => 0,
                    ]);
                    $created++;
                    $bar->advance();

                    continue;
                }

                // A second pass over a richer file fills what the first one had
                // no column for — which is exactly how the ingredient axis
                // reached 25,000 rows already imported by name alone. It fills
                // blanks and corrects stale facts, and touches neither the row's
                // identity nor `uses_count`: that is the record of what doctors
                // did, and no file may rewrite it.
                $changes = array_filter(
                    $extra,
                    fn ($value, $column) => $value !== null && (string) $existing->{$column} !== (string) $value,
                    ARRAY_FILTER_USE_BOTH
                );

                if ($changes !== []) {
                    $existing->forceFill($changes)->save();
                    $enriched++;
                } else {
                    $untouched++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Added {$created} · enriched {$enriched} · unchanged {$untouched}");

        return self::SUCCESS;
    }

    /** @return array<int,array{name:string,strength:?string}>|null */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return null;
        }

        // A UTF-8 BOM would glue itself to the first header and make «name»
        // unfindable — every Excel export from an Arabic Windows has one.
        $first = fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first);
        rewind($handle);
        fgets($handle);

        $header = array_map(
            fn ($h) => $this->key($h),
            str_getcsv(rtrim($first, "\r\n"))
        );

        $nameAt = $this->columnFor($header, self::NAME_KEYS);
        $strengthAt = $this->columnFor($header, self::STRENGTH_KEYS);

        $extraAt = [];
        foreach (self::EXTRA_KEYS as $column => $keys) {
            $at = $this->columnFor($header, $keys);

            // The name column must not be read twice: a register whose only
            // name header is «generic_name» would otherwise land in both, and
            // the ingredient axis would just echo the trade name.
            if ($at !== null && $at !== $nameAt) {
                $extraAt[$column] = $at;
            }
        }

        if ($nameAt === null) {
            $this->error('No name column. Looked for: ' . implode(', ', self::NAME_KEYS));
            fclose($handle);

            return null;
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $extra = [];
            foreach ($extraAt as $column => $at) {
                $extra[$column] = $line[$at] ?? null;
            }

            $row = $this->row(
                $line[$nameAt] ?? null,
                $strengthAt === null ? null : ($line[$strengthAt] ?? null),
                $extra,
            );

            if ($row) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /** @return array<int,array{name:string,strength:?string}>|null */
    private function readJson(string $path): ?array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            $this->error('The file is not valid JSON.');

            return null;
        }

        // Accept both a bare list and {"data": [...]}, since exports differ.
        $list = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        $rows = [];

        foreach ($list as $entry) {
            if (is_string($entry)) {
                $row = $this->row($entry, null);
            } elseif (is_array($entry)) {
                $extra = [];
                foreach (self::EXTRA_KEYS as $column => $keys) {
                    $extra[$column] = $this->pick($entry, $keys);
                }

                $row = $this->row(
                    $this->pick($entry, self::NAME_KEYS),
                    $this->pick($entry, self::STRENGTH_KEYS),
                    $extra,
                );
            } else {
                continue;
            }

            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * A header as a comparable key: «Trade Name», «trade-name» and «TRADE_NAME»
     * are one column. Every register exports a different spelling of the same
     * word, and matching them literally meant an EDA file with «Trade Name»
     * reported «no name column» and imported nothing.
     */
    private function key(mixed $raw): string
    {
        return preg_replace('/[\s\-]+/u', '_', mb_strtolower(trim((string) $raw))) ?? '';
    }

    /** @param  array<int,string>  $header */
    private function columnFor(array $header, array $keys): ?int
    {
        foreach ($keys as $key) {
            $at = array_search($key, $header, true);

            if ($at !== false) {
                return (int) $at;
            }
        }

        return null;
    }

    /** @param  array<string,mixed>  $entry */
    private function pick(array $entry, array $keys): ?string
    {
        foreach ($keys as $key) {
            foreach ($entry as $column => $value) {
                if ($this->key($column) === $key && is_scalar($value)) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,?string>  $extra
     * @return array<string,mixed>|null
     */
    private function row(?string $name, ?string $strength, array $extra = []): ?array
    {
        $name = trim((string) $name);

        if ($name === '' || mb_strlen($name) > 200) {
            return null;
        }

        $strength = trim((string) $strength);
        $strength = ($strength === '' || mb_strlen($strength) > 120) ? null : $strength;

        $row = ['name' => $name, 'strength' => $strength];

        foreach (self::EXTRA_KEYS as $column => $ignored) {
            $value = trim((string) ($extra[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            // A price is a number or it is nothing — a register with «N/A» in
            // the column must not write 0.00 and call it a price.
            if ($column === 'price_egp') {
                $clean = str_replace([',', ' '], '', $value);
                $row[$column] = is_numeric($clean) ? round((float) $clean, 2) : null;

                continue;
            }

            // The alias is a MATCHING key, so it is stored folded: hamza,
            // ة/ه, ى/ي and the latin punctuation the transliteration drags
            // along («أوجمينتين . ./») all go, and the search folds the typed
            // term the same way to meet it.
            if ($column === 'name_ar') {
                $folded = Medicine::arabicKey($value);
                $row[$column] = $folded === '' ? null : mb_substr($folded, 0, 300);

                continue;
            }

            $row[$column] = mb_substr($value, 0, $column === 'scientific_name' ? 500 : 191);
        }

        return $row;
    }
}
