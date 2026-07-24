<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Ratings\RatingService;
use App\Services\ServiceFeeConsentEnforcer;
use Illuminate\Http\Request;

/**
 * Operation-based rating (objective reputation) read API: a user's success /
 * cancel / dispute percentages, derived from their recorded operation outcomes.
 */
final class RatingController extends Controller
{
    public function __construct(
        private readonly RatingService $ratings,
        private readonly ServiceFeeConsentEnforcer $feeConsent,
    ) {
    }

    /** GET /api/v2/ratings/me — the authenticated user's rating in their own role. */
    public function me(Request $request)
    {
        $user = $request->user();
        $role = $this->roleFor($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $user->id,
                'rating' => $this->ratings->summaryFor((int) $user->id, $role),
                // Whether THIS party has opened their rating (and therefore accepted
                // service fees on their own operations). See enable() below.
                'rating_enabled' => $user->hasRatingEnabled(),
                'fee_auto_charge_enabled' => $user->hasFeeAutoChargeEnabled(),
            ],
        ]);
    }

    /**
     * POST /api/v2/ratings/enable — the caller opens their OWN rating.
     *
     * Rating is the premium, opt-in surface: transacting (listing a product,
     * buying one) is free, and no service fee is charged to anyone who has not
     * opened their rating. Opening it is per-party and self-service — a business
     * opening its rating makes only the BUSINESS liable for fees; a client
     * opening theirs makes only the CLIENT liable — because fees are charged per
     * user via WalletFeeService::canAutoChargeFees($thatUser).
     *
     * Forward-only (ServiceFeeConsentEnforcer never auto-disables), so a party
     * cannot take the trust/visibility benefit of an open rating and then close
     * it to dodge the fees.
     */
    public function enable(Request $request)
    {
        $user = $request->user();
        $reason = 'فتح التقييم (' . ($user->isBusiness() ? 'بزنس' : 'عميل') . ')';

        $this->feeConsent->enforce($user, $reason);

        return response()->json([
            'success' => true,
            'message' => __('تم فتح التقييم. ستُطبَّق رسوم الخدمة على عملياتك من الآن.'),
            'data' => [
                'user_id' => (int) $user->id,
                'rating_enabled' => true,
                'fee_auto_charge_enabled' => true,
            ],
        ]);
    }

    /** GET /api/v2/ratings/user/{user} — a user's public rating in their role. */
    public function show(Request $request, int $user)
    {
        $target = User::query()->find($user);

        if (! $target) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $target->id,
                'name' => $target->name,
                'type' => $target->type,
                'rating' => $this->ratings->summaryFor((int) $target->id, $this->roleFor($target)),
            ],
        ]);
    }

    /** GET /api/v2/ratings/user/{user}/reviews — star reviews a user received. */
    public function reviews(Request $request, int $user)
    {
        $target = User::query()->find($user);

        if (! $target) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $perPage = min(max((int) $request->get('per_page', 20), 1), 50);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $target->id,
                'reviews' => $this->ratings->reviewsFor((int) $target->id, $this->roleFor($target), $perPage),
            ],
        ]);
    }

    /**
     * POST /api/v2/ratings/review — submit (or update) a star review. Only allowed
     * for a real, completed operation the caller took part in.
     */
    public function review(Request $request)
    {
        $data = $request->validate([
            'operation_type' => ['required', 'string', 'in:booking,order'],
            'operation_id' => ['required', 'integer', 'min:1'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->ratings->submitReview(
            rater: $request->user(),
            operationType: (string) $data['operation_type'],
            operationId: (int) $data['operation_id'],
            stars: (int) $data['stars'],
            comment: $data['comment'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ التقييم.'),
            'data' => ['review' => $result['review']],
        ], 201);
    }

    private function roleFor(User $user): string
    {
        return $user->isBusiness()
            ? UserOperationRating::ROLE_BUSINESS
            : UserOperationRating::ROLE_CLIENT;
    }
}
