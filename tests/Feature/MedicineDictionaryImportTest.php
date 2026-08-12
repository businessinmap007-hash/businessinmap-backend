<?php

namespace Tests\Feature;

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
        $this->actingAs($this->admin())->get(route('admin.medicines.index'))->assertOk();
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
