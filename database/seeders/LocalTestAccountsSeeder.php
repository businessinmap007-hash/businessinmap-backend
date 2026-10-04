<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL ONLY — a furniture-factory account to try the web panel's «استيراد وتصدير المنيو» in a browser.
 * Refuses to run outside the local environment. The password is a throwaway test value for this account alone.
 *
 *   php artisan db:seed --class=LocalTestAccountsSeeder
 */
class LocalTestAccountsSeeder extends Seeder
{
    public const EMAIL = 'menu-import-test@bim.test';
    public const PASSWORD = 'Imp-c337c3affa36';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalTestAccountsSeeder runs on local only.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->forceFill([
            'name' => 'تجربة استيراد المنيو', 'name_en' => 'Menu import test', 'type' => 'business',
            'phone' => '01000000999', 'password' => self::PASSWORD,
            'category_id' => 23, 'category_child_id' => 116, 'country_id' => 1, 'governorate_id' => 10, 'city_id' => 549,
            'activated_at' => now(), 'api_token' => $user->api_token ?: \Illuminate\Support\Str::random(60), 'code' => $user->code ?: (string) random_int(10000000, 99999999),
        ])->save();

        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        if ($bedroom) {
            DB::table('option_user')->updateOrInsert(['user_id' => $user->id, 'option_id' => $bedroom], []);
        }
    }
}
