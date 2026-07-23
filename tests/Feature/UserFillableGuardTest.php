<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The privileged User columns (wallet balance + the trust/fee-consent flags)
 * must be impossible to set through a mass-assigned create()/update(): they are
 * out of $fillable on purpose. Their legitimate writers use forceFill() or
 * direct assignment, which this also confirms still works.
 */
class UserFillableGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mass_assignment_cannot_set_privileged_columns(): void
    {
        $user = User::create([
            'name' => 'Guard Test',
            'email' => 'guard-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'secret-password',
            'type' => User::TYPE_CLIENT,
            'api_token' => Str::random(80),
            // All of these must be ignored — they are not fillable.
            'balance' => 999999,
            'guarantee_enabled' => true,
            'rating_enabled' => true,
            'commercial_operations_enabled' => true,
        ]);

        $fresh = $user->fresh();
        $this->assertSame('0.00', (string) $fresh->balance);
        $this->assertFalse((bool) $fresh->guarantee_enabled);
        $this->assertFalse((bool) $fresh->rating_enabled);
        $this->assertFalse((bool) $fresh->commercial_operations_enabled);

        // update() is mass-assignment too — equally ignored.
        $user->update(['balance' => 500, 'commercial_operations_enabled' => true]);
        $fresh = $user->fresh();
        $this->assertSame('0.00', (string) $fresh->balance);
        $this->assertFalse((bool) $fresh->commercial_operations_enabled);
    }

    public function test_force_fill_still_writes_privileged_columns(): void
    {
        $user = User::create([
            'name' => 'Force Test',
            'email' => 'force-' . uniqid() . '@example.test',
            'phone' => '0100' . random_int(1000000, 9999999),
            'password' => 'secret-password',
            'type' => User::TYPE_CLIENT,
            'api_token' => Str::random(80),
        ]);

        // This is how the guarantee services legitimately set the flags.
        $user->forceFill([
            'guarantee_enabled' => true,
            'rating_enabled' => true,
            'commercial_operations_enabled' => true,
        ])->save();

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->guarantee_enabled);
        $this->assertTrue((bool) $fresh->rating_enabled);
        $this->assertTrue((bool) $fresh->commercial_operations_enabled);
    }
}
