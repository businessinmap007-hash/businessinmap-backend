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
        {--chunk=500 : Rows per insert}';

    protected $description = 'Import medicines into the shared prescription dictionary';

    /** Header names a register might use for the drug itself. */
    private const NAME_KEYS = ['name', 'medicine', 'drug', 'trade_name', 'product', 'اسم', 'الاسم', 'الدواء'];

    /** …and for its strength. */
    private const STRENGTH_KEYS = ['strength', 'dose', 'dosage', 'concentration', 'التركيز', 'الجرعة'];

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
            foreach (array_slice($rows, 0, 10) as $row) {
                $this->line('  ' . $row['name'] . ($row['strength'] ? ' — ' . $row['strength'] : ''));
            }

            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $created = 0;
        $bar = $this->output->createProgressBar(count($rows));

        foreach (array_chunk($rows, max((int) $this->option('chunk'), 1)) as $chunk) {
            // firstOrCreate per row rather than one insertOrIgnore: the table's
            // identity is (name, strength) and NULL strength does not collide
            // with NULL in a unique index, so the check has to be explicit.
            foreach ($chunk as $row) {
                $found = Medicine::query()
                    ->where('name', $row['name'])
                    ->where('strength', $row['strength'])
                    ->exists();

                if (! $found) {
                    Medicine::query()->create([
                        'name' => $row['name'],
                        'strength' => $row['strength'],
                        'created_by' => null,
                        // Imported, not prescribed. Leaving it at zero keeps the
                        // typeahead's «most-used first» order honest: what
                        // doctors actually reach for still rises above the
                        // twelve thousand rows nobody has written yet.
                        'uses_count' => 0,
                    ]);
                    $created++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Added {$created} · already present " . (count($rows) - $created));

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

        if ($nameAt === null) {
            $this->error('No name column. Looked for: ' . implode(', ', self::NAME_KEYS));
            fclose($handle);

            return null;
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $row = $this->row($line[$nameAt] ?? null, $strengthAt === null ? null : ($line[$strengthAt] ?? null));

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
                $row = $this->row(
                    $this->pick($entry, self::NAME_KEYS),
                    $this->pick($entry, self::STRENGTH_KEYS),
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

    /** @return array{name:string,strength:?string}|null */
    private function row(?string $name, ?string $strength): ?array
    {
        $name = trim((string) $name);

        if ($name === '' || mb_strlen($name) > 200) {
            return null;
        }

        $strength = trim((string) $strength);
        $strength = ($strength === '' || mb_strlen($strength) > 120) ? null : $strength;

        return ['name' => $name, 'strength' => $strength];
    }
}
