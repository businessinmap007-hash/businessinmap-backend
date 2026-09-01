<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\FollowUser;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\BusinessHoursService;
use App\Services\Ratings\RatingService;
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

        $postsCount = FeedPost::query()
            ->where('user_id', $business)
            ->where('is_active', 1)
            ->where(fn ($w) => $w->whereNull('expire_at')->orWhere('expire_at', '>=', now()))
            ->count();

        $hasMenu = DB::table('menu_items')->where('business_id', $business)->where('is_active', 1)->exists();
        $hasServices = DB::table('business_service_prices')->where('business_id', $business)->where('is_active', 1)->exists();

        $viewer = $request->user() ?: auth('sanctum')->user();
        $followersCount = FollowUser::query()->where('follow_id', $business)->count();
        $isFollowing = $viewer !== null && FollowUser::query()
            ->where('user_id', $viewer->id)
            ->where('follow_id', $business)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $model->id,
                'name' => (string) $model->name,
                'logo' => $model->logo ?: null,
                'cover' => $model->cover ?: null,
                'image' => $model->image ?: null,
                'about' => $model->about ?: null,
                'phone' => $model->phone ?: null,
                'location' => [
                    'latitude' => $model->latitude !== null ? (float) $model->latitude : null,
                    'longitude' => $model->longitude !== null ? (float) $model->longitude : null,
                ],
                'category' => [
                    'id' => $model->category_id !== null ? (int) $model->category_id : null,
                    'child_id' => $model->category_child_id !== null ? (int) $model->category_child_id : null,
                ],
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
            ],
        ]);
    }
}
