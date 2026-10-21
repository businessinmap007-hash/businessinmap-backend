<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The app's search box: businesses by name, public, names only. */
class BusinessSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function business(string $name, ?string $nameEn = null): User
    {
        return User::create([
            'name' => $name, 'name_en' => $nameEn, 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => User::TYPE_BUSINESS, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => 24, 'category_child_id' => 536,
        ]);
    }

    public function test_a_business_is_found_by_its_name_in_either_language_without_signing_in(): void
    {
        $tag = 'Qzx' . substr(md5(uniqid()), 0, 6);
        $b = $this->business('نيل ' . $tag, 'Nile ' . $tag);

        $byEnglish = $this->getJson('/api/v2/search/businesses?q=' . urlencode('Nile ' . $tag))->assertOk()->json('data.businesses');
        $this->assertSame([$b->id], array_column($byEnglish, 'id'));
        $this->assertSame(536, $byEnglish[0]['category_child_id']);

        $byArabic = $this->getJson('/api/v2/search/businesses?q=' . urlencode('نيل ' . $tag))->assertOk()->json('data.businesses');
        $this->assertSame([$b->id], array_column($byArabic, 'id'));
    }

    public function test_it_matches_names_only_never_an_email_and_a_short_query_is_empty(): void
    {
        $b = $this->business('Plain ' . uniqid());

        $this->assertSame([], $this->getJson('/api/v2/search/businesses?q=' . urlencode($b->email))->assertOk()->json('data.businesses'), 'an e-mail must not confirm an account');
        $this->assertSame([], $this->getJson('/api/v2/search/businesses?q=a')->assertOk()->json('data.businesses'));
        $this->assertSame([], $this->getJson('/api/v2/search/businesses')->assertOk()->json('data.businesses'));
    }
}
