<?php

namespace App\Services\Menu;

use App\Http\Controllers\Api\V2\BusinessMenuItemController;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use App\Support\SaleUnits;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader;

/**
 * «المنيو يحفظ فى ملف csv او اكسل ويضاف عليه ثم يتم استيراده للحساب — للمطاعم والسوبر ماركت وأى نشاط عنده
 * داتا بالفعل» — المالك، 2026-10-05.
 *
 * One sheet, one row per item, the same columns going out (export, template) and coming back (import).
 * The server reads CSV; an Excel file is turned into rows on the client (the app, the web panel) and sent as
 * rows — so the server needs no spreadsheet library.
 *
 * Every row is written through the SAME door the app uses ({@see BusinessMenuItemController::store()} and
 * `update()`): the vocabulary, the section grown from the line's group, the kilo default for food, the shop's
 * services on every item — an imported item is exactly an item added by hand. A dry run is the same run inside
 * a transaction that is rolled back: the preview says exactly what the import would do.
 */
final class MenuSheet
{
    /** key => [Arabic header, English header]. Order is the sheet's column order. */
    public const COLUMNS = [
        'id' => ['رقم الصنف', 'Item ID'],
        'line' => ['النوع', 'Type'],
        'section' => ['القسم', 'Section'],
        'barcode' => ['الباركود', 'Barcode'],
        'name_ar' => ['الاسم عربي', 'Name (Arabic)'],
        'name_en' => ['الاسم إنجليزي', 'Name (English)'],
        'price' => ['السعر', 'Price'],
        'supply_price' => ['سعر التكلفة', 'Cost price'],
        'unit' => ['الوحدة', 'Unit'],
        'quantity' => ['الكمية المتاحة', 'Quantity'],
        'brand' => ['الماركة', 'Brand'],
        'description_ar' => ['الوصف عربي', 'Description (Arabic)'],
        'description_en' => ['الوصف إنجليزي', 'Description (English)'],
        'variants' => ['المقاسات', 'Sizes'],
        'extras' => ['الإضافات', 'Extras'],
        'images' => ['الصور', 'Images'],
        'active' => ['نشط', 'Active'],
    ];

    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly MerchantOfferingVocabulary $vocabulary,
        private readonly MenuSheetDetails $details,
    ) {
    }

    /** @return list<array{key:string,label:string}> headers in the current locale */
    public function columns(): array
    {
        $en = app()->getLocale() === 'en';

        return collect(self::COLUMNS)->map(fn ($l, $key) => ['key' => $key, 'label' => $en ? $l[1] : $l[0]])->values()->all();
    }

    /**
     * What the merchant may write in «النوع» and «الوحدة», and the sections he already has.
     *
     * @return array{lines:list<array{name:string,group:string}>,units:list<string>,sections:list<string>,help:list<string>}
     */
    public function vocabulary(User $business): array
    {
        return [
            'lines' => $this->lines($business)->map(fn ($l) => ['name' => (string) $l->name_ar, 'group' => (string) $l->group_name])->values()->all(),
            'units' => array_values($this->unitLabels()),
            'sections' => MenuSection::query()->where('business_id', $business->id)->orderBy('sort_order')->pluck('name_ar')->all(),
            // How the cells that hold more than one thing are written — printed beside the lists in the template.
            'help' => [
                __('المقاسات: صغير=50; وسط=70; كبير=90 — الأول هو الافتراضي.'),
                __('الإضافات: جبنة=10; صوص=5'),
                __('مجموعات الإضافات: الصوصات: كاتشب=5, مايو=5 | الخبز (واحد): أبيض=0, أسمر=3 — «(واحد)» يعني يختار العميل واحدًا فقط.'),
                __('الصور: روابط الصور، رابط في كل سطر. تُضاف للصنف ولا تحذف صوره الموجودة.'),
                __('الباركود: من كتالوج المنصة — يربط الصنف بالمنتج (الاسم والصورة).'),
                __('خانة المقاسات أو الإضافات الفارغة تحذف ما كان على الصنف؛ احذف العمود كله لتتركها كما هي.'),
            ],
        ];
    }

    /** @return list<array<string,mixed>> the merchant's items as sheet rows */
    public function export(User $business): array
    {
        $sections = MenuSection::query()->where('business_id', $business->id)->pluck('name_ar', 'id');
        $lineNames = DB::table('offering_options as oo')->join('options as o', 'o.id', '=', 'oo.option_id')
            ->where('oo.offering_type', (new MenuItem)->getMorphClass())->where('oo.role', 'line')
            ->whereIn('oo.offering_id', MenuItem::query()->where('business_id', $business->id)->select('id'))
            ->pluck('o.name_ar', 'oo.offering_id');

        return MenuItem::query()->where('business_id', $business->id)->orderBy('menu_section_id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (MenuItem $m) => [
                'id' => (int) $m->id,
                'line' => (string) ($lineNames[$m->id] ?? ''),
                'section' => (string) ($sections[$m->menu_section_id] ?? ''),
                'barcode' => $this->details->barcodeOf($m),
                'name_ar' => (string) $m->name_ar,
                'name_en' => (string) ($m->name_en ?? ''),
                'price' => (float) $m->base_price,
                'supply_price' => $m->supply_price !== null ? (float) $m->supply_price : '',
                'unit' => (string) ($this->unitLabels()[$m->sale_unit] ?? ''),
                'quantity' => $m->available_quantity !== null ? (int) $m->available_quantity : '',
                'brand' => (string) ($m->brand_name ?? ''),
                'description_ar' => (string) ($m->description_ar ?? ''),
                'description_en' => (string) ($m->description_en ?? ''),
                'variants' => $this->details->formatVariants($m),
                'extras' => $this->details->formatExtras($m),
                'images' => implode("\n", $this->details->imageUrls($m)),
                'active' => $m->is_active ? 'نعم' : 'لا',
            ])->values()->all();
    }

    /** Two example rows for an empty template, in the merchant's own vocabulary. */
    public function exampleRows(User $business): array
    {
        $lines = $this->lines($business)->take(2)->values();
        $row = fn (string $line, string $name, float $price) => array_merge(array_fill_keys(array_keys(self::COLUMNS), ''), [
            'line' => $line, 'name_ar' => $name, 'price' => $price, 'active' => 'نعم',
            'section' => $line === '' ? 'قسم 1' : '',
        ]);

        return [
            $row((string) ($lines[0]->name_ar ?? ''), (string) ($lines[0]->name_ar ?? 'صنف 1'), 100),
            $row((string) ($lines[1]->name_ar ?? ''), (string) ($lines[1]->name_ar ?? 'صنف 2'), 50),
        ];
    }

    /** CSV text (UTF-8 with a BOM, so Excel shows the Arabic) for these rows. */
    public function toCsv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($this->columns(), 'label'));
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', array_keys(self::COLUMNS)));
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * A CSV as a grid of cells: the header row first (comma, semicolon or tab; a BOM is dropped).
     *
     * @return list<list<string>>
     */
    public function csvGrid(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $first = strtok($content, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $reader = Reader::createFromString($content);
        $reader->setDelimiter($delimiter);

        return array_map(fn ($cells) => array_map(fn ($c) => trim((string) $c), $cells), iterator_to_array($reader->getRecords(), false));
    }

    /** The file's grid from a request: an uploaded CSV, or `grid` (what a client read out of an Excel file). */
    public function gridFromRequest(Request $request): array
    {
        if ($request->hasFile('file')) {
            return $this->csvGrid((string) file_get_contents($request->file('file')->getRealPath()));
        }

        return array_values((array) $request->input('grid', []));
    }

    /** The column mapping a client sent (an object, or a JSON string in a multipart request), or null. */
    public function mappingFromRequest(Request $request): ?array
    {
        $mapping = $request->input('mapping');
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true);
        }

        return is_array($mapping) ? $mapping : null;
    }

    /**
     * «ربط الأعمدة بالترقيم» — المالك، 2026-10-05: a file whose headers are called something else still imports,
     * because each of OUR columns is pointed at the file's column NUMBER. This is the suggestion: for each of our
     * columns, the 1-based number of the file column whose header it recognises (Arabic, English or the key), or
     * null. The first match wins.
     *
     * @param  list<mixed>  $headers  the file's header row
     * @return array<string,?int> our column key => the file's column number (1-based) or null
     */
    public function suggestMapping(array $headers): array
    {
        $mapping = array_fill_keys(array_keys(self::COLUMNS), null);
        foreach (array_values($headers) as $i => $header) {
            $key = $this->keyOf((string) $header);
            if ($key !== null && $mapping[$key] === null) {
                $mapping[$key] = $i + 1;
            }
        }

        return $mapping;
    }

    /** What a file looks like before it is imported: its numbered headers, a few rows, and the suggested mapping. */
    public function inspect(array $grid): array
    {
        $grid = array_values($grid);
        $headers = array_map(fn ($h) => is_scalar($h) ? trim((string) $h) : '', array_values((array) ($grid[0] ?? [])));

        return [
            'headers' => $headers,
            'sample' => array_map(fn ($r) => array_map(fn ($c) => is_scalar($c) ? trim((string) $c) : '', array_values((array) $r)), array_slice($grid, 1, 3)),
            'total_rows' => max(0, count($grid) - 1),
            'mapping' => $this->suggestMapping($headers),
            'columns' => $this->columns(),
        ];
    }

    /**
     * Rows keyed by our column keys, out of a grid (header row first) and a mapping (our key => the file's 1-based
     * column number). Without a mapping the headers are recognised by name, as before.
     *
     * @param  list<list<mixed>>  $grid
     * @param  array<string,mixed>|null  $mapping
     * @return list<array<string,string>>
     */
    public function rowsFromGrid(array $grid, ?array $mapping = null): array
    {
        $grid = array_values($grid);
        if ($grid === []) {
            return [];
        }

        $header = array_shift($grid);
        $mapping ??= $this->suggestMapping((array) $header);

        $columns = [];
        foreach ($mapping as $key => $number) {
            if (array_key_exists((string) $key, self::COLUMNS) && is_numeric($number) && (int) $number >= 1) {
                $columns[(string) $key] = (int) $number - 1;
            }
        }

        return collect($grid)
            ->map(function ($cells) use ($columns) {
                $cells = array_values((array) $cells);
                $row = [];
                foreach ($columns as $key => $index) {
                    $value = $cells[$index] ?? '';
                    $row[$key] = is_scalar($value) ? trim((string) $value) : '';
                }

                return $row;
            })
            ->filter(fn ($row) => collect($row)->filter(fn ($v) => $v !== '')->isNotEmpty())
            ->values()->all();
    }

    /**
     * Rows out of a CSV, headers recognised by name — or by the given mapping (see {@see rowsFromGrid()}).
     *
     * @param  array<string,mixed>|null  $mapping
     * @return list<array<string,string>>
     */
    public function parseCsv(string $content, ?array $mapping = null): array
    {
        return $this->rowsFromGrid($this->csvGrid($content), $mapping);
    }

    /**
     * Run the rows. `dryRun`: everything happens and is rolled back — the report is what an import would do.
     *
     * @param  list<array<string,mixed>>  $rows  keyed by column key (or header — see keyOf())
     * @return array{summary:array{create:int,update:int,error:int},rows:list<array{row:int,action:string,name:string,id:?int,errors:list<string>,warnings:list<string>}>}
     */
    public function import(Request $outer, User $business, array $rows, bool $dryRun): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['rows' => [__('الملف أكبر من :max صف — قسّمه على أكثر من ملف.', ['max' => self::MAX_ROWS])]]);
        }

        $lines = $this->lines($business)->keyBy(fn ($l) => $this->norm((string) $l->name_ar));
        $linesEn = $this->lines($business)->filter(fn ($l) => (string) $l->name_en !== '')->keyBy(fn ($l) => $this->norm((string) $l->name_en));
        $units = $this->unitsByName();
        $report = [];

        DB::beginTransaction();
        try {
            $existing = MenuItem::query()->where('business_id', $business->id)->get(['id', 'name_ar'])
                ->groupBy(fn ($m) => $this->norm((string) $m->name_ar));

            $rows = array_values($rows);
            $currentSection = null;

            foreach ($rows as $i => $raw) {
                $row = $this->normaliseKeys((array) $raw);
                $number = $i + 2; // the sheet's own row number: the header is row 1
                $errors = [];
                $warnings = [];

                // «القسم يُكتب في سطر وتحته أصنافه» — a printed menu is typed as it reads: a line with only a name and
                // no price, followed by priced lines, is a SECTION; the lines under it belong to it until the next.
                if ($this->isSectionHeader($row, $rows, $i)) {
                    $currentSection = trim((string) $row['name_ar']);
                    $report[] = ['row' => $number, 'action' => 'section', 'name' => $currentSection, 'id' => null, 'errors' => [], 'warnings' => []];

                    continue;
                }
                if ($currentSection !== null && trim((string) ($row['section'] ?? '')) === '' && trim((string) ($row['line'] ?? '')) === '') {
                    $row['section'] = $currentSection;
                }

                // A barcode from the shared catalog links the item to that product — and names it when the
                // sheet left the name empty.
                $product = null;
                if (($row['barcode'] ?? '') !== '') {
                    $product = $this->details->productForBarcode((string) $row['barcode']);
                    if (! $product) {
                        $warnings[] = __('الباركود :code ليس في كتالوج المنصة — أُضيف الصنف بدون ربط.', ['code' => $row['barcode']]);
                    }
                }
                $name = trim((string) ($row['name_ar'] ?? '')) ?: (string) ($product->name_ar ?? '');

                $variants = null;
                $extras = null;
                try {
                    if (array_key_exists('variants', $row)) {
                        $variants = $this->details->parseVariants((string) $row['variants']);
                    }
                    if (array_key_exists('extras', $row)) {
                        $extras = $this->details->parseExtras((string) $row['extras']);
                    }
                } catch (\InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                }

                $images = $this->details->parseImageUrls((string) ($row['images'] ?? ''));
                foreach ($images['bad'] as $bad) {
                    $warnings[] = __('«:text» ليس رابط صورة.', ['text' => $bad]);
                }

                $line = null;
                if (($row['line'] ?? '') !== '') {
                    $line = $lines[$this->norm((string) $row['line'])] ?? $linesEn[$this->norm((string) $row['line'])] ?? null;
                    if (! $line) {
                        $errors[] = __('النوع «:name» ليس من أنواع نشاطك.', ['name' => $row['line']]);
                    }
                }

                $unit = null;
                if (($row['unit'] ?? '') !== '') {
                    $unit = $units[$this->norm((string) $row['unit'])] ?? null;
                    if ($unit === null) {
                        $errors[] = __('الوحدة «:name» غير معروفة.', ['name' => $row['unit']]);
                    }
                }

                $price = $this->number($row['price'] ?? '');
                if ($name === '') {
                    $errors[] = __('الاسم عربي مطلوب.');
                }
                if ($price === null) {
                    $errors[] = __('السعر مطلوب ويكون رقمًا.');
                }

                // Which item: the id written in the sheet, else the same Arabic name, else a new one.
                $id = null;
                if (($row['id'] ?? '') !== '') {
                    $id = (int) $row['id'];
                    if (! MenuItem::query()->where('business_id', $business->id)->whereKey($id)->exists()) {
                        $errors[] = __('رقم الصنف :id ليس من أصنافك.', ['id' => $row['id']]);
                    }
                } elseif ($name !== '' && isset($existing[$this->norm($name)])) {
                    if ($existing[$this->norm($name)]->count() > 1) {
                        $errors[] = __('عندك أكثر من صنف بهذا الاسم — اكتب رقم الصنف.');
                    } else {
                        $id = (int) $existing[$this->norm($name)]->first()->id;
                    }
                }

                if ($errors !== []) {
                    $report[] = ['row' => $number, 'action' => 'error', 'name' => $name, 'id' => $id, 'errors' => $errors, 'warnings' => $warnings];

                    continue;
                }

                $payload = array_filter([
                    'name_ar' => $name,
                    'name_en' => (string) ($row['name_en'] ?? ''),
                    'base_price' => $price,
                    'supply_price' => $this->number($row['supply_price'] ?? ''),
                    'sale_unit' => $unit,
                    'available_quantity' => ($row['quantity'] ?? '') !== '' ? (int) $this->number($row['quantity']) : null,
                    'brand_name' => (string) ($row['brand'] ?? ''),
                    'description_ar' => (string) ($row['description_ar'] ?? ''),
                    'description_en' => (string) ($row['description_en'] ?? ''),
                    'line_option_id' => $line ? (int) $line->id : null,
                    'catalog_product_id' => $product ? (int) $product->id : null,
                    'menu_section_id' => ! $line && ($row['section'] ?? '') !== '' ? $this->sectionId($business, (string) $row['section']) : null,
                ], fn ($v) => $v !== null && $v !== '');
                $payload['is_active'] = $this->yes($row['active'] ?? '') ? 1 : 0;

                $isUpdate = $id !== null;
                try {
                    $id = $this->write($outer, $business, $payload, $id);
                    $item = MenuItem::query()->findOrFail($id);
                    if ($variants !== null) {
                        $this->details->writeVariants($item, $variants);
                    }
                    if ($extras !== null) {
                        $this->details->writeExtras($item, $extras);
                    }
                    // Photos are fetched only on the real import: a preview downloads nothing.
                    if (! $dryRun && $images['urls'] !== []) {
                        $warnings = array_merge($warnings, $this->details->attachImages($outer, $item, $images['urls']));
                    }
                    $report[] = ['row' => $number, 'action' => $isUpdate ? 'update' : 'create', 'name' => $name, 'id' => $id, 'errors' => [], 'warnings' => $warnings];
                    // A second row with the same name further down updates this one, it does not add a twin.
                    $existing[$this->norm($name)] ??= collect([(object) ['id' => $id, 'name_ar' => $name]]);
                } catch (ValidationException $e) {
                    $report[] = ['row' => $number, 'action' => 'error', 'name' => $name, 'id' => $id, 'errors' => collect($e->errors())->flatten()->values()->all(), 'warnings' => $warnings];
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        $count = fn (string $action) => count(array_filter($report, fn ($r) => $r['action'] === $action));

        $summary = ['create' => $count('create'), 'update' => $count('update'), 'error' => $count('error')];
        // only a sheet that carried section lines says how many — the summary of every other sheet is unchanged
        if ($count('section') > 0) {
            $summary['section'] = $count('section');
        }

        return ['summary' => $summary, 'rows' => $report];
    }

    /**
     * A row that only names a section: a name, no price and nothing else, with a priced row coming right after it.
     * A lone unpriced name at the end of a sheet (or one followed by another unpriced name) stays an ordinary row, so
     * its «السعر مطلوب» error still shows instead of the row vanishing.
     *
     * @param  array<string,mixed>  $row
     * @param  list<mixed>  $rows
     */
    private function isSectionHeader(array $row, array $rows, int $i): bool
    {
        if (trim((string) ($row['name_ar'] ?? '')) === '') {
            return false;
        }

        foreach ($row as $key => $value) {
            if ($key === 'name_ar' || $key === 'name_en') {
                continue;
            }
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        for ($j = $i + 1; $j < count($rows); $j++) {
            $next = $this->normaliseKeys((array) $rows[$j]);
            if (trim((string) ($next['name_ar'] ?? '')) === '' && $this->number($next['price'] ?? '') === null) {
                continue;
            }

            return $this->number($next['price'] ?? '') !== null;
        }

        return false;
    }

    /** Through the app's own door — see the class comment. Returns the item id. */
    private function write(Request $outer, User $business, array $payload, ?int $id): int
    {
        $inner = Request::create('/', $id ? 'PUT' : 'POST', $payload);
        $inner->setUserResolver(fn () => $outer->user());
        foreach ($outer->attributes->all() as $key => $value) {
            $inner->attributes->set($key, $value);
        }
        $inner->headers->set('Accept', 'application/json');

        $controller = app(BusinessMenuItemController::class);
        if ($id) {
            $controller->update($inner, $id);

            return $id;
        }

        return (int) $controller->store($inner)->getData(true)['data']['id'];
    }

    private function sectionId(User $business, string $name): int
    {
        $name = trim($name);
        $found = MenuSection::query()->where('business_id', $business->id)->get(['id', 'name_ar'])
            ->first(fn ($s) => $this->norm((string) $s->name_ar) === $this->norm($name));

        return (int) ($found?->id ?? MenuSection::query()->create([
            'business_id' => $business->id, 'name_ar' => $name,
            'sort_order' => (int) MenuSection::query()->where('business_id', $business->id)->max('sort_order') + 1,
            'is_active' => true,
        ])->id);
    }

    /** The «line» options this merchant sells under, with their group. */
    private function lines(User $business): Collection
    {
        return collect($this->vocabulary->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id)['lines'])
            ->flatten(1)->unique('id')->values();
    }

    /** code => Arabic label, one code per unit (the shortest). */
    private function unitLabels(): array
    {
        static $labels = null;
        if ($labels !== null) {
            return $labels;
        }
        $labels = [];
        foreach (DB::table('catalog_units')->where('is_active', 1)->where('is_sale_unit', 1)->orderByRaw('CHAR_LENGTH(code)')->get(['code', 'name_ar']) as $u) {
            if (! in_array($u->name_ar, $labels, true) && in_array($u->code, SaleUnits::codes(), true)) {
                $labels[$u->code] = $u->name_ar;
            }
        }

        return $labels;
    }

    /** normalised Arabic/English name or code => the unit code */
    private function unitsByName(): array
    {
        $map = [];
        foreach (DB::table('catalog_units')->where('is_active', 1)->where('is_sale_unit', 1)->orderByRaw('CHAR_LENGTH(code) DESC')->get(['code', 'name_ar', 'name_en']) as $u) {
            if (! in_array($u->code, SaleUnits::codes(), true)) {
                continue;
            }
            foreach ([$u->code, $u->name_ar, $u->name_en] as $word) {
                if ((string) $word !== '') {
                    $map[$this->norm((string) $word)] = (string) $u->code;
                }
            }
        }
        foreach (['كيلو', 'كيلوجرام', 'كجم', 'كغ'] as $kilo) {
            $map[$this->norm($kilo)] ??= 'kg';
        }

        return $map;
    }

    /** A header (Arabic, English or the key itself) → column key, or null for a column we do not read. */
    private function keyOf(string $header): ?string
    {
        $h = $this->norm($header);
        foreach (self::COLUMNS as $key => [$ar, $en]) {
            if (in_array($h, [$this->norm($key), $this->norm($ar), $this->norm($en)], true)) {
                return $key;
            }
        }

        return null;
    }

    /** Rows sent as JSON may carry headers instead of keys. */
    private function normaliseKeys(array $row): array
    {
        $out = [];
        foreach ($row as $header => $value) {
            $key = array_key_exists((string) $header, self::COLUMNS) ? (string) $header : $this->keyOf((string) $header);
            if ($key !== null) {
                $out[$key] = is_scalar($value) ? trim((string) $value) : '';
            }
        }

        return $out;
    }

    private function number(mixed $value): ?float
    {
        $text = strtr(trim((string) $value), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '']);

        return $text !== '' && is_numeric($text) ? round((float) $text, 2) : null;
    }

    private function yes(mixed $value): bool
    {
        $v = $this->norm((string) $value);

        return $v === '' || in_array($v, ['نعم', 'yes', 'y', '1', 'true', 'ايوه', 'اه', 'نشط'], true);
    }

    /** Arabic-tolerant comparison: hamzas, taa marbuta, alef maqsura, spaces and case. */
    private function norm(string $text): string
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));

        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ـ' => '']);
    }
}
