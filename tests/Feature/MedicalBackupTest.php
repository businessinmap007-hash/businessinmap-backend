<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «النسخة الاحتياطية المشفرة» of the medical file — one opaque blob per account. Rolls back.
 */
class MedicalBackupTest extends TestCase
{
    use DatabaseTransactions;

    private function blob(int $iterations = 310000): string
    {
        return json_encode(['v' => 1, 'kdf' => 'pbkdf2-sha256', 'iterations' => $iterations, 'salt' => base64_encode(random_bytes(16)), 'data' => base64_encode(random_bytes(120))]);
    }

    public function test_an_account_keeps_one_backup_that_each_new_one_replaces(): void
    {
        $me = User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();
        Sanctum::actingAs($me);
        $this->getJson('/api/v2/medical-backup')->assertNotFound();

        $first = $this->blob();
        $this->putJson('/api/v2/medical-backup', ['blob' => $first])->assertOk();
        $second = $this->blob();
        $this->putJson('/api/v2/medical-backup', ['blob' => $second])->assertOk();

        $this->assertSame(1, DB::table('medical_backups')->where('user_id', $me->id)->count());
        $this->assertSame($second, $this->getJson('/api/v2/medical-backup')->assertOk()->json('data.blob'), 'stored exactly as the phone wrote it');
    }

    public function test_nobody_reads_another_accounts_backup_and_the_owner_can_delete_his(): void
    {
        [$a, $b] = User::query()->where('type', '!=', 'business')->orderBy('id')->limit(2)->get()->all();
        Sanctum::actingAs($a);
        $this->putJson('/api/v2/medical-backup', ['blob' => $this->blob()])->assertOk();

        Sanctum::actingAs($b);
        $this->getJson('/api/v2/medical-backup')->assertNotFound();

        Sanctum::actingAs($a);
        $this->deleteJson('/api/v2/medical-backup')->assertOk();
        $this->getJson('/api/v2/medical-backup')->assertNotFound();
    }

    public function test_it_only_takes_what_the_app_writes(): void
    {
        Sanctum::actingAs(User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail());

        $this->putJson('/api/v2/medical-backup', ['blob' => 'plain text anyone could read'])->assertUnprocessable();
        $this->putJson('/api/v2/medical-backup', ['blob' => $this->blob(1000)])->assertUnprocessable();
        $this->putJson('/api/v2/medical-backup', ['blob' => str_repeat('x', 700000)])->assertUnprocessable();
    }
}
