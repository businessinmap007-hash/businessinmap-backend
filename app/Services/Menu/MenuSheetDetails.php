<?php

namespace App\Services\Menu;

use App\Http\Controllers\Api\V2\BusinessMenuItemController;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\MenuItemVariant;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The second phase of «استيراد وتصدير المنيو» (المالك، 2026-10-05): what an item carries beyond its name and
 * price, written in one cell each so a sheet stays one row per item.
 *
 *   المقاسات   «صغير=50; وسط=70; كبير=90»            — the first one is the default
 *   الإضافات   «جبنة=10; صوص=5»                       — extras the customer may add
 *              «الصوصات: كاتشب=5, مايو=5 | الخبز (واحد): أبيض=0, أسمر=3»
 *                                                     — groups, «(واحد)» = pick one
 *   الصور      links, one per line or separated by spaces — added to the item, never removed by a sheet
 *   الباركود   a barcode from the shared catalog       — links the item to that product (its name and photo)
 *
 * The shop's own priced services (طريقة الطهي…) are not in the sheet: they come from «خدمات المحل».
 */
final class MenuSheetDetails
{
    public const MAX_IMAGES_PER_ROW = 5;

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    /** Overridable in tests: host → its IP addresses. */
    public static ?\Closure $resolveHost = null;

    // ───────────────────────── المقاسات ─────────────────────────

    public function formatVariants(MenuItem $item): string
    {
        return MenuItemVariant::query()->where('menu_item_id', $item->id)->where('type', '!=', 'payment')
            ->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn ($v) => $v->name_ar . '=' . $this->plain((float) ($v->price ?? ((float) $item->base_price + (float) $v->price_delta))))
            ->implode('; ');
    }

    /**
     * @return list<array{name:string,price:float}>
     *
     * @throws \InvalidArgumentException with the message for the row
     */
    public function parseVariants(string $cell): array
    {
        return array_map(function (array $pair) {
            if ($pair['price'] === null) {
                throw new \InvalidArgumentException(__('المقاس «:name» بلا سعر — اكتبه هكذا: صغير=50', ['name' => $pair['name']]));
            }

            return ['name' => $pair['name'], 'price' => $pair['price']];
        }, $this->pairs($cell));
    }

    /** Replace the item's sizes with these (an empty list removes them). */
    public function writeVariants(MenuItem $item, array $variants): void
    {
        MenuItemVariant::query()->where('menu_item_id', $item->id)->where('type', '!=', 'payment')->delete();
        foreach (array_values($variants) as $i => $v) {
            MenuItemVariant::query()->create([
                'menu_item_id' => $item->id, 'type' => 'size', 'name_ar' => $v['name'],
                'price' => $v['price'], 'is_default' => $i === 0, 'is_active' => true,
            ]);
        }
    }

    // ───────────────────────── الإضافات ─────────────────────────

    public function formatExtras(MenuItem $item): string
    {
        $extras = MenuItemExtra::query()->where('menu_item_id', $item->id)->whereNull('source_option_id')->orderBy('id')->get();
        $groups = MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->whereNull('source_group_id')->orderBy('reorder')->orderBy('id')->get();
        $pair = fn ($e) => $e->name_ar . '=' . $this->plain((float) $e->price);

        $parts = [];
        $loose = $extras->whereNull('extra_group_id');
        if ($loose->isNotEmpty()) {
            $parts[] = $loose->map($pair)->implode('; ');
        }
        foreach ($groups as $g) {
            $own = $extras->where('extra_group_id', $g->id);
            $parts[] = $g->name_ar . ($g->selection_type === MenuItemExtraGroup::SELECTION_SINGLE ? ' (واحد)' : '') . ': ' . $own->map($pair)->implode(', ');
        }

        return implode(' | ', $parts);
    }

    /**
     * @return list<array{group:?string,single:bool,items:list<array{name:string,price:float}>}>
     *
     * @throws \InvalidArgumentException
     */
    public function parseExtras(string $cell): array
    {
        $out = [];
        foreach (preg_split('/\s*\|\s*/u', trim($cell)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            $group = null;
            $single = false;
            if (preg_match('/^([^=:]+):(.*)$/u', $part, $m)) {
                $group = trim($m[1]);
                $part = $m[2];
                if (preg_match('/\((واحد|one|single)\)\s*$/iu', $group)) {
                    $single = true;
                    $group = trim(preg_replace('/\((واحد|one|single)\)\s*$/iu', '', $group) ?? $group);
                }
            }
            $items = array_map(fn ($p) => ['name' => $p['name'], 'price' => $p['price'] ?? 0.0], $this->pairs($part));
            if ($items === []) {
                throw new \InvalidArgumentException(__('مجموعة الإضافات «:name» فارغة.', ['name' => $group ?? '']));
            }
            $out[] = ['group' => $group, 'single' => $single, 'items' => $items];
        }

        return $out;
    }

    /** Replace the item's own extras with these; the shop's services are left alone. */
    public function writeExtras(MenuItem $item, array $groups): void
    {
        MenuItemExtra::query()->where('menu_item_id', $item->id)->whereNull('source_option_id')->delete();
        MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->whereNull('source_group_id')->delete();

        foreach (array_values($groups) as $i => $g) {
            $groupId = null;
            if ($g['group'] !== null) {
                $groupId = MenuItemExtraGroup::query()->create([
                    'menu_item_id' => $item->id, 'name_ar' => $g['group'], 'reorder' => $i + 1, 'is_active' => true,
                    'selection_type' => $g['single'] ? MenuItemExtraGroup::SELECTION_SINGLE : MenuItemExtraGroup::SELECTION_MULTIPLE,
                ])->id;
            }
            foreach ($g['items'] as $e) {
                MenuItemExtra::query()->create([
                    'menu_item_id' => $item->id, 'extra_group_id' => $groupId, 'name_ar' => $e['name'],
                    'price' => $e['price'], 'max_qty' => 1, 'is_active' => true,
                ]);
            }
        }
    }

    // ───────────────────────── الباركود ─────────────────────────

    public function barcodeOf(MenuItem $item): string
    {
        if (! $item->catalog_product_id) {
            return '';
        }

        return (string) (DB::table('catalog_products')->where('id', $item->catalog_product_id)->value('default_barcode')
            ?? DB::table('catalog_product_barcodes')->where('product_id', $item->catalog_product_id)->orderByDesc('is_primary')->value('barcode')
            ?? '');
    }

    /** The approved catalog product this barcode belongs to, or null. */
    public function productForBarcode(string $barcode): ?object
    {
        $barcode = preg_replace('/\s+/', '', $barcode) ?? $barcode;
        if ($barcode === '') {
            return null;
        }
        $id = DB::table('catalog_products')->where('default_barcode', $barcode)->whereNull('deleted_at')->where('approval_status', 'approved')->value('id')
            ?? DB::table('catalog_product_barcodes as b')->join('catalog_products as p', 'p.id', '=', 'b.product_id')
                ->where('b.barcode', $barcode)->whereNull('p.deleted_at')->where('p.approval_status', 'approved')->value('p.id');

        return $id ? DB::table('catalog_products')->where('id', $id)->first(['id', 'name_ar', 'name_en']) : null;
    }

    // ───────────────────────── الصور ─────────────────────────

    /** @return list<string> absolute links to the item's photos */
    public function imageUrls(MenuItem $item): array
    {
        return $item->images()->orderBy('id')->pluck('image')->map(fn ($path) => url((string) $path))->all();
    }

    /**
     * The links in a cell, and the ones that are not links at all.
     *
     * @return array{urls:list<string>,bad:list<string>}
     */
    public function parseImageUrls(string $cell): array
    {
        $urls = [];
        $bad = [];
        foreach (preg_split('/[\s|,،]+/u', trim($cell)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            filter_var($token, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($token, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $urls[] = $token
                : $bad[] = $token;
        }

        return ['urls' => array_values(array_unique($urls)), 'bad' => $bad];
    }

    /**
     * Download each new link and add it to the item, through the app's own door (its limits, its second-hand
     * rule). A link to a photo the item already has is skipped — an exported sheet comes back unchanged.
     *
     * @return list<string> warnings for this row
     */
    public function attachImages(Request $outer, MenuItem $item, array $urls): array
    {
        $warnings = [];
        $have = $item->images()->pluck('image')->map(fn ($p) => ltrim((string) $p, '/'))->all();
        $files = [];

        foreach (array_slice($urls, 0, self::MAX_IMAGES_PER_ROW) as $url) {
            $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            if (in_array($path, $have, true) || collect($have)->contains(fn ($h) => str_ends_with($path, $h))) {
                continue;
            }
            try {
                $files[] = $this->fetch($url);
            } catch (\Throwable $e) {
                $warnings[] = __('تعذّر تحميل الصورة: :url', ['url' => $url]);
            }
        }
        if (count($urls) > self::MAX_IMAGES_PER_ROW) {
            $warnings[] = __('أول :max صور فقط تُضاف من الصف الواحد.', ['max' => self::MAX_IMAGES_PER_ROW]);
        }
        if ($files === []) {
            return $warnings;
        }

        $inner = Request::create('/', 'POST', [], [], ['images' => $files]);
        $inner->setUserResolver(fn () => $outer->user());
        foreach ($outer->attributes->all() as $key => $value) {
            $inner->attributes->set($key, $value);
        }
        $inner->headers->set('Accept', 'application/json');

        try {
            $response = app(BusinessMenuItemController::class)->storeImages($inner, (int) $item->id);
            if ($response->getStatusCode() >= 400) {
                $warnings[] = (string) ($response->getData(true)['message'] ?? __('تعذّر إضافة الصور.'));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $warnings[] = collect($e->errors())->flatten()->first() ?: __('تعذّر إضافة الصور.');
        }

        return $warnings;
    }

    /** One link → a local image file. Our own uploads are copied from disk; anything else is fetched safely. */
    private function fetch(string $url): UploadedFile
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (str_starts_with($path, 'files/uploads/') && (parse_url($url, PHP_URL_HOST) === $ownHost || in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true))) {
            $local = public_path($path);
            if (! is_file($local) || ! str_starts_with((string) realpath($local), (string) realpath(public_path('files/uploads')))) {
                throw new \RuntimeException('missing');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'mimg');
            copy($local, $tmp);

            return new UploadedFile($tmp, basename($path), mime_content_type($tmp) ?: null, null, true);
        }

        // Someone else's server: only a public address (never this machine or the private network), no
        // redirects, an image, at most 5 MB.
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = self::$resolveHost ? (self::$resolveHost)($host) : (gethostbynamel($host) ?: []);
        if ($ips === [] || collect($ips)->contains(fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new \RuntimeException('blocked host');
        }

        // A named client: image hosts (Wikimedia among them) refuse a request that does not say who it is.
        $response = Http::timeout(10)->withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'BIM-MenuImport/1.0 (+' . config('app.url') . ')', 'Accept' => 'image/*'])
            ->get($url);
        $type = strtolower((string) $response->header('Content-Type'));
        $body = $response->body();
        if (! $response->successful() || ! str_starts_with($type, 'image/') || strlen($body) === 0 || strlen($body) > self::MAX_IMAGE_BYTES) {
            throw new \RuntimeException('not an image');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mimg');
        file_put_contents($tmp, $body);
        $ext = match (true) {
            str_contains($type, 'png') => 'png',
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'gif') => 'gif',
            default => 'jpg',
        };

        return new UploadedFile($tmp, 'sheet.' . $ext, $type, null, true);
    }

    // ───────────────────────── helpers ─────────────────────────

    /**
     * «اسم=سعر» pairs separated by ; ؛ , or ، — a name with no «=» has no price.
     *
     * @return list<array{name:string,price:?float}>
     */
    private function pairs(string $text): array
    {
        $out = [];
        foreach (preg_split('/\s*[;؛,،]\s*/u', trim($text)) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            [$name, $price] = array_pad(explode('=', $chunk, 2), 2, null);
            $name = trim((string) $name);
            if ($name === '') {
                throw new \InvalidArgumentException(__('«:text» بلا اسم.', ['text' => $chunk]));
            }
            $number = null;
            if ($price !== null && trim($price) !== '') {
                $digits = strtr(trim($price), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);
                if (! is_numeric($digits) || (float) $digits < 0) {
                    throw new \InvalidArgumentException(__('سعر «:name» ليس رقمًا.', ['name' => $name]));
                }
                $number = round((float) $digits, 2);
            }
            $out[] = ['name' => $name, 'price' => $number];
        }

        return $out;
    }

    private function plain(float $v): string
    {
        return $v == floor($v) ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
