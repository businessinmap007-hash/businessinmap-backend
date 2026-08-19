<?php

namespace Tests\Feature;

use App\Enums\BookingPattern;
use App\Models\BusinessBookingSetting;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الدرجة الوسطى من سُلّم التفصيل: الطفل يفتح، والنشاط يقرّر.
 *
 * قبل هذا الجدول لم يكن لصاحب المحل مفتاحٌ واحد، فكانت صالة البلايستيشن
 * والجيم والبولينج — وهى ثلاثة أشياء مختلفة تحت نمطٍ واحد — تعرض الشاشة
 * نفسها. وما يحرسه هذا الملف هو أن الفراغ ما زال يعنى «خذ ما يقوله النمط».
 */
class BusinessBookingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    /** نشاطٌ يقف على ابنٍ يعلن هذا النمط بالفعل. */
    private function ownerOn(BookingPattern $pattern): User
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $rows = DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)->where('is_active', 1)
            ->get(['category_id', 'child_id', 'config']);

        foreach ($rows as $row) {
            if (BookingPattern::tryFromConfig($row->config) !== $pattern) {
                continue;
            }

            $owner = User::query()->where('type', 'business')
                ->where('category_child_id', $row->child_id)
                ->where('category_id', $row->category_id)
                ->first();

            if ($owner) {
                return $owner;
            }
        }

        $this->markTestSkipped("لا نشاط قائم على نمط «{$pattern->label()}».");
    }

    /** أكثر الأنشطة لن يكون لها صفّ — وهذه هى الحال السليمة لا الناقصة. */
    public function test_a_business_with_no_row_behaves_exactly_like_its_pattern(): void
    {
        $shape = BusinessBookingSetting::resolve(BookingPattern::STAY, null);

        $this->assertSame(BookingPattern::UNIT_ALWAYS, $shape['unit']);
        $this->assertSame(BookingPattern::STAY->asks(), $shape['asks']);
        $this->assertSame(BookingPattern::STAY->requires(), $shape['requires']);
        $this->assertNull($shape['slot_minutes']);
    }

    /**
     * سؤال الوحدة يُجاب هنا، ومن يملك الجواب.
     *
     * نمط «مدّة» يمتنع، فيبقى معلّقًا حتى يقول صاحبُ المحل: عندى ستّة أجهزة،
     * أو لا وحدات عندى — دخولٌ فقط.
     */
    public function test_the_business_answers_the_unit_question_the_child_abstained_from(): void
    {
        $this->assertSame(
            BookingPattern::UNIT_OPTIONAL,
            BookingPattern::DURATION->unit(),
            'نمط «مدّة» لم يعد ممتنعًا'
        );

        $undecided = BusinessBookingSetting::resolve(BookingPattern::DURATION, new BusinessBookingSetting());
        $this->assertSame(BookingPattern::UNIT_OPTIONAL, $undecided['unit'], 'الفراغ حُسب قرارًا');

        $playstation = new BusinessBookingSetting(['uses_units' => true]);
        $gym = new BusinessBookingSetting(['uses_units' => false]);

        $this->assertSame(BookingPattern::UNIT_ALWAYS, BusinessBookingSetting::resolve(BookingPattern::DURATION, $playstation)['unit']);
        $this->assertSame(BookingPattern::UNIT_NEVER, BusinessBookingSetting::resolve(BookingPattern::DURATION, $gym)['unit']);
    }

    /** ما يقرّره الطفل مطلوبًا لا يستطيع النشاط إسقاطه — يضيف ولا يحذف. */
    public function test_a_business_may_add_requirements_but_never_drop_the_pattern_s(): void
    {
        $row = new BusinessBookingSetting([
            'asks' => ['room_preference'],
            'requires' => ['room_preference'],
        ]);

        $shape = BusinessBookingSetting::resolve(BookingPattern::STAY, $row);

        foreach (BookingPattern::STAY->requires() as $field) {
            $this->assertContains($field, $shape['requires'], "النشاط أسقط «{$field}» وهو من النمط");
        }

        $this->assertContains('room_preference', $shape['requires']);
    }

    /** ما يُطلب لا بدّ أن يكون معروضًا — شرطٌ على حقلٍ مخفىّ بابٌ مسدود. */
    public function test_nothing_is_required_without_being_asked(): void
    {
        $row = new BusinessBookingSetting(['requires' => ['a_field_never_shown']]);

        foreach (BookingPattern::cases() as $pattern) {
            $shape = BusinessBookingSetting::resolve($pattern, $row);

            foreach ($shape['requires'] as $field) {
                $this->assertContains($field, $shape['asks'], "«{$pattern->label()}» يشترط حقلًا لا يعرضه: {$field}");
            }
        }
    }

    /** «زيارةٌ عندك» لا تُعرض إلا على من يفعل الاثنين. */
    public function test_the_visit_place_appears_only_when_the_business_does_both(): void
    {
        $atShop = BusinessBookingSetting::resolve(BookingPattern::APPOINTMENT, null);
        $this->assertNotContains('visit_place', $atShop['asks']);

        $plumber = new BusinessBookingSetting(['visit_mode' => BusinessBookingSetting::VISIT_BOTH]);
        $this->assertContains('visit_place', BusinessBookingSetting::resolve(BookingPattern::APPOINTMENT, $plumber)['asks']);
    }

    /** الشاشة تُحفظ، والفراغ يُخزَّن NULL لا صفرًا. */
    public function test_the_owner_saves_and_an_empty_field_stays_undecided(): void
    {
        $owner = $this->ownerOn(BookingPattern::DURATION);
        $this->actingAs($owner);

        $this->put(route('business.booking-settings.update', [], false), [
            'pattern' => BookingPattern::DURATION->value,
            'uses_units' => '1',
            'slot_minutes' => 60,
        ])->assertRedirect();

        $row = BusinessBookingSetting::query()->where('business_id', $owner->id)->firstOrFail();

        $this->assertTrue($row->uses_units);
        $this->assertSame(60, $row->slot_minutes);
        $this->assertNull($row->lead_time_minutes, 'حقلٌ لم يُملأ صار قرارًا');

        $this->put(route('business.booking-settings.update', [], false), [
            'pattern' => BookingPattern::DURATION->value,
            'uses_units' => '',
        ])->assertRedirect();

        $this->assertNull($row->fresh()->uses_units, '«لم أحدّد» لم يعد إلى الامتناع');
    }

    /** لا يختار النشاط نمطًا لم يفتحه له تصنيفه. */
    public function test_a_business_cannot_pick_a_pattern_its_child_never_opened(): void
    {
        $owner = $this->ownerOn(BookingPattern::STAY);
        $this->actingAs($owner);

        $this->put(route('business.booking-settings.update', [], false), [
            'pattern' => BookingPattern::TABLE->value,
        ])->assertSessionHasErrors('pattern');

        $this->assertDatabaseMissing('business_booking_settings', [
            'business_id' => $owner->id,
            'pattern' => BookingPattern::TABLE->value,
        ]);
    }

    /** الشاشة تُفتح وتعرض أنماط الطفل. */
    public function test_the_screen_opens_for_a_booking_business(): void
    {
        $owner = $this->ownerOn(BookingPattern::APPOINTMENT);

        $this->actingAs($owner)
            ->get(route('business.booking-settings.edit', [], false))
            ->assertOk()
            ->assertSee(BookingPattern::APPOINTMENT->label());
    }
}
