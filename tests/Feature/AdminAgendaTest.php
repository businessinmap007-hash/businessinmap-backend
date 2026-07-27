<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The AdminV2 read-only oversight of users' personal agendas. Gated OPERATIONS;
 * lists every agenda item across users, filterable by user/kind/status/date.
 */
class AdminAgendaTest extends TestCase
{
    use DatabaseTransactions;

    private function client(string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function supervisor(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::OPERATIONS] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    private function item(User $user, string $title): AgendaItem
    {
        return AgendaItem::create([
            'user_id' => $user->id, 'kind' => AgendaItem::KIND_PERSONAL, 'title' => $title,
            'starts_at' => Carbon::tomorrow()->setTime(9, 0), 'ends_at' => Carbon::tomorrow()->setTime(9, 30),
            'blocking' => true, 'status' => AgendaItem::STATUS_ACTIVE,
        ]);
    }

    public function test_a_supervisor_lists_agenda_items(): void
    {
        $user = $this->client('User');
        $item = $this->item($user, 'ReviewMe ' . Str::random(4));

        $this->actingAs($this->supervisor())->get('/admin/agenda')
            ->assertOk()
            ->assertSee($item->title)
            ->assertSee($user->name);
    }

    public function test_the_search_filter_narrows_the_list(): void
    {
        $keep = $this->item($this->client('Keep'), 'KeepThis ' . Str::random(4));
        $this->item($this->client('Drop'), 'DropThat ' . Str::random(4));

        $this->actingAs($this->supervisor())
            ->get('/admin/agenda?q=' . urlencode($keep->title))
            ->assertOk()
            ->assertSee($keep->title)
            ->assertDontSee('DropThat');
    }

    public function test_an_admin_without_operations_is_forbidden(): void
    {
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plainag-' . uniqid() . '@example.test';
        $plain->phone = '0158' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($plain)->get('/admin/agenda')->assertForbidden();
    }
}
