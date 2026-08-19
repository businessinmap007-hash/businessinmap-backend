<?php

namespace Tests\Feature;

use App\Enums\BookingPattern;
use App\Http\Controllers\AdminV2\CategoryServiceBulkController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * الخطوة السادسة: لوحة الإدارة تحفظ نمطًا، لا ثمانية مفاتيح.
 *
 * كانت الشاشة تعرض خمسةَ مربّعات مستقلّة وقائمةَ حقولٍ مطلوبة. على ١٩٤ إعدادًا
 * نشطًا كان اثنان من ثمانية مفاتيح مضبوطَين، والستّة الباقية مطفأةً بلا
 * استثناءٍ واحد — لا لأن أحدًا قرّر ذلك، بل لأن ثمانية مفاتيح لا تُملأ يدويًا
 * صحيحةً مرّتين.
 */
class AdminBookingPatternPanelTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(array $input, array $stored = []): array
    {
        $controller = app(CategoryServiceBulkController::class);

        $method = new \ReflectionMethod($controller, 'bookingConfigPayload');
        $method->setAccessible(true);

        return $method->invoke($controller, new Request($input), $stored);
    }

    /** يُحفظ النمط، ويأتى الشكل كلُّه معه. */
    public function test_saving_a_pattern_derives_every_flag_it_implies(): void
    {
        $config = $this->payload(['booking_pattern' => BookingPattern::STAY->value]);

        $this->assertSame(BookingPattern::STAY->value, $config['booking_pattern']);

        foreach (BookingPattern::STAY->legacyFlags() as $key => $expected) {
            $this->assertSame($expected, $config[$key], "«{$key}» لم يُشتقّ من النمط");
        }

        $this->assertTrue($config['supports_guest_count'], 'الفندق لا يسأل «كم نزيلًا»');
    }

    /** ولا يستطيع الحفظ أن يكتب مفتاحًا يخالف النمط. */
    public function test_a_stray_flag_in_the_request_cannot_contradict_the_pattern(): void
    {
        $config = $this->payload([
            'booking_pattern' => BookingPattern::APPOINTMENT->value,
            'supports_guest_count' => '1',
            'supports_quantity' => '1',
            'requires_bookable_item' => '1',
        ]);

        $this->assertFalse($config['supports_guest_count']);
        $this->assertFalse($config['supports_quantity']);
        $this->assertFalse($config['requires_bookable_item'], 'موعدٌ يطالب بوحدة');
    }

    /**
     * الامتناع محفوظ.
     *
     * «مدّة» لا تحكم فى الوحدة، فالقيمة المخزَّنة تعبر الحفظ سالمة — وإلا فقد
     * ملعبُ الكرة حارسه لأن أحدهم فتح شاشة الإدارة وضغط حفظ.
     */
    public function test_an_admin_save_does_not_answer_what_the_pattern_abstains_from(): void
    {
        $config = $this->payload(
            ['booking_pattern' => BookingPattern::DURATION->value],
            ['requires_bookable_item' => true]
        );

        $this->assertTrue($config['requires_bookable_item']);

        $config = $this->payload(
            ['booking_pattern' => BookingPattern::DURATION->value],
            ['requires_bookable_item' => false]
        );

        $this->assertFalse($config['requires_bookable_item']);
    }

    /** والأنماط الإضافية قرارُ ملفّ التوزيع، لا شاشةِ حفظ — فلا تُمحى. */
    public function test_the_extra_patterns_a_child_opens_survive_an_admin_save(): void
    {
        $config = $this->payload(
            ['booking_pattern' => BookingPattern::DURATION->value],
            ['booking_patterns' => [BookingPattern::DURATION->value, BookingPattern::COURSE->value]]
        );

        $this->assertContains(BookingPattern::COURSE->value, $config['booking_patterns']);
    }

    /** وتصنيفٌ بلا نمط لا يُخمَّن له شكل: الغياب سكوتٌ لا حكم. */
    public function test_a_child_with_no_pattern_is_left_alone(): void
    {
        $config = $this->payload(['requires_bookable_item' => '1']);

        $this->assertArrayNotHasKey('booking_pattern', $config);
        $this->assertArrayNotHasKey('requires_bookable_item', $config);
        $this->assertArrayNotHasKey('supports_guest_count', $config);
    }
}
