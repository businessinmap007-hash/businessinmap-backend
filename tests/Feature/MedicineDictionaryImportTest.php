<?php

namespace Tests\Feature;

use App\Console\Commands\DeriveMedicineStrength;
use App\Models\Medicine;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «هل يمكن اضافة جدول ونضيف به جميع الادوية الموجودة فى مصر».
 *
 * The dictionary was built to grow by itself — every drug a doctor writes is
 * remembered — which is right, and leaves the FIRST doctor typing into an empty
 * box. This fills it from a real register.
 *
 * **Nothing invents a drug.** A wrong name or strength in a list a doctor picks
 * from is not a cosmetic bug, so the importer only ever writes what the file
 * says, and these tests exist to prove exactly that.
 */
class MedicineDictionaryImportTest extends TestCase
{
    use DatabaseTransactions;

    private function file(string $name, string $body): string
    {
        $path = storage_path('app/' . $name);
        file_put_contents($path, $body);

        return $path;
    }

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::CONTENT);
        Bouncer::refresh();

        return $admin;
    }

    public function test_a_csv_register_fills_the_dictionary(): void
    {
        $path = $this->file('meds-test.csv', "Trade Name,Strength\nZzTestDrugA,500mg\nZzTestDrugB,1g\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertDatabaseHas('medicines', ['name' => 'ZzTestDrugA', 'strength' => '500mg']);
        $this->assertDatabaseHas('medicines', ['name' => 'ZzTestDrugB', 'strength' => '1g']);
    }

    /**
     * «Trade Name», «trade_name», «TRADE-NAME» are one column.
     *
     * Matching the header literally is why the first real file reported «no
     * name column» and imported nothing: every register spells it differently.
     */
    public function test_the_header_is_matched_however_the_register_spells_it(): void
    {
        foreach (['Trade Name', 'trade_name', 'DRUG', 'الدواء'] as $i => $header) {
            $name = 'ZzHeader' . $i;
            $path = $this->file('meds-header.csv', "{$header},Strength\n{$name},10mg\n");

            Artisan::call('medicines:import', ['file' => $path]);
            @unlink($path);

            $this->assertDatabaseHas('medicines', ['name' => $name], null);
        }
    }

    public function test_re_running_the_same_file_adds_nothing(): void
    {
        $path = $this->file('meds-twice.csv', "name,strength\nZzTwice,20mg\n");

        Artisan::call('medicines:import', ['file' => $path]);
        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertSame(1, Medicine::query()->where('name', 'ZzTwice')->count());
    }

    /** A register listing one drug per pack size must not fight itself. */
    public function test_duplicates_inside_one_file_collapse(): void
    {
        $path = $this->file('meds-dup.csv', "name,strength\nZzDup,5mg\nZzDup,5mg\nZzDup,5mg\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertSame(1, Medicine::query()->where('name', 'ZzDup')->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $path = $this->file('meds-dry.csv', "name\nZzNeverWritten\n");

        Artisan::call('medicines:import', ['file' => $path, '--dry-run' => true]);
        @unlink($path);

        $this->assertSame(0, Medicine::query()->where('name', 'ZzNeverWritten')->count());
    }

    public function test_a_file_with_no_name_column_is_refused(): void
    {
        $path = $this->file('meds-bad.csv', "colour,size\nred,large\n");

        $code = Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertNotSame(0, $code, 'a file the importer cannot read must fail loudly, not silently');
    }

    /**
     * An imported row starts at zero uses, so the typeahead's «most-used first»
     * order still surfaces what doctors actually reach for above the twelve
     * thousand rows nobody has written yet.
     */
    public function test_an_imported_drug_does_not_pretend_to_have_been_prescribed(): void
    {
        $path = $this->file('meds-uses.csv', "name\nZzFresh\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertSame(0, (int) Medicine::query()->where('name', 'ZzFresh')->value('uses_count'));
    }

    public function test_the_admin_screen_uploads_a_register(): void
    {
        $upload = UploadedFile::fake()->createWithContent('register.csv', "name,strength\nZzUploaded,250mg\n");

        $this->actingAs($this->admin())
            ->post(route('admin.medicines.import'), ['file' => $upload])
            ->assertRedirect();

        $this->assertDatabaseHas('medicines', ['name' => 'ZzUploaded', 'strength' => '250mg']);
    }

    public function test_the_screen_opens(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.medicines.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mdTry', $html, 'the try-it box is on the page');

        // The AdminV2 AJAX landmine: an absolute URL built from APP_URL points
        // at a host the panel may not be served from, and the fetch dies on the
        // cross-origin check with nothing on screen to say why.
        //
        // Matched against the ESCAPED form @json emits ("\/admin\/…"), not the
        // plain path — the first version of this assertion failed on that alone.
        $this->assertStringContainsString(
            'const ENDPOINT = ' . json_encode(route('admin.medicines.search', [], false)),
            $html,
            'the endpoint must be relative'
        );
        $this->assertStringNotContainsString(
            json_encode(route('admin.medicines.search')),
            $html,
            'an absolute endpoint would break the preview off-host'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | «اعطنى فيو ارى منهم الدواء واجرب البحث»
    |--------------------------------------------------------------------------
    | The preview must be the doctor's own search, not a demonstration of one.
    */
    public function test_the_preview_and_the_app_search_are_the_same_search(): void
    {
        Medicine::create(['name' => 'ZzAlphaOne 500 MG', 'strength' => null, 'uses_count' => 0]);
        Medicine::create(['name' => 'ZzAlphaTwo 500 MG', 'strength' => null, 'uses_count' => 9]);

        $preview = $this->actingAs($this->admin())
            ->getJson(route('admin.medicines.search', ['q' => 'ZzAlpha']))
            ->assertOk()
            ->json('data');

        $direct = Medicine::query()->search('ZzAlpha')->limit(20)->pluck('name')->all();

        $this->assertSame(
            $direct,
            array_column($preview, 'name'),
            'the preview ran a different query from the one the doctor is given'
        );
    }

    /** A name that STARTS with what was typed outranks one that merely holds it. */
    public function test_a_prefix_match_outranks_a_middle_match(): void
    {
        Medicine::create(['name' => 'ZzMid CONTAINSZZKEY 10 MG', 'strength' => null, 'uses_count' => 40]);
        Medicine::create(['name' => 'ZZKEY PLAIN 10 MG', 'strength' => null, 'uses_count' => 0]);

        $first = Medicine::query()->search('ZZKEY')->first();

        $this->assertSame(
            'ZZKEY PLAIN 10 MG',
            $first->name,
            'the prefix must win even against a far more prescribed middle match'
        );
    }

    /**
     * The register writes the dose into the name — «AUGMENTIN 1 GM 14 F.C.TABS.»
     * — so a doctor reaching for a strength types from the middle of it. Prefix
     * only was the original rule and it hid most of a 25,000-row register.
     */
    public function test_a_doctor_can_search_by_the_dose_inside_the_name(): void
    {
        Medicine::create(['name' => 'ZzDoseDrug 250 MG 20 TABS.', 'strength' => null, 'uses_count' => 0]);

        $this->assertSame(
            1,
            Medicine::query()->search('250 MG 20 TABS')->where('name', 'like', 'ZzDose%')->count()
        );
    }

    /** A wildcard typed into the box is a character, not a query. */
    public function test_a_percent_sign_matches_nothing_rather_than_everything(): void
    {
        Medicine::create(['name' => 'ZzWildcardTarget', 'strength' => null, 'uses_count' => 0]);

        $this->assertSame(
            0,
            Medicine::query()->search('ZzWild%Target')->count(),
            'a typed % must not turn into a wildcard'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The six columns the first import threw away
    |--------------------------------------------------------------------------
    | 25,065 drugs were loaded by trade name alone and «DICLOFENAC» returned
    | nothing at all, though 102 registered products contain it. A doctor thinks
    | in ACTIVE INGREDIENT — that is how medicine is taught.
    */
    public function test_a_second_pass_enriches_rows_already_imported_by_name(): void
    {
        $row = Medicine::create(['name' => 'ZzThin 10 MG', 'strength' => null, 'uses_count' => 4]);

        $path = $this->file('meds-rich.csv',
            "commercial_name_en,scientific_name,manufacturer,price_egp\nZzThin 10 MG,ZZINGREDIENT,ZzPharma,25.5\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $row->refresh();

        $this->assertSame('ZZINGREDIENT', $row->scientific_name);
        $this->assertSame('ZzPharma', $row->manufacturer);
        $this->assertSame('25.50', (string) $row->price_egp);

        // A price with no date is a claim nobody can check.
        $this->assertNotNull($row->price_captured_at);

        // And the record of what doctors did is not a register's to rewrite.
        $this->assertSame(4, (int) $row->uses_count);
    }

    public function test_a_doctor_finds_every_brand_of_an_active_ingredient(): void
    {
        Medicine::create(['name' => 'ZzBrandOne 50 MG', 'strength' => null, 'scientific_name' => 'ZZDICLOTEST', 'uses_count' => 0]);
        Medicine::create(['name' => 'ZzBrandTwo 100 MG', 'strength' => null, 'scientific_name' => 'ZZDICLOTEST POTASSIUM', 'uses_count' => 0]);

        $found = Medicine::query()->search('ZZDICLOTEST')->pluck('name')->all();

        $this->assertContains('ZzBrandOne 50 MG', $found);
        $this->assertContains('ZzBrandTwo 100 MG', $found);
    }

    /** A brand-name hit still outranks an ingredient hit — you asked for a brand. */
    public function test_the_brand_still_wins_over_an_ingredient_match(): void
    {
        Medicine::create(['name' => 'ZZKEYB PLAIN', 'strength' => null, 'uses_count' => 0]);
        Medicine::create(['name' => 'ZzOther Brand', 'strength' => null, 'scientific_name' => 'ZZKEYB ACID', 'uses_count' => 90]);

        $this->assertSame('ZZKEYB PLAIN', Medicine::query()->search('ZZKEYB')->first()->name);
    }

    /**
     * Nobody types hamza consistently. The alias is stored folded and the term
     * is folded to meet it, so «اوجمينتين» and «أوجمينتين» are one word.
     */
    public function test_the_arabic_alias_ignores_hamza_and_punctuation(): void
    {
        $path = $this->file('meds-ar.csv',
            "commercial_name_en,commercial_name_ar\nZzArabicDrug 500 MG,\"أوجمينتينZZ . ./\"\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $stored = Medicine::query()->where('name', 'ZzArabicDrug 500 MG')->value('name_ar');

        $this->assertSame('اوجمينتين', $stored, 'folded, and the latin punctuation dropped');

        foreach (['أوجمينتين', 'اوجمينتين'] as $typed) {
            $this->assertSame(
                1,
                Medicine::query()->search($typed)->where('name', 'ZzArabicDrug 500 MG')->count(),
                "«{$typed}» must find it"
            );
        }
    }

    /**
     * The alias is a MATCHING key, never a name.
     *
     * The register ships a phonetic transliteration, not the registered Arabic
     * brand, so serialising it is the first step to a client rendering it as
     * the drug's name — or printing it on a prescription.
     */
    public function test_the_arabic_alias_never_leaves_the_api(): void
    {
        Medicine::create(['name' => 'ZzHidden 5 MG', 'strength' => null, 'name_ar' => 'زدهيدن', 'uses_count' => 0]);

        $body = $this->actingAs($this->admin())
            ->getJson(route('admin.medicines.search', ['q' => 'ZzHidden']))
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($body);
        $this->assertArrayNotHasKey('name_ar', $body[0], 'the alias must never be handed to a client');
        $this->assertSame('ZzHidden 5 MG', $body[0]['name']);
    }

    /** A register whose only name column is «generic_name» must not fill both. */
    public function test_the_name_column_is_never_read_as_the_ingredient_too(): void
    {
        $path = $this->file('meds-one.csv', "generic_name\nZzSoleColumn\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertNull(
            Medicine::query()->where('name', 'ZzSoleColumn')->value('scientific_name'),
            'the ingredient axis would just echo the trade name'
        );
    }

    /** «N/A» in a price column is not a price of zero. */
    public function test_an_unparseable_price_is_left_null(): void
    {
        $path = $this->file('meds-price.csv', "name,price_egp\nZzNoPrice,N/A\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertNull(Medicine::query()->where('name', 'ZzNoPrice')->value('price_egp'));
    }

    /*
    |--------------------------------------------------------------------------
    | «شيت كامل بالاسم والتركيزات» — export, edit, import
    |--------------------------------------------------------------------------
    | The register states no strength as a column: it writes it into the name,
    | recoverable for 58% of rows. The other 42% — and every «A.ONE SOAP 100 GM»
    | a parser cannot tell from «AUGMENTIN 1 GM» — are a human's job, so the
    | sheet has to go out and come back.
    */
    public function test_the_strength_is_read_out_of_the_name(): void
    {
        foreach ([
            'ZzD AUG 1 GM 14 F.C.TABS.' => '1 GM',
            'ZzD BRU 100MG/5ML SYRUP 150ML' => '100MG/5ML',
            'ZzD CAT 25 MG 20 SUGAR C.TABS.' => '25 MG',
            'ZzD VIT 50000 IU 10 CAPS.' => '50000 IU',
        ] as $name => $expected) {
            $this->assertSame($expected, DeriveMedicineStrength::read($name), $name);
        }
    }

    /**
     * A bottle is not a dose.
     *
     * A first pass allowed a bare ML and claimed 82% coverage, a quarter of
     * which was bottle sizes — «SUSP. 120 ML» is how much liquid you get, and
     * «20 F.C.TABS.» is how many tablets.
     */
    public function test_a_pack_size_is_never_read_as_a_strength(): void
    {
        foreach (['ZzP SUSP. 120 ML', 'ZzP 20 F.C.TABS.', 'ZzP GEL 100 ML'] as $name) {
            $this->assertNull(DeriveMedicineStrength::read($name), $name);
        }
    }

    /** A parsed strength never sits where a stated one goes. */
    public function test_a_derived_strength_stays_out_of_the_identity_column(): void
    {
        $row = Medicine::create(['name' => 'ZzDerive 250 MG 10 TABS.', 'strength' => null, 'uses_count' => 0]);

        Artisan::call('medicines:derive-strength');
        $row->refresh();

        $this->assertSame('250 MG', $row->strength_derived);
        $this->assertTrue((bool) $row->strength_is_derived);

        // (name, strength) is the row's identity and the importer matches on it.
        // Writing a guess there would make every future import miss its own
        // rows and duplicate all 25,000.
        $this->assertNull($row->strength, 'a guess must never become half the identity');
    }

    public function test_the_sheet_carries_every_column_a_reviewer_needs(): void
    {
        Medicine::create(['name' => 'ZzSheet 5 MG', 'strength' => null, 'scientific_name' => 'ZZSHEETMOL', 'uses_count' => 0]);

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.medicines.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Excel needs the BOM or Arabic arrives as mojibake');
        $this->assertStringContainsString(implode(',', Medicine::SHEET_COLUMNS), $csv);
        $this->assertStringContainsString('ZzSheet 5 MG', $csv);
        $this->assertStringContainsString('ZZSHEETMOL', $csv);
    }

    /**
     * The whole point of the id column.
     *
     * A reviewer fills in a strength and re-uploads. Matching on (name,
     * strength) the row no longer matches itself — so the correction would land
     * as a TWIN of the row it was meant to fix, and a 25,000-row sheet would
     * double the dictionary.
     */
    public function test_a_corrected_strength_lands_on_its_own_row(): void
    {
        $row = Medicine::create(['name' => 'ZzFixMe SYRUP', 'strength' => null, 'uses_count' => 7]);

        $path = $this->file('meds-fix.csv', "id,name,strength\n{$row->id},ZzFixMe SYRUP,120 MG/5 ML\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertSame(1, Medicine::query()->where('name', 'ZzFixMe SYRUP')->count(), 'no twin');

        $row->refresh();
        $this->assertSame('120 MG/5 ML', $row->strength);
        $this->assertFalse((bool) $row->strength_is_derived, 'a human typed it — it is stated now, not derived');
        $this->assertSame(7, (int) $row->uses_count, 'and the prescribing history is untouched');
    }

    /** Without an id the sheet still behaves as it always did. */
    public function test_a_sheet_without_ids_still_matches_on_name_and_strength(): void
    {
        Medicine::create(['name' => 'ZzNoId 10 MG', 'strength' => null, 'uses_count' => 0]);

        $path = $this->file('meds-noid.csv', "name,manufacturer\nZzNoId 10 MG,ZzMaker\n");

        Artisan::call('medicines:import', ['file' => $path]);
        @unlink($path);

        $this->assertSame(1, Medicine::query()->where('name', 'ZzNoId 10 MG')->count());
        $this->assertSame('ZzMaker', Medicine::query()->where('name', 'ZzNoId 10 MG')->value('manufacturer'));
    }

    /** A drug already written into someone's prescription is not deletable. */
    public function test_a_prescribed_drug_survives_a_delete(): void
    {
        $row = Medicine::create(['name' => 'ZzUsed', 'strength' => null, 'uses_count' => 3]);

        $this->actingAs($this->admin())
            ->delete(route('admin.medicines.destroy', $row->id))
            ->assertRedirect();

        $this->assertDatabaseHas('medicines', ['id' => $row->id]);
    }
}
