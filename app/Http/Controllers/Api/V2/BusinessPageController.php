<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\FeedPost;
use App\Models\FollowUser;
use App\Models\Governorate;
use App\Models\Option;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\BusinessHoursService;
use App\Services\Ratings\RatingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The public business page — the single aggregate a customer lands on after
 * tapping a search result. Gathers the profile header, rating, open-now state
 * and which sub-sections exist (posts / menu / services), so the client can
 * render the page and its tabs from one call. The heavy lists (posts, menu,
 * offers) stay on their own paginated endpoints.
 */
final class BusinessPageController extends Controller
{
    public function __construct(
        private readonly RatingService $ratings,
        private readonly BusinessHoursService $hours,
    ) {
    }

    /** GET /api/v2/businesses/{business} */
    public function show(Request $request, int $business)
    {
        /** @var User|null $model */
        $model = User::query()->where('type', 'business')->whereKey($business)->first();

        abort_unless((bool) $model, 404, __('النشاط التجاري غير موجود.'));

        // No expire_at filter here (dropped 2026-09-03, same fix as
        // PostController::business() below) — this count decides whether
        // the Posts tab shows up at all; filtering it the same stale way
        // meant a business whose posts all carried an old expire_at got no
        // Posts tab whatsoever, not just a hidden post.
        $postsCount = FeedPost::query()
            ->where('user_id', $business)
            ->where('is_active', 1)
            ->count();

        $hasMenu = DB::table('menu_items')->where('business_id', $business)->where('is_active', 1)->exists();
        $hasServices = DB::table('business_service_prices')->where('business_id', $business)->where('is_active', 1)->exists();

        // The one entry point (bim_app, above the menu, before checkout)
        // needs to know which methods to even offer. No row yet means the
        // business never opted out of either — both stay available, same as
        // checkout's own fulfillment_type validation always allowed. Dine-in
        // has no settings flag of its own: it's whether the business has any
        // active table (BIM-13.3), the existing signal for that capability.
        $menuSettings = DB::table('business_menu_settings')->where('business_id', $business)->first();
        $hasActiveTables = DB::table('business_tables')
            ->where('business_id', $business)
            ->where('is_active', 1)
            ->exists();

        $viewer = $request->user() ?: auth('sanctum')->user();
        $followersCount = FollowUser::query()->where('follow_id', $business)->count();
        $isFollowing = $viewer !== null && FollowUser::query()
            ->where('user_id', $viewer->id)
            ->where('follow_id', $business)
            ->exists();

        // A street-level address is optional and lives in the same book a
        // customer's own delivery addresses do (Address::user_id) — the
        // business's own is whichever one it flagged primary, same as any
        // other account. No fallback to fabricate one when none is set.
        $address = $model->addresses()->where('is_primary', 1)->first();

        // Every account is Egyptian today (see [[location-and-delivery-address]]
        // convention) but country_id itself is rarely set at signup — default
        // the *display* to Egypt rather than leaving the field blank for
        // almost every business on the platform.
        $countryId = $model->country_id ?: Country::query()->where('iso2', 'EG')->value('id');

        $optionIds = DB::table('option_user')->where('user_id', $business)->pluck('option_id');
        $options = Option::query()->whereIn('id', $optionIds)->get()
            ->map(fn (Option $o) => $this->nameOf($o))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $model->id,
                'name' => $model->displayName(),
                'logo' => $model->logo ?: null,
                'cover' => $model->cover ?: null,
                'image' => $model->image ?: null,
                'about' => $model->about ?: null,
                'phone' => $model->phone ?: null,
                'address' => $address?->address_line,
                'location' => [
                    'latitude' => $model->latitude !== null ? (float) $model->latitude : null,
                    'longitude' => $model->longitude !== null ? (float) $model->longitude : null,
                    // Admin-area names, not just the GPS point — the info
                    // screen shows "which governorate/city", not a map pin.
                    'country' => $this->nameOf($countryId ? Country::find($countryId) : null),
                    'governorate' => $this->nameOf(
                        $model->governorate_id ? Governorate::find($model->governorate_id) : null
                    ),
                    'city' => $this->nameOf($model->city_id ? City::find($model->city_id) : null),
                ],
                'category' => [
                    'id' => $model->category_id !== null ? (int) $model->category_id : null,
                    'name' => $model->category_id ? $this->nameOf($model->category) : null,
                    'child_id' => $model->category_child_id !== null ? (int) $model->category_child_id : null,
                    'child_name' => $model->category_child_id ? $this->nameOf($model->categoryChild) : null,
                ],
                'options' => $options,
                'social' => $this->socialLinks($model),
                'rating' => $this->ratings->summaryFor((int) $model->id, UserOperationRating::ROLE_BUSINESS),
                'open_now' => $this->hours->isOpenNow((int) $model->id),
                'is_following' => $isFollowing,
                'counts' => ['posts' => $postsCount, 'followers' => $followersCount],
                // Which tabs the client should surface for this business.
                'sections' => [
                    'posts' => $postsCount > 0,
                    'menu' => $hasMenu,
                    'services' => $hasServices,
                ],
                // Which fulfillment methods the unified entry point should
                // offer above the menu — see Order::FULFILLMENT_* and
                // CustomerCartService::placeOrder for how the choice is spent.
                'fulfillment' => [
                    'delivery' => $menuSettings ? (bool) $menuSettings->supports_delivery : true,
                    'pickup' => $menuSettings ? (bool) $menuSettings->supports_pickup : true,
                    'dine_in' => $hasActiveTables,
                ],
            ],
        ]);
    }

    /** Null when the business has never set a single link — not an empty object. */
    private function socialLinks(User $model): ?array
    {
        $social = $model->social;

        if (! $social) {
            return null;
        }

        $links = array_filter([
            'facebook' => $social->facebook ?: null,
            'instagram' => $social->instagram ?: null,
            'twitter' => $social->twitter ?: null,
            'youtube' => $social->youtube ?: null,
            'linkedin' => $social->linkedin ?: null,
        ]);

        return $links === [] ? null : $links;
    }

    /** Mirrors AddressResource::nameOf() — id + both languages, not just one. */
    private function nameOf(?Model $relation): ?array
    {
        if (! $relation) {
            return null;
        }

        return [
            'id' => (int) $relation->id,
            'name' => method_exists($relation, 'loc') ? $relation->loc('name') : ($relation->name_ar ?: $relation->name_en),
            'name_ar' => $relation->name_ar,
            'name_en' => $relation->name_en,
        ];
    }
}
