<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A goods business ticks its catalog's own line options via
 * BusinessMenuItemController::updateAvailableTypes() — the same
 * `option_user` table this screen's `selected_ids` read from without
 * excluding them. Echoing the FULL list straight back on save (what the
 * mobile client does) then hit updateOptions()'s own line-id rejection on
 * every goods business with anything in its catalog. Fixed 2026-09-16.
 */
class ProfileOptionsLineExclusionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_selected_ids_excludes_a_line_tick_and_the_round_trip_saves_clean(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'خضار وفاكهة')->value('id');

        $business = new User();
        $business->name = 'Greengrocer '.Str::random(4);
        $business->email = 'shop-'.uniqid().'@example.test';
        $business->phone = '01'.random_int(100000000, 999999999);
        $business->password = 'secret-password';
        $business->type = User::TYPE_BUSINESS;
        $business->category_id = 17;
        $business->category_child_id = $childId;
        $business->api_token = Str::random(80);
        $business->save();

        // A line option (a catalog item, e.g. طماطم) ticked the way
        // BusinessMenuItemController::updateAvailableTypes() ticks one —
        // and a real, ordinary descriptive/modifier tick alongside it.
        $lineOption = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', 'line')
            ->value('o.id');

        $descriptiveOption = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_child_option as cco', 'cco.option_id', '=', 'o.id')
            ->where('cco.child_id', $childId)
            ->where('g.price_role', '!=', 'line')
            ->value('o.id');

        DB::table('option_user')->insert([
            ['user_id' => $business->id, 'option_id' => $lineOption],
            ['user_id' => $business->id, 'option_id' => $descriptiveOption],
        ]);

        $payload = $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/profile/options')
            ->assertOk()
            ->json('data');

        $this->assertNotContains($lineOption, $payload['selected_ids'], 'a line tick leaked into this screen\'s selection');
        $this->assertContains($descriptiveOption, $payload['selected_ids']);

        // The mobile client's actual failure mode: echo selected_ids straight
        // back on save. Must succeed now that it never carries a line id.
        $this->actingAs($business, 'sanctum')
            ->patchJson('/api/v2/profile/options', ['option_ids' => $payload['selected_ids']])
            ->assertOk();

        // And the line tick itself must survive untouched — this screen
        // never touches BusinessMenuItemController's half of option_user.
        $this->assertDatabaseHas('option_user', ['user_id' => $business->id, 'option_id' => $lineOption]);
    }
}
