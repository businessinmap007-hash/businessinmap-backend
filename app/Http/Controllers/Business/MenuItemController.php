<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\PlatformService;
use App\Services\Media\ImageUploadService;
use App\Services\MerchantOfferingVocabulary;
use App\Support\SaleUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My menu" for the business owner. Simple scoped CRUD over menu_items; these
 * are the items customers can order (dine-in food attaches to a booking, or a
 * standalone menu order). Every query is scoped to business_id = auth id.
 */
class MenuItemController extends Controller
{
    use ResolvesOwnerCatalog {
        businessId as protected ownerBusinessId;
    }

    /** Matches the API's cap — one behaviour, two doors. */
    private const MAX_IMAGES = 10;

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    protected function businessId(): int
    {
        return (int) (Auth::id() ?: $this->ownerBusinessId());
    }

    /**
     * What this merchant may say an item IS. Narrowed to the options he ticked
     * about himself, so a furniture factory picks from its own pieces instead
     * of the platform's twelve.
     */
    private function vocabulary(): array
    {
        return $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId());
    }

    private function scopedItem(int $id): MenuItem
    {
        return MenuItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $active = $request->get('active', '');

        $rows = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->when($active !== '' && $active !== null, fn ($query) => $query->where('is_active', (int) $active))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(name_ar) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$term]);
                });
            })
            ->with('section:id,name_ar')
            ->orderByDesc('is_featured')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.menu.index', [
            'rows' => $rows,
            'q' => $q,
            'active' => (string) $active,
        ]);
    }

    public function create(): View
    {
        return view('business.menu.create', [
            'row' => new MenuItem(['is_active' => 1, 'sort_order' => 0, 'base_price' => 0]),
            'sections' => $this->sections(),
            'itemTypes' => $this->itemTypes(),
            'saleUnits' => SaleUnits::options(),
            'vocabulary' => $this->vocabulary(),
            'lineId' => null,
            'modifierIds' => collect(),
        ]);
    }

    /**
     * The kinds of thing this merchant's child may put on a menu — «مشويات»،
     * «ساندوتشات» for a restaurant, «قطعة أثاث» for a showroom. Comes straight
     * from the taxonomy (`config.allowed_item_types`), so it needs no typing
     * and cannot drift from what the platform says the child sells.
     *
     * @return array<int,array{key:string,label:string}>
     */
    private function itemTypes(): array
    {
        // One vocabulary, not two. Where the merchant has line options, THEY
        // are the heading — «مشويات»، «غرفة نوم — مودرن» — and offering him a
        // parallel list of item types saying the same thing is exactly the
        // duplication that made the customer's road too long.
        if (! empty($this->vocabulary()['lines'] ?? [])) {
            return [];
        }

        $services = $this->servicesForChild();
        $menu = $services->firstWhere('key', PlatformService::KEY_MENU);

        if (! $menu) {
            return [];
        }

        return $this->allowedTypesByService($services)[(int) $menu->id] ?? [];
    }

    /** The owner's sections for the item form dropdown. */
    private function sections()
    {
        return MenuSection::query()
            ->where('business_id', $this->businessId())
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar']);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = MenuItem::create($this->validateData($request) + ['business_id' => $this->businessId()]);
        $this->applyVocabulary($request, $item);

        return redirect()
            ->route('business.menu.index')
            ->with('success', 'تمت إضافة الصنف بنجاح.');
    }

    public function edit(int $id): View
    {
        $row = $this->scopedItem($id);
        $row->load([
            'variants' => fn ($q) => $q->orderBy('id'),
            'extras' => fn ($q) => $q->orderBy('id'),
            'images',
        ]);

        return view('business.menu.edit', [
            'row' => $row,
            'sections' => $this->sections(),
            'itemTypes' => $this->itemTypes(),
            'saleUnits' => SaleUnits::options(),
            'vocabulary' => $this->vocabulary(),
            'lineId' => $row->lineOption()?->id,
            'modifierIds' => $row->modifierOptions()->pluck('id'),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);
        $item->update($this->validateData($request));
        $this->applyVocabulary($request, $item);

        return back()->with('success', 'تم تحديث الصنف بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedItem($id)->delete();

        return redirect()
            ->route('business.menu.index')
            ->with('success', 'تم حذف الصنف بنجاح.');
    }

    /**
     * Photos for an item, from the panel.
     *
     * Only on EDIT: an item has to exist before it can own a gallery, and the
     * create form is a plain (non-multipart) POST that would silently drop the
     * files. Same ceiling and same storage as the API — one behaviour, two
     * doors.
     */
    public function storeImages(Request $request, int $id): RedirectResponse
    {
        $item = $this->scopedItem($id);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . self::MAX_IMAGES],
            'images.*' => ImageUploadService::validationRules(),
        ]);

        $room = self::MAX_IMAGES - $item->images()->count();

        if ($room <= 0) {
            return back()->withErrors(['images' => __('الحد الأقصى :max صور للصنف الواحد.', ['max' => self::MAX_IMAGES])]);
        }

        $uploads = app(ImageUploadService::class);

        foreach (array_slice($request->file('images'), 0, $room) as $file) {
            $item->images()->create([
                'image' => $uploads->store($file),
                'source' => Image::SOURCE_UPLOAD,
            ]);
        }

        return back()->with('success', 'تم رفع الصور.');
    }

    /** The row and the FILE, together — see MenuItem::booted for why. */
    public function destroyImage(int $id, int $image): RedirectResponse
    {
        $item = $this->scopedItem($id);
        $row = $item->images()->findOrFail($image);

        app(ImageUploadService::class)->delete($row->image);
        $row->delete();

        return back()->with('success', 'تم حذف الصورة.');
    }

    /**
     * Store what the item IS, refusing anything outside this merchant's own
     * vocabulary — an option he never claimed, or one whose group is
     * descriptive and has no business carrying a price.
     */
    private function applyVocabulary(Request $request, MenuItem $item): void
    {
        $picks = $this->vocabulary->pickableIds($this->businessId(), $this->childId(), $this->rootId());

        $line = (int) $request->input('line_option_id', 0);
        $line = $picks['lines']->contains($line) ? $line : null;

        $modifiers = collect($request->input('modifier_option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $picks['modifiers']->contains($id))
            ->values()
            ->all();

        // شاشة المنيو لا تعرض خانات الزيادة بعد؛ فتُمرَّر كما هى بدل أن تُمحى.
        $item->syncOfferingOptions($line, $modifiers, $item->currentOfferingAdjustments());
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'menu_section_id' => [
                'nullable', 'integer',
                Rule::exists('menu_sections', 'id')->where('business_id', $this->businessId()),
            ],
            'item_type' => ['nullable', 'string', 'max:60'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            // What the price is the price OF. Empty means «by the item», which
            // is what a sandwich is; a greengrocer says «كجم».
            'sale_unit' => ['nullable', Rule::in(SaleUnits::codes())],
            // NULL means «لا أتابع الكمية» — a kitchen does not count
            // sandwiches. Zero is the other claim: «معروض، ونفد».
            'available_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ], [], [
            'name_ar' => 'الاسم العربي',
            'base_price' => 'السعر',
            'sale_unit' => 'وحدة البيع',
            'available_quantity' => 'الكمية المتاحة',
            'menu_section_id' => 'القسم',
            'item_type' => 'النوع',
        ]);

        // Never trust the posted key: a merchant may only file an item under a
        // kind his own child is allowed to list.
        $type = trim((string) ($data['item_type'] ?? ''));
        $allowed = array_column($this->itemTypes(), 'key');

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'menu_section_id' => ($data['menu_section_id'] ?? null) ?: null,
            'item_type' => in_array($type, $allowed, true) ? $type : null,
            'description_ar' => trim((string) ($data['description_ar'] ?? '')) ?: null,
            'description_en' => trim((string) ($data['description_en'] ?? '')) ?: null,
            'base_price' => round((float) $data['base_price'], 2),
            // Empty string and «by the item» are the same answer; both null.
            'sale_unit' => trim((string) ($data['sale_unit'] ?? '')) ?: null,
            'available_quantity' => ($data['available_quantity'] ?? null) === null
                || $data['available_quantity'] === ''
                ? null
                : max(0, (int) $data['available_quantity']),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
