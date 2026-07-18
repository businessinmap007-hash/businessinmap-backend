<?php

namespace App\Services\Posts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who a user's post feed is allowed to draw from.
 *
 * This restores v1's intent. `getTargetsAndFollowersBusiness()` in
 * app/Http/Helpers/General.php composed the audience from four `User`
 * relations — followers(), categoryFollows(), targetsReverse() — **none of
 * which exist on App\Models\User**, so the helper throws
 * BadMethodCallException. v1's authenticated feed (`GET /api/v1/get/posts`)
 * has therefore been returning a 500 for every signed-in user; verified
 * against the running server, it dies even earlier, on `User::byToken()`.
 *
 * The four pivot tables are real and populated, so the rule is reconstructed
 * against them directly rather than through model relations:
 *
 *   category_target (3153)  a business chose to target a whole category
 *   follow_user     (3209)  accounts I follow
 *   category_user           categories I follow -> everyone in them
 *   target_user     (703)   accounts that targeted me specifically
 *
 * Kept faithful to v1 on purpose: the feed is targeted/followed content only,
 * and never your own posts. A brand-new account with no category and no
 * follows therefore sees an empty feed — that is v1's behaviour, and widening
 * it is a product decision, not a porting one.
 */
final class PostAudienceService
{
    /**
     * Author ids whose posts $user may see in the feed, excluding themselves.
     *
     * @return list<int>
     */
    public function authorIdsFor(User $user): array
    {
        $ids = collect();

        // 1. Businesses that target the category this user belongs to.
        if ($user->category_id !== null) {
            $ids = $ids->merge(
                DB::table('category_target')
                    ->where('category_id', $user->category_id)
                    ->pluck('user_id')
            );
        }

        // 2. Accounts this user follows.
        $ids = $ids->merge(
            DB::table('follow_user')->where('user_id', $user->id)->pluck('follow_id')
        );

        // 3. Everyone sitting in a category this user follows.
        $followedCategoryIds = DB::table('category_user')
            ->where('user_id', $user->id)
            ->pluck('category_id');

        if ($followedCategoryIds->isNotEmpty()) {
            $ids = $ids->merge(
                DB::table('users')->whereIn('category_id', $followedCategoryIds)->pluck('id')
            );
        }

        // 4. Accounts that targeted this user directly.
        $ids = $ids->merge(
            DB::table('target_user')->where('target_id', $user->id)->pluck('user_id')
        );

        return $ids
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $user->id)
            ->unique()
            ->values()
            ->all();
    }
}
