<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\OwnerListing;
use App\Models\OwnerListingInquiry;
use App\Services\Media\ImageUploadService;
use App\Services\OwnerListings\ContactRevealService;
use App\Services\OwnerListings\OwnerListingCategories;
use App\Services\OwnerListings\OwnerListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * «إعلان من المالك»: any account lists ONE item per category (a car, a flat, a phone…) for sale, rent or swap, with no
 * business account behind it. See {@see OwnerListingService} for the rules and the migration for the idea.
 */
final class OwnerListingController extends Controller
{
    public function __construct(
        private readonly OwnerListingService $listings,
        private readonly ContactRevealService $reveal
    ) {}

    /** GET owner-listings/categories — the categories, their deals and the fields each one asks. */
    public function categories()
    {
        return response()->json(['success' => true, 'data' => ['categories' => OwnerListingCategories::forApi($this->english())]]);
    }

    /** GET owner-listings — what a person browses: live listings, newest first. */
    public function index(Request $request)
    {
        $filters = Validator::make($request->all(), [
            'category' => ['nullable', 'string'],
            'deal' => ['nullable', 'in:sale,rent,swap'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        $page = $this->listings->browse($filters)->with(['images', 'owner:id,name,logo'])->paginate((int) ($filters['per_page'] ?? 20));
        $places = $this->placeNames($page->getCollection());
        $page->getCollection()->transform(fn (OwnerListing $l) => $this->card($l, $request, $places));

        return response()->json(['success' => true, 'data' => $page]);
    }

    /** GET owner-listings/mine — the caller's own, in any state. */
    public function mine(Request $request)
    {
        $rows = OwnerListing::query()->where('user_id', (int) $request->user()->id)->with(['images', 'owner:id,name,logo'])->latest('id')->get();
        $places = $this->placeNames($rows);

        return response()->json(['success' => true, 'data' => ['results' => $rows->map(fn (OwnerListing $l) => $this->card($l, $request, $places))->values()]]);
    }

    /** GET owner-listings/{id} */
    public function show(Request $request, int $id)
    {
        $listing = OwnerListing::query()->with(['images', 'owner:id,name,logo'])->findOrFail($id);
        $mine = (int) $listing->user_id === (int) $request->user()->id;

        // a stranger sees only a live listing; its owner sees it in whatever state it is
        abort_if(! $mine && ! $listing->isVisible(), 404);

        return response()->json(['success' => true, 'data' => ['listing' => $this->detail($listing, $request)]]);
    }

    /** POST owner-listings — {category, deal, title, description?, price?, price_unit?, governorate_id, city_id?, attributes?} */
    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'category' => ['required', 'string'],
            'deal' => ['required', 'in:sale,rent,swap'],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'in:day,week,month'],
            'governorate_id' => ['required', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'attributes' => ['nullable', 'array'],
        ])->validate();

        $listing = $this->listings->create($request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => __('نُشر إعلانك. أضف صورة واحدة على الأقل ليظهر للناس.'),
            'data' => ['listing' => $this->detail($listing->load('images', 'owner:id,name,logo'), $request)],
        ], 201);
    }

    /** PUT owner-listings/{id} */
    public function update(Request $request, int $id)
    {
        $listing = $this->ownedOrFail($request, $id);
        $data = Validator::make($request->all(), [
            'deal' => ['nullable', 'in:sale,rent,swap'],
            'title' => ['nullable', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'in:day,week,month'],
            'governorate_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'attributes' => ['nullable', 'array'],
        ])->validate();

        $listing = $this->listings->update($listing, array_filter($data, fn ($v) => $v !== null) + (array_key_exists('description', $request->all()) ? ['description' => $request->input('description')] : []));

        return response()->json(['success' => true, 'data' => ['listing' => $this->detail($listing->load('images', 'owner:id,name,logo'), $request)]]);
    }

    /** POST owner-listings/{id}/finish {reason} — sold / rented / swapped / withdrawn: hidden now, removed in three days. */
    public function finish(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['required', 'in:sold,rented,swapped,withdrawn']]);
        $listing = $this->listings->finish($this->ownedOrFail($request, $id), $data['reason']);

        return response()->json([
            'success' => true,
            'message' => __('تم إنهاء الإعلان وسيُحذف بعد :days أيام.', ['days' => (int) config('bim.owner_listings.purge_days', 3)]),
            'data' => ['listing' => $this->detail($listing->load('images', 'owner:id,name,logo'), $request)],
        ]);
    }

    /** POST owner-listings/{id}/renew */
    public function renew(Request $request, int $id)
    {
        $listing = $this->listings->renew($this->ownedOrFail($request, $id));

        return response()->json(['success' => true, 'data' => ['listing' => $this->detail($listing->load('images', 'owner:id,name,logo'), $request)]]);
    }

    /** DELETE owner-listings/{id} — now, with its photos and its chats. */
    public function destroy(Request $request, int $id)
    {
        $this->listings->remove($this->ownedOrFail($request, $id));

        return response()->json(['success' => true, 'message' => __('تم حذف الإعلان.')]);
    }

    /** POST owner-listings/{id}/images — multipart images[] (up to the limit per listing). */
    public function addImages(Request $request, int $id)
    {
        $listing = $this->ownedOrFail($request, $id);
        $max = (int) config('bim.owner_listings.max_images', 8);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . $max],
            'images.*' => ImageUploadService::validationRules(),
        ]);

        if ($listing->images()->count() + count($request->file('images', [])) > $max) {
            return response()->json(['success' => false, 'message' => __('الحد الأقصى :max صور للإعلان.', ['max' => $max])], 422);
        }

        $uploads = app(ImageUploadService::class);

        foreach ($request->file('images') as $file) {
            $listing->images()->create(['image' => $uploads->store($file)]);
        }

        return response()->json(['success' => true, 'data' => ['images' => $listing->images()->get()->map(fn (Image $i) => ['id' => (int) $i->id, 'image' => (string) $i->image])->values()]], 201);
    }

    /** DELETE owner-listings/{id}/images/{image} */
    public function removeImage(Request $request, int $id, int $image)
    {
        $listing = $this->ownedOrFail($request, $id);
        $row = $listing->images()->findOrFail($image);
        app(ImageUploadService::class)->delete($row->image);
        $row->delete();

        return response()->json(['success' => true]);
    }

    /** POST owner-listings/{id}/report {reason?} */
    public function report(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $listing = OwnerListing::query()->findOrFail($id);
        $this->listings->report($listing, $request->user(), $data['reason'] ?? null);

        return response()->json(['success' => true, 'message' => __('وصلنا بلاغك، شكرًا.')]);
    }

    /** POST owner-listings/{id}/inquiries — the buyer's conversation about it (chat: operation-chats/listing/{inquiry}). */
    public function inquire(Request $request, int $id)
    {
        $listing = OwnerListing::query()->findOrFail($id);
        $inquiry = $this->listings->inquire($listing, $request->user());

        return response()->json(['success' => true, 'data' => ['inquiry' => $this->inquiryPayload($inquiry->load('listing.images'), $request)]], 201);
    }

    // ---- the inquiries --------------------------------------------------------------------------------------------

    /** GET owner-listing-inquiries — the conversations I am in, as buyer or as seller. */
    public function inquiries(Request $request)
    {
        $uid = (int) $request->user()->id;
        $rows = OwnerListingInquiry::query()->where(fn ($q) => $q->where('buyer_id', $uid)->orWhere('seller_id', $uid))
            ->with(['listing.images', 'buyer:id,name', 'seller:id,name'])->latest('updated_at')->limit(100)->get();

        return response()->json(['success' => true, 'data' => ['results' => $rows->map(fn ($i) => $this->inquiryPayload($i, $request))->values()]]);
    }

    /** GET owner-listing-inquiries/{inquiry} */
    public function inquiry(Request $request, int $inquiry)
    {
        return response()->json(['success' => true, 'data' => ['inquiry' => $this->inquiryPayload($this->partyInquiry($request, $inquiry), $request)]]);
    }

    /** POST owner-listing-inquiries/{inquiry}/reveal/request — «اتفقنا»: ask to swap numbers. */
    public function revealRequest(Request $request, int $inquiry)
    {
        $row = $this->reveal->request($this->partyInquiry($request, $inquiry), $request->user());

        return response()->json(['success' => true, 'data' => ['inquiry' => $this->inquiryPayload($row->load('listing.images'), $request)]]);
    }

    /** POST owner-listing-inquiries/{inquiry}/reveal/accept — agree: the fee is taken and the numbers appear. */
    public function revealAccept(Request $request, int $inquiry)
    {
        $row = $this->reveal->accept($this->partyInquiry($request, $inquiry), $request->user());

        return response()->json(['success' => true, 'data' => ['inquiry' => $this->inquiryPayload($row->load('listing.images'), $request)]]);
    }

    /** POST owner-listing-inquiries/{inquiry}/reveal/cancel — decline the request, or take mine back. */
    public function revealCancel(Request $request, int $inquiry)
    {
        $row = $this->reveal->cancel($this->partyInquiry($request, $inquiry), $request->user());

        return response()->json(['success' => true, 'data' => ['inquiry' => $this->inquiryPayload($row->load('listing.images'), $request)]]);
    }

    // ---- shapes ---------------------------------------------------------------------------------------------------

    private function english(): bool
    {
        return app()->getLocale() === 'en';
    }

    private function ownedOrFail(Request $request, int $id): OwnerListing
    {
        return OwnerListing::query()->where('user_id', (int) $request->user()->id)->findOrFail($id);
    }

    private function partyInquiry(Request $request, int $id): OwnerListingInquiry
    {
        $row = OwnerListingInquiry::query()->with('listing')->findOrFail($id);
        // 404, not 403: a stranger must not learn the conversation exists
        abort_if(! in_array((int) $request->user()->id, $row->parties(), true), 404);

        return $row;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,OwnerListing>  $rows
     * @return array{governorates:array<int,string>,cities:array<int,string>}
     */
    private function placeNames($rows): array
    {
        $en = $this->english();
        $pick = fn ($r) => (string) ($en ? ($r->name_en ?: $r->name_ar) : ($r->name_ar ?: $r->name_en));

        return [
            'governorates' => DB::table('governorates')->whereIn('id', $rows->pluck('governorate_id')->filter()->unique())->get(['id', 'name_ar', 'name_en'])->mapWithKeys(fn ($r) => [(int) $r->id => $pick($r)])->all(),
            'cities' => DB::table('cities')->whereIn('id', $rows->pluck('city_id')->filter()->unique())->get(['id', 'name_ar', 'name_en'])->mapWithKeys(fn ($r) => [(int) $r->id => $pick($r)])->all(),
        ];
    }

    /** @param array{governorates:array<int,string>,cities:array<int,string>} $places */
    private function card(OwnerListing $l, Request $request, array $places): array
    {
        $cover = $l->images->first();

        return [
            'id' => (int) $l->id,
            'category' => ['key' => $l->category, 'name' => OwnerListingCategories::name($l->category, $this->english())],
            'deal' => $l->deal,
            'title' => $l->title,
            'price' => $l->price !== null ? (float) $l->price : null,
            'price_unit' => $l->price_unit,
            'currency' => $l->currency,
            'governorate' => $places['governorates'][(int) $l->governorate_id] ?? null,
            'city' => $l->city_id ? ($places['cities'][(int) $l->city_id] ?? null) : null,
            'cover' => $cover ? (string) $cover->image : null,
            'images_count' => $l->images->count(),
            'status' => $l->status,
            'mine' => (int) $l->user_id === (int) $request->user()->id,
            'expires_at' => optional($l->expires_at)->toIso8601String(),
            'purge_at' => optional($l->purge_at)->toIso8601String(),
            'created_at' => optional($l->created_at)->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function detail(OwnerListing $l, Request $request): array
    {
        $places = $this->placeNames(collect([$l]));
        $uid = (int) $request->user()->id;
        $mine = (int) $l->user_id === $uid;
        $inquiry = $mine ? null : OwnerListingInquiry::query()->where('owner_listing_id', $l->id)->where('buyer_id', $uid)->first();

        return $this->card($l, $request, $places) + [
            'description' => $l->description,
            'attributes' => OwnerListingCategories::describe($l->category, (array) $l->attributes, $this->english()),
            'raw_attributes' => (object) (array) $l->attributes,
            'images' => $l->images->map(fn (Image $i) => ['id' => (int) $i->id, 'image' => (string) $i->image])->values(),
            // the person, never the number
            'owner' => ['id' => (int) $l->user_id, 'name' => $l->owner?->name, 'logo' => $l->owner?->logo],
            'my_inquiry_id' => $inquiry?->id ? (int) $inquiry->id : null,
            'inquiries_count' => $mine ? (int) $l->inquiries()->count() : null,
        ];
    }

    /** @return array<string,mixed> */
    private function inquiryPayload(OwnerListingInquiry $i, Request $request): array
    {
        $viewer = $request->user();
        $isBuyer = (int) $i->buyer_id === (int) $viewer->id;
        $quote = $this->reveal->quote($i);
        $listing = $i->listing;
        $cover = $listing?->relationLoaded('images') ? $listing->images->first() : $listing?->images()->first();
        $otherId = $isBuyer ? (int) $i->seller_id : (int) $i->buyer_id;

        return [
            'id' => (int) $i->id,
            'status' => (string) $i->status,
            'role' => $isBuyer ? 'buyer' : 'seller',
            'listing' => $listing ? [
                'id' => (int) $listing->id,
                'title' => $listing->title,
                'price' => $listing->price !== null ? (float) $listing->price : null,
                'deal' => $listing->deal,
                'status' => $listing->status,
                'cover' => $cover ? (string) $cover->image : null,
            ] : null,
            'other' => ['id' => $otherId, 'name' => DB::table('users')->where('id', $otherId)->value('name')],
            'reveal' => [
                'requested_by_me' => $i->status === OwnerListingInquiry::STATUS_REVEAL_REQUESTED && (int) $i->reveal_requested_by === (int) $viewer->id,
                'requested_by_other' => $i->status === OwnerListingInquiry::STATUS_REVEAL_REQUESTED && (int) $i->reveal_requested_by !== (int) $viewer->id,
                'fee_total' => $quote['total'],
                'my_share' => (float) ($quote['split'][(int) $viewer->id] ?? 0),
                'payer' => $quote['payer'],
            ],
            // the other side's number — only after both said yes and the fee was paid
            'other_phone' => $this->reveal->phoneOfOther($i, $viewer),
            'updated_at' => optional($i->updated_at)->toIso8601String(),
        ];
    }
}
