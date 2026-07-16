<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\WorldCountriesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The countries table backs the international shipping picker, so the list is
 * held to ISO 3166-1's officially assigned codes — no more (invented places),
 * no fewer (a destination the carrier cannot name). Rolls back.
 */
class WorldCountriesSeederTest extends TestCase
{
    use DatabaseTransactions;

    /** Every officially assigned ISO 3166-1 alpha-2 code (249). */
    private const OFFICIAL = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /** @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string}> */
    private function list(): array
    {
        $method = new ReflectionMethod(WorldCountriesSeeder::class, 'countries');
        $method->setAccessible(true);

        return $method->invoke(new WorldCountriesSeeder());
    }

    public function test_list_is_exactly_iso_3166_1_officially_assigned(): void
    {
        $official = preg_split('/\s+/', trim(self::OFFICIAL));
        $codes = array_column($this->list(), 2);

        $this->assertSame([], array_values(array_diff($official, $codes)), 'countries missing from the seeder');
        $this->assertSame([], array_values(array_diff($codes, $official)), 'codes that are not officially assigned');
        $this->assertCount(249, $codes);
    }

    public function test_codes_are_unique_and_well_formed(): void
    {
        $list = $this->list();

        $this->assertSame(array_unique(array_column($list, 2)), array_column($list, 2), 'duplicate iso2');
        $this->assertSame(array_unique(array_column($list, 3)), array_column($list, 3), 'duplicate iso3');

        foreach ($list as [$ar, $en, $iso2, $iso3, $phone, $currency]) {
            $this->assertNotSame('', trim($ar), "{$iso2} has no Arabic name");
            $this->assertNotSame('', trim($en), "{$iso2} has no English name");
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $iso3, "{$iso2} has a malformed iso3");
            $this->assertMatchesRegularExpression('/^[0-9]{1,4}$/', $phone, "{$iso2} has a malformed phone code");
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $currency, "{$iso2} has a malformed currency");
        }
    }

    public function test_seeder_is_rerunnable_and_derives_flags(): void
    {
        (new WorldCountriesSeeder())->run();
        (new WorldCountriesSeeder())->run();

        $this->assertSame(1, Country::query()->where('iso2', 'EG')->count(), 'Egypt must be updated in place, never duplicated');
        $this->assertSame(249, Country::query()->count());
        $this->assertSame(0, Country::query()->whereNull('flag')->orWhere('flag', '')->count());

        // The flag is derived from the code, so it cannot drift from the row.
        $this->assertSame('🇪🇬', Country::query()->where('iso2', 'EG')->value('flag'));
        $this->assertSame('🇯🇵', Country::query()->where('iso2', 'JP')->value('flag'));
    }
}
