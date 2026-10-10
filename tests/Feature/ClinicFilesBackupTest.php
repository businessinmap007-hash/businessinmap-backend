<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «النسخة الاحتياطية المشفرة لملفات المرضى» — one opaque blob per clinic account. Rolls back.
 */
class ClinicFilesBackupTest extends TestCase
{
    use DatabaseTransactions;

    private function blob(int $iterations = 310000, int $dataBytes = 200, string $kind = 'clinic_files'): string
    {
        return json_encode([
            'v' => 1, 'kind' => $kind, 'kdf' => 'pbkdf2-sha256', 'iterations' => $iterations,
            'salt' => base64_encode(random_bytes(16)), 'data' => base64_encode(random_bytes($dataBytes)),
        ]);
    }

    private function clinic(): User
    {
        return User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
    }

    public function test_a_clinic_keeps_one_backup_that_each_new_one_replaces_and_it_comes_back_as_written(): void
    {
        Sanctum::actingAs($this->clinic());
        $this->getJson('/api/v2/clinic-files-backup')->assertNotFound();

        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $this->blob()])->assertOk();
        $second = $this->blob();
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $second])->assertOk()->assertJsonPath('data.bytes', strlen($second));

        $this->assertSame(1, DB::table('clinic_file_backups')->where('user_id', $this->clinic()->id)->count());
        $this->assertSame($second, $this->getJson('/api/v2/clinic-files-backup')->assertOk()->json('data.blob'));
    }

    public function test_it_is_big_enough_for_a_clinic_and_no_bigger_than_the_cap(): void
    {
        Sanctum::actingAs($this->clinic());

        // a few MB of sealed files — a clinic of thousands of patients — goes in
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $this->blob(310000, 3_000_000)])->assertOk();

        // past the cap it does not
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => str_repeat('x', 31_000_000)])->assertUnprocessable();
    }

    public function test_it_only_takes_what_the_app_writes(): void
    {
        Sanctum::actingAs($this->clinic());

        $this->putJson('/api/v2/clinic-files-backup', ['blob' => 'plain text anyone could read'])->assertUnprocessable();
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $this->blob(1000)])->assertUnprocessable();
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $this->blob(310000, 200, 'medical_file')])->assertUnprocessable();
    }

    public function test_nobody_reads_another_accounts_backup_and_the_owner_can_delete_his(): void
    {
        [$a, $b] = User::query()->where('type', 'business')->orderBy('id')->limit(2)->get()->all();
        Sanctum::actingAs($a);
        $this->putJson('/api/v2/clinic-files-backup', ['blob' => $this->blob()])->assertOk();

        Sanctum::actingAs($b);
        $this->getJson('/api/v2/clinic-files-backup')->assertNotFound();

        Sanctum::actingAs($a);
        $this->deleteJson('/api/v2/clinic-files-backup')->assertOk();
        $this->getJson('/api/v2/clinic-files-backup')->assertNotFound();
    }
}
