<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\TalentView;
use App\Models\User;
use App\Services\TalentScoutingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * «واذا شاهده اكثر من مرة تحسب مرة واحدة فقط» — owner, 2026-08-18.
 *
 * The scout pays and the player never does. Everything below is one of the
 * four rules that makes that true and keeps it true.
 */
class TalentScoutingTest extends TestCase
{
    use DatabaseTransactions;

    private TalentScoutingService $scouting;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scouting = app(TalentScoutingService::class);
        $this->wallet = app(WalletService::class);

        config(['bim.talent.view_fee' => 5, 'bim.talent.reveal_fee' => 50]);
    }

    private function talent(): Post
    {
        return Post::create([
            'user_id' => User::where('type', 'client')->value('id'),
            'type' => 'talent',
            'title' => 'لاعب وسط ١٦ سنة',
            'sport' => 'كرة قدم',
            'playing_position' => 'وسط',
            'is_active' => 1,
        ]);
    }

    private function scout(float $balance = 500): User
    {
        $scout = User::create([
            'name' => 'أكاديمية كشافة',
            'email' => 'scout' . uniqid() . '@test.local',
            'password' => bcrypt('x'),
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'phone' => '01' . random_int(100000000, 999999999),
            'type' => 'business',
            'category_id' => 7,
            'category_child_id' => (int) config('bim.talent.scout_child_id'),
        ]);

        if ($balance > 0) {
            $this->wallet->deposit($scout->id, $balance, 'test float');
        }

        return $scout;
    }

    private function balance(User $u): float
    {
        return (float) $this->wallet->getOrCreateWallet($u->id)->balance;
    }

    /** The first look costs the view fee; every look after it is free. */
    public function test_a_repeat_view_is_counted_once_and_charged_once(): void
    {
        $talent = $this->talent();
        $scout = $this->scout();

        $before = $this->balance($scout);

        $this->scouting->recordView($talent, $scout);

        $this->assertSame($before - 5, $this->balance($scout), 'the first view did not charge the view fee');

        foreach (range(1, 4) as $_) {
            $this->scouting->recordView($talent, $scout);
        }

        $this->assertSame($before - 5, $this->balance($scout), 'a repeat view was charged again');

        $view = TalentView::where('talent_post_id', $talent->id)->where('scout_id', $scout->id)->firstOrFail();

        $this->assertSame(5, $view->view_count, 'the repeats were not counted');
        $this->assertSame(1, TalentView::where('talent_post_id', $talent->id)->count(), 'one pair, one row');
    }

    /** The boy's wallet is never touched — the whole inversion, in one assertion. */
    public function test_the_player_is_never_charged(): void
    {
        $talent = $this->talent();
        $player = User::findOrFail($talent->user_id);

        $before = $this->balance($player);

        $this->scouting->revealContact($talent, $this->scout());
        $this->scouting->revealContact($talent, $this->scout());

        $this->assertSame($before, $this->balance($player), 'the player paid for being watched');
    }

    /** Asking who he is costs the reveal fee, once, on top of the view. */
    public function test_revealing_contact_charges_once_and_is_traceable(): void
    {
        $talent = $this->talent();
        $scout = $this->scout();

        $before = $this->balance($scout);

        $this->scouting->revealContact($talent, $scout);

        $this->assertSame($before - 55, $this->balance($scout), 'view + reveal should both be charged');

        $this->scouting->revealContact($talent, $scout);

        $this->assertSame($before - 55, $this->balance($scout), 'the reveal was charged twice');

        $view = TalentView::where('talent_post_id', $talent->id)->where('scout_id', $scout->id)->firstOrFail();

        // The row a complaint is answered from: who took a minor's details, when.
        $this->assertNotNull($view->revealed_at);
        $this->assertNotNull($view->reveal_transaction_id);
        $this->assertTrue($this->scouting->hasRevealed($talent, $scout));
    }

    /**
     * The paywall is the boy's protection, so it is not a UI concern.
     *
     * A client, and a business standing on any other child, are both refused —
     * and refused before any money moves.
     */
    public function test_only_a_scout_account_may_open_a_card(): void
    {
        $talent = $this->talent();

        $client = User::where('type', 'client')->firstOrFail();

        $notAScout = User::create([
            'name' => 'ورشة',
            'email' => 'notscout' . uniqid() . '@test.local',
            'password' => bcrypt('x'),
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'phone' => '01' . random_int(100000000, 999999999),
            'type' => 'business',
            'category_id' => 10,
            'category_child_id' => 543,
        ]);

        foreach ([$client, $notAScout] as $intruder) {
            try {
                $this->scouting->recordView($talent, $intruder);
                $this->fail('a non-scout opened a talent card');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('scout', $e->errors());
            }
        }

        $this->assertSame(0, TalentView::where('talent_post_id', $talent->id)->count());
    }

    /** A card is a card: nothing else may be charged for through this door. */
    public function test_an_ordinary_post_is_not_a_talent_card(): void
    {
        $post = Post::create([
            'user_id' => User::where('type', 'client')->value('id'),
            'type' => 'post',
            'title' => 'منشور عادي',
            'is_active' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $this->scouting->recordView($post, $this->scout());
    }

    /** A launch with the fees at zero still records who looked. */
    public function test_a_free_launch_still_records_the_view(): void
    {
        config(['bim.talent.view_fee' => 0, 'bim.talent.reveal_fee' => 0]);

        $talent = $this->talent();
        $scout = $this->scout();
        $before = $this->balance($scout);

        $view = $this->scouting->revealContact($talent, $scout);

        $this->assertSame($before, $this->balance($scout));
        $this->assertNotNull($view->revealed_at);
        $this->assertNull($view->view_transaction_id, 'a zero fee must not write a transaction');
    }

    /** A scout with nothing in the wallet is stopped, and leaves no row behind. */
    public function test_an_empty_wallet_cannot_open_a_card(): void
    {
        $talent = $this->talent();
        $scout = $this->scout(0);

        try {
            $this->scouting->recordView($talent, $scout);
            $this->fail('a scout with no balance opened a card');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('balance', $e->errors());
        }

        $this->assertSame(0, TalentView::where('talent_post_id', $talent->id)->count());
        $this->assertSame(0.0, $this->balance($scout));
    }

    /**
     * «اخفى الاسم والنادى والفيديو قبل الدفع» — through the API, end to end.
     *
     * The three hidden fields are the three that make him findable off the
     * platform. What is left is enough to decide whether to pay and useless for
     * anything else.
     */
    public function test_the_api_hides_the_name_club_and_video_until_paid(): void
    {
        $talent = $this->talent();
        $talent->forceFill([
            'current_club' => 'نادي الاتحاد',
            'video_url' => 'https://example.test/clip.mp4',
            'birth_date' => now()->subYears(16),
        ])->save();

        $scout = $this->scout();

        $open = $this->actingAs($scout, 'sanctum')->getJson("/api/v2/talents/{$talent->id}");
        $open->assertOk();

        foreach (['name', 'current_club', 'video_url', 'contact'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $open->json('data'), "«{$hidden}» leaked before payment");
        }

        $open->assertJsonPath('data.revealed', false);
        $open->assertJsonPath('data.sport', 'كرة قدم');
        $open->assertJsonPath('data.age', 16);
        $this->assertContains('video_url', $open->json('data.locked_fields'));

        $paid = $this->actingAs($scout, 'sanctum')->postJson("/api/v2/talents/{$talent->id}/reveal");
        $paid->assertOk()
            ->assertJsonPath('data.revealed', true)
            ->assertJsonPath('data.current_club', 'نادي الاتحاد')
            ->assertJsonPath('data.video_url', 'https://example.test/clip.mp4');

        $this->assertNotNull($paid->json('data.contact.phone'));

        // …and it stays revealed without paying again.
        $again = $this->actingAs($scout, 'sanctum')->getJson("/api/v2/talents/{$talent->id}");
        $again->assertJsonPath('data.revealed', true);
    }

    /** The browsable grid is free, and locked for everyone. */
    public function test_the_list_never_charges_and_never_reveals(): void
    {
        $talent = $this->talent();
        $talent->forceFill(['video_url' => 'https://example.test/x.mp4'])->save();

        $scout = $this->scout();
        $before = $this->balance($scout);

        $list = $this->actingAs($scout, 'sanctum')->getJson('/api/v2/talents?sport=' . urlencode('كرة قدم'));
        $list->assertOk();

        $this->assertSame($before, $this->balance($scout), 'browsing the grid charged the scout');

        foreach ($list->json('data') as $card) {
            $this->assertArrayNotHasKey('video_url', $card);
            $this->assertFalse($card['revealed']);
        }
    }

    /** The «once» rule is the database's, not the code's. */
    public function test_the_pair_is_unique_in_the_schema(): void
    {
        $talent = $this->talent();
        $scout = $this->scout();

        $this->scouting->recordView($talent, $scout);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('talent_views')->insert([
            'talent_post_id' => $talent->id,
            'scout_id' => $scout->id,
            'first_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
