<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * «عند التسجيل كبزنس يعطى 419 Page Expired» — المالك، 2026-08-19.
 *
 * الحمايةُ نفسها سليمة: فحصتُ المسار على البوّابتين، فمرّ بترميزٍ طازج ورفض
 * بلا ترميز. لكن انتهاء الجلسة ليس خطأً من المستخدم — النموذج فُتح ثم دارت
 * الجلسة تحته — وصفحةُ ٤١٩ العارية كانت تبتلع اسمًا وهاتفًا وتصنيفًا ليُعاد
 * كتابته من الصفر.
 */
class ExpiredPageRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([StartSession::class])->group(function () {
            Route::get('__expiry/form', fn () => 'form');
            Route::post('__expiry/submit', fn () => 'never reached');
        });
    }

    private function expire(): void
    {
        Route::post('__expiry/submit', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
    }

    /** يعود إلى نموذجه ومعه ما كتبه، لا إلى صفحةٍ عارية. */
    public function test_an_expired_page_returns_the_visitor_to_the_form_with_their_input(): void
    {
        $this->expire();

        $response = $this->from('/__expiry/form')
            ->post('/__expiry/submit', [
                'first_name' => 'محمد',
                'phone' => '01000000000',
                'password' => 'sirri',
            ]);

        $response->assertRedirect('/__expiry/form');
        $response->assertSessionHas('error');

        $this->assertSame('محمد', session('_old_input.first_name'));
        $this->assertSame('01000000000', session('_old_input.phone'));
    }

    /** وكلمةُ المرور لا تُعاد — ما لا يُحفظ عند فشل التحقّق لا يُحفظ هنا. */
    public function test_the_password_is_never_flashed_back(): void
    {
        $this->expire();

        $this->from('/__expiry/form')
            ->post('/__expiry/submit', ['first_name' => 'محمد', 'password' => 'sirri']);

        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    /** والعميلُ البرمجىّ ينتظر رمزًا لا تحويلًا — الـ API لم يُمَسّ. */
    public function test_a_json_client_still_gets_the_status_code(): void
    {
        $this->expire();

        $this->postJson('/__expiry/submit', ['first_name' => 'محمد'])
            ->assertStatus(419);
    }
}
