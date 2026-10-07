<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessCapability;
use Database\Seeders\BookingDesignOptionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «الأنظمة الغذائية وجداول التمارين … ستحتاجها بعض التخصصات ونقوم بربطها من الان» — المالك، 2026-10-08.
 *
 * A clinic opens the nutrition and exercise tools only when it ticked one of the specialties that need them.
 * Rolls back.
 */
class TrainingSpecialtyGateTest extends TestCase
{
    use DatabaseTransactions;

    private function business(int $category, int $child): User
    {
        $u = new User();
        $u->forceFill([
            'name' => 'اختبار ' . Str::random(4), 'type' => User::TYPE_BUSINESS, 'email' => 'gate-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password',
            'category_id' => $category, 'category_child_id' => $child, 'api_token' => Str::random(60),
        ])->save();

        return $u;
    }

    private function tick(User $u, string $specialty): void
    {
        $id = (int) DB::table('options')->where('name_ar', $specialty)->value('id');
        $this->assertGreaterThan(0, $id, $specialty);
        DB::table('option_user')->updateOrInsert(['user_id' => $u->id, 'option_id' => $id], []);
    }

    public function test_a_clinic_without_the_specialties_has_no_training_tools(): void
    {
        $this->seed(BookingDesignOptionsSeeder::class);
        $clinic = $this->business(20, 514);

        $caps = array_keys(BusinessCapability::forBusiness($clinic));

        $this->assertContains('prescriptions', $caps);
        $this->assertNotContains('training', $caps, 'a dentist has no use for a diet plan');
    }

    public function test_ticking_nutrition_physio_or_sports_medicine_opens_them(): void
    {
        $this->seed(BookingDesignOptionsSeeder::class);

        foreach (BusinessCapability::TRAINING_SPECIALTIES as $specialty) {
            $clinic = $this->business(20, 514);
            $this->tick($clinic, $specialty);

            $this->assertContains('training', array_keys(BusinessCapability::forBusiness($clinic)), $specialty);
        }
    }

    public function test_the_specialties_are_choices_a_clinic_can_tick(): void
    {
        $this->seed(BookingDesignOptionsSeeder::class);

        foreach ([514, 515, 513] as $child) {
            foreach (BusinessCapability::TRAINING_SPECIALTIES as $specialty) {
                $this->assertTrue(
                    DB::table('category_child_option as cco')->join('options as o', 'o.id', '=', 'cco.option_id')
                        ->where('cco.child_id', $child)->where('o.name_ar', $specialty)->exists(),
                    "$child / $specialty"
                );
            }
        }
    }

    public function test_a_gym_is_gated_by_its_trade_as_before(): void
    {
        $gym = $this->business(7, 130);

        $this->assertContains('training', array_keys(BusinessCapability::forBusiness($gym)));
    }
}
