<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\FollowUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Follow/unfollow another account (almost always a business). `follow_user`
 * is the same table PostAudienceService reads to build the personal feed's
 * audience — following someone here is what makes their posts start showing
 * up in GET /api/v2/posts. See FollowUser for why this endpoint didn't exist
 * until now even though the table did.
 */
final class FollowController extends Controller
{
    /** GET /api/v2/follows — accounts I follow. */
    public function index(Request $request)
    {
        $followIds = FollowUser::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->pluck('follow_id');

        $users = User::query()
            ->whereIn('id', $followIds)
            ->get(['id', 'name', 'name_en', 'type', 'logo', 'image', 'category_id', 'category_child_id'])
            ->keyBy('id');

        // Preserve follow order (most recently followed first), not id order.
        $data = $followIds
            ->map(fn ($id) => $users->get($id))
            ->filter()
            ->map(fn (User $u) => $this->shape($u))
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/v2/follows — follow an account. Idempotent. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'follow_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $followId = (int) $data['follow_id'];
        $userId = (int) $request->user()->id;

        if ($followId === $userId) {
            abort(422, __('لا يمكنك متابعة حسابك الخاص.'));
        }

        $follow = FollowUser::query()->firstOrCreate([
            'user_id' => $userId,
            'follow_id' => $followId,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $follow->id, 'follow_id' => $followId],
        ], 201);
    }

    /** DELETE /api/v2/follows/{user} — unfollow. Idempotent. */
    public function destroy(Request $request, int $user)
    {
        FollowUser::query()
            ->where('user_id', $request->user()->id)
            ->where('follow_id', $user)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function shape(User $u): array
    {
        return [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'type' => $u->type,
            'logo' => $u->logo ?: null,
            'image' => $u->image ?: null,
            'category_id' => $u->category_id !== null ? (int) $u->category_id : null,
            'category_child_id' => $u->category_child_id !== null ? (int) $u->category_child_id : null,
        ];
    }
}
