<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The ban list.
 *
 * A ban is only as strong as its normalization: every test here is a way
 * somebody would try to walk around it. Rolls back.
 */
class BlockedIdentityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_arabic_digits_are_the_same_number_as_latin_ones(): void
    {
        // Not hypothetical: real rows in this database store phones as
        // ٠١٠١٤٤١٩٧٨٨. A ban that only understands 0-9 hashes an empty string
        // for those users and stops nobody.
        $this->assertSame('01014419788', BlockedIdentity::normalizePhone('٠١٠١٤٤١٩٧٨٨'));
        $this->assertSame(
            BlockedIdentity::hashPhone('01014419788'),
            BlockedIdentity::hashPhone('٠١٠١٤٤١٩٧٨٨')
        );
    }

    public function test_the_international_spellings_of_a_number_are_one_identity(): void
    {
        $local = BlockedIdentity::normalizePhone('01001234567');

        foreach (['+201001234567', '00201001234567', '201001234567', '+20 100 123 4567', '0100-123-4567'] as $variant) {
            $this->assertSame($local, BlockedIdentity::normalizePhone($variant), $variant . ' must be the same identity');
        }
    }

    public function test_email_case_and_spacing_are_not_identity(): void
    {
        $this->assertSame(
            BlockedIdentity::hashEmail('Fraud@Example.COM'),
            BlockedIdentity::hashEmail('  fraud@example.com  ')
        );
    }

    public function test_the_list_stores_no_readable_identity(): void
    {
        $user = $this->makeUser();
        BlockedIdentity::blockUser($user, 'fraud', BlockedIdentity::SOURCE_FRAUD);

        $row = BlockedIdentity::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($row);
        // Hashed and keyed: the table must never be readable as a contact list.
        $this->assertNotSame($user->email, $row->email_hash);
        $this->assertNotSame($user->phone, $row->phone_hash);
        $this->assertSame(64, strlen($row->email_hash));
        $this->assertNotSame(hash('sha256', $user->email), $row->email_hash, 'a bare hash of an email is trivially reversed — this must be keyed');
    }

    public function test_either_identity_alone_is_enough_to_be_recognised(): void
    {
        $user = $this->makeUser();
        BlockedIdentity::blockUser($user);

        $this->assertTrue(BlockedIdentity::isBlocked($user->email, null), 'the email alone');
        $this->assertTrue(BlockedIdentity::isBlocked(null, $user->phone), 'the phone alone — a new email does not help');
        $this->assertTrue(BlockedIdentity::isBlocked('someone-else@example.test', $user->phone));
        $this->assertFalse(BlockedIdentity::isBlocked('nobody@example.test', '01199999999'));
    }

    public function test_blank_input_matches_nobody(): void
    {
        BlockedIdentity::blockUser($this->makeUser());

        // A NULL hash must never behave like a wildcard.
        $this->assertFalse(BlockedIdentity::isBlocked(null, null));
        $this->assertFalse(BlockedIdentity::isBlocked('', ''));
    }

    public function test_banning_the_same_identity_twice_updates_rather_than_duplicates(): void
    {
        $user = $this->makeUser();

        BlockedIdentity::blockUser($user, 'first reason');
        BlockedIdentity::blockUser($user, 'second reason', BlockedIdentity::SOURCE_FRAUD);

        $rows = BlockedIdentity::query()->where('user_id', $user->id)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('second reason', $rows->first()->reason);
        $this->assertSame(BlockedIdentity::SOURCE_FRAUD, $rows->first()->source);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->name = 'Ban Test';
        $user->email = 'ban-' . uniqid() . '@example.test';
        $user->phone = '0111' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_CLIENT;
        $user->api_token = Str::random(80);
        $user->save();

        return $user;
    }
}
