<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A notification is written once and read later by a recipient whose language
 * nobody knows at creation time, so both language slots must be filled at the
 * point of storage. InAppNotificationService::create() is the one place that
 * happens — every producer funnels through it.
 */
class BilingualNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        return User::query()->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a user.');
    }

    private function create(array $data): AppNotification
    {
        return app(InAppNotificationService::class)->create(
            $data + ['user_id' => $this->user()->id]
        );
    }

    /** The Arabic-only caller (ServiceEventNotificationService) still gets English. */
    public function test_english_is_filled_from_the_arabic_source(): void
    {
        $n = $this->create([
            'title_ar' => 'دعوة لمشاركتك في ضمان عملية',
            'body_ar' => 'تم رفض طلب الضمان',
        ]);

        $this->assertSame('An invitation to co-guarantee an operation', $n->title_en);
        $this->assertSame('Guarantee request declined', $n->body_en);
    }

    public function test_arabic_is_filled_from_an_english_only_caller(): void
    {
        $n = $this->create(['title_en' => 'Guarantee request accepted']);

        $this->assertSame('تم قبول طلب الضمان', $n->title_ar);
    }

    /** An explicit pair is stored verbatim — the fill never overwrites a caller. */
    public function test_an_explicit_pair_is_left_alone(): void
    {
        $n = $this->create([
            'title_ar' => 'دعوة لمشاركتك في ضمان عملية',
            'title_en' => 'Custom English title',
        ]);

        $this->assertSame('دعوة لمشاركتك في ضمان عملية', $n->title_ar);
        $this->assertSame('Custom English title', $n->title_en);
    }

    /**
     * Content the translations don't know (an author's own words, an interpolated
     * order number) still lands in both slots rather than leaving one NULL — the
     * app renders a notification, never a blank.
     */
    public function test_an_untranslated_string_still_fills_both_slots(): void
    {
        $n = $this->create(['body_ar' => 'طلبك رقم #4821 جاهز.']);

        $this->assertSame('طلبك رقم #4821 جاهز.', $n->body_ar);
        $this->assertSame('طلبك رقم #4821 جاهز.', $n->body_en);
    }

    /**
     * The stored content must not depend on the locale of whoever triggered it.
     * This is why notification content is passed raw, not wrapped in __().
     */
    public function test_stored_content_does_not_follow_the_actor_locale(): void
    {
        app()->setLocale('en');
        $asEnglishActor = $this->create(['title_ar' => 'تم قبول طلب الضمان']);

        app()->setLocale('ar');
        $asArabicActor = $this->create(['title_ar' => 'تم قبول طلب الضمان']);

        $this->assertSame($asArabicActor->title_ar, $asEnglishActor->title_ar);
        $this->assertSame($asArabicActor->title_en, $asEnglishActor->title_en);
        $this->assertSame('تم قبول طلب الضمان', $asEnglishActor->title_ar);
        $this->assertSame('Guarantee request accepted', $asEnglishActor->title_en);
    }
}
