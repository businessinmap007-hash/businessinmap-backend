<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\TalentCardResource;
use App\Models\TalentPost;
use App\Models\TalentView;
use App\Services\TalentScoutingService;
use Illuminate\Http\Request;

/**
 * The scout's side of the talent feature.
 *
 * Owner, 2026-08-18: «اخفى الاسم والنادى والفيديو قبل الدفع».
 *
 * Three endpoints and one rule between them: the card is browsable, the
 * identity is not. Browsing costs the view fee once per boy; the name, the club
 * and the video cost the reveal fee, once, and only a «مستكشف لاعبين» account
 * may do either — see TalentScoutingService, which enforces both the gate and
 * the charge so no second caller can walk around them.
 *
 * `index` deliberately does NOT charge. A scout scrolling a list of forty cards
 * must not be debited forty times for looking at a grid — the fee belongs to
 * opening one boy's card, which is `show`.
 */
class TalentController extends Controller
{
    public function __construct(private readonly TalentScoutingService $scouting)
    {
    }

    /**
     * GET /api/v2/talents — the browsable list, always locked.
     *
     * Free, and returns no name, club or video for anybody: the list is the
     * shop window and the identity is the product.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'sport' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'age_min' => ['nullable', 'integer', 'min:5', 'max:60'],
            'age_max' => ['nullable', 'integer', 'min:5', 'max:60'],
        ]);

        $cards = TalentPost::query()
            ->where('is_active', true)
            ->when($data['sport'] ?? null, fn ($q, $s) => $q->where('sport', $s))
            ->when($data['position'] ?? null, fn ($q, $p) => $q->where('playing_position', $p))
            // Older birth date = older player, so the bounds invert.
            ->when($data['age_min'] ?? null, fn ($q, $a) => $q->whereDate('birth_date', '<=', now()->subYears($a)))
            ->when($data['age_max'] ?? null, fn ($q, $a) => $q->whereDate('birth_date', '>=', now()->subYears($a + 1)))
            ->latest()
            ->paginate(20);

        return TalentCardResource::collection($cards);
    }

    /**
     * GET /api/v2/talents/{talent} — open one card.
     *
     * This is the charged moment, once per (boy, scout). A scout who has
     * already paid to reveal sees the full card here without paying again.
     */
    public function show(Request $request, TalentPost $talent)
    {
        $view = $this->scouting->recordView($talent, $request->user());

        return response()->json([
            'success' => true,
            'data' => new TalentCardResource($talent->load('user'), $view->isRevealed()),
            'meta' => [
                'view_count' => $view->view_count,
                'charged' => (float) $view->view_fee,
            ],
        ]);
    }

    /**
     * POST /api/v2/talents/{talent}/reveal — pay to see who he is.
     *
     * Idempotent by design: a scout who has already revealed gets the card back
     * and is not charged twice.
     */
    public function reveal(Request $request, TalentPost $talent)
    {
        $view = $this->scouting->revealContact($talent, $request->user());

        return response()->json([
            'success' => true,
            'data' => new TalentCardResource($talent->load('user'), true),
            'meta' => [
                'revealed_at' => $view->revealed_at,
                'charged' => (float) $view->reveal_fee,
            ],
        ]);
    }

    /**
     * GET /api/v2/talents/mine/views — what this scout has already paid for.
     *
     * So he can see a boy again without wondering whether it will cost him,
     * which is the question the «counted once» rule exists to answer.
     */
    public function myViews(Request $request)
    {
        $views = TalentView::where('scout_id', $request->user()->id)
            ->with('talent')
            ->latest('first_seen_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $views->getCollection()->map(fn (TalentView $v) => [
                'talent' => $v->talent ? new TalentCardResource($v->talent, $v->isRevealed()) : null,
                'first_seen_at' => $v->first_seen_at,
                'view_count' => $v->view_count,
                'revealed_at' => $v->revealed_at,
                'paid' => (float) $v->view_fee + (float) $v->reveal_fee,
            ]),
            'meta' => [
                'current_page' => $views->currentPage(),
                'last_page' => $views->lastPage(),
                'total' => $views->total(),
            ],
        ]);
    }
}
