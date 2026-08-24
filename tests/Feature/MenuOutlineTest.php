<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Option;
use App\Models\User;
use App\Services\Menu\MenuOutline;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «رتب المنيو كامل واربطه مع كل بزنس حسب الخيارات اللى رتبناها … قسم البيتزا
 *  يكون تحته كل أنواع البيتزا، فاكهة تحتها كل الفواكه» — المالك، 2026-08-24.
 *
 * The arrangement is READ, never stored: قسم = the option group, بند = the line
 * option, صنف = the merchant's row. What these hold down is that the reading
 * cannot drift from the two things it claims to reflect — the vocabulary the
 * child was curated with, and the heading the CUSTOMER sees.
 *
 * Rolls back.
 */
class MenuOutlineTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        // A trade whose curated line vocabulary is the restaurant band list.
        $this->business = User::query()
            ->where('type', 'business')
            ->where('category_child_id', '>', 0)
            ->whereIn('id', function ($q) {
                $q->select('u.id')->from('users as u')
                    ->join('category_children_master as c', 'c.id', '=', 'u.category_child_id')
                    ->where('c.name_ar', 'مطعم');
            })
            ->orderBy('id')
            ->first()
            ?: $this->markTestSkipped('Needs a restaurant business.');
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    private function bands()
    {
        $lines = app(MerchantOfferingVocabulary::class)->for(
            (int) $this->business->id,
            (int) $this->business->category_child_id,
            (int) $this->business->category_id
        )['lines'];

        if ($lines->isEmpty()) {
            $this->markTestSkipped('This trade has no curated line vocabulary.');
        }

        return $lines->first();
    }

    private function bandNamed(string $name): object
    {
        $band = $this->bands()->firstWhere('name_ar', $name);

        return $band ?: $this->markTestSkipped("The band «{$name}» is not in this trade's vocabulary.");
    }

    private function item(string $name, ?int $lineId = null, array $modifiers = [], array $attributes = []): MenuItem
    {
        $row = MenuItem::create(array_merge([
            'business_id' => $this->business->id,
            'menu_section_id' => null,
            'item_type' => 'menu_food',
            'name_ar' => $name,
            'base_price' => 100,
            'is_active' => 1,
        ], $attributes));

        if ($lineId) {
            $row->syncOfferingOptions($lineId, $modifiers);
        }

        return $row->refresh();
    }

    /** @return array<string,mixed>|null */
    private function sectionOf(array $outline, string $label): ?array
    {
        foreach ($outline['sections'] as $section) {
            if ($section['label'] === $label) {
                return $section;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function bandOf(array $section, string $label): ?array
    {
        foreach ($section['bands'] as $band) {
            if ($band['label'] === $label) {
                return $band;
            }
        }

        return null;
    }

    private function outline(): array
    {
        return app(MenuOutline::class)->for($this->business->fresh());
    }

    // ── the shape the owner asked for ───────────────────────────────────────

    public function test_a_band_holds_every_item_of_its_kind(): void
    {
        $pizza = $this->bandNamed('بيتزا');
        $sandwich = $this->bandNamed('ساندوتشات');

        $margherita = $this->item('بيتزا مارجريتا', (int) $pizza->id);
        $bbq = $this->item('بيتزا فراخ باربكيو', (int) $pizza->id);
        $shawarma = $this->item('شاورما فراخ', (int) $sandwich->id);

        $section = $this->sectionOf($this->outline(), 'بنود المنيو');
        $this->assertNotNull($section, 'The curated group is the section.');

        $band = $this->bandOf($section, 'بيتزا');
        $ids = collect($band['items'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$margherita->id, $bbq->id], $ids);
        $this->assertSame(2, $band['count']);
        $this->assertNotContains($shawarma->id, $ids, 'A sandwich is not a pizza.');
    }

    public function test_a_variety_stays_under_its_own_band(): void
    {
        // «مانجو — بلدي» و«مانجو — مستورد» صنفان تحت بندٍ واحد، لا بندان.
        // The customer's heading splits them; a review must not, or «كل أنواع
        // المانجو» becomes a question the screen refuses to answer.
        $pizza = $this->bandNamed('بيتزا');

        $modifier = Option::query()
            ->whereIn('id', app(MerchantOfferingVocabulary::class)
                ->pickableIds(
                    (int) $this->business->id,
                    (int) $this->business->category_child_id,
                    (int) $this->business->category_id
                )['modifiers'])
            ->first();

        if (! $modifier) {
            $this->markTestSkipped('This trade has no modifier to qualify a line with.');
        }

        $plain = $this->item('بيتزا سادة', (int) $pizza->id);
        $qualified = $this->item('بيتزا مميزة', (int) $pizza->id, [(int) $modifier->id]);

        $band = $this->bandOf($this->sectionOf($this->outline(), 'بنود المنيو'), 'بيتزا');

        $this->assertEqualsCanonicalizing(
            [$plain->id, $qualified->id],
            collect($band['items'])->pluck('id')->all()
        );
    }

    // ── the empty band is the finding ───────────────────────────────────────

    public function test_a_band_he_may_sell_and_does_not_is_shown_empty(): void
    {
        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $section = $this->sectionOf($this->outline(), 'بنود المنيو');
        $empty = $this->bandOf($section, 'شوربة');

        $this->assertNotNull($empty, 'Every curated band appears, filled or not.');
        $this->assertSame(0, $empty['count']);
        $this->assertGreaterThan(0, $section['empty_bands']);
    }

    public function test_the_totals_count_what_is_missing(): void
    {
        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $totals = $this->outline()['totals'];

        $this->assertSame(1, $totals['items']);
        $this->assertSame(1, $totals['filled_bands']);
        $this->assertSame($totals['bands'] - $totals['filled_bands'], $totals['empty_bands']);
        $this->assertGreaterThan(0, $totals['empty_bands']);
    }

    // ── it must agree with what the customer sees ───────────────────────────

    public function test_a_hand_written_section_wins_over_the_vocabulary(): void
    {
        // `MenuItem::heading()` puts a hand-written section first, so the
        // customer reads «عروض اليوم». A review that filed it under «بيتزا»
        // would be describing a screen nobody sees.
        $section = MenuSection::create([
            'business_id' => $this->business->id,
            'name_ar' => 'عروض اليوم',
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $item = $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id, [], [
            'menu_section_id' => $section->id,
        ]);

        $outline = $this->outline();

        $hand = $this->sectionOf($outline, 'عروض اليوم');
        $this->assertNotNull($hand);
        $this->assertSame('section', $hand['source']);
        $this->assertSame(1, $hand['items']);

        // …and its band inside that section is still the line it carries.
        $this->assertNotNull($this->bandOf($hand, 'بيتزا'), 'bands: ' . collect($hand['bands'])->pluck('label')->implode(' | '));

        $vocab = $this->sectionOf($outline, 'بنود المنيو');
        $this->assertSame(
            0,
            collect($this->bandOf($vocab, 'بيتزا')['items'])->count(),
            'One item, one place — never counted twice.'
        );

        $this->assertSame(1, $outline['totals']['items']);
        $this->assertNotNull($item->id);
    }

    public function test_an_item_naming_nothing_is_called_out(): void
    {
        $orphan = $this->item('صنف بلا هوية', null, [], ['item_type' => null]);

        $outline = $this->outline();

        $this->assertSame(1, $outline['totals']['unplaced']);

        $section = $this->sectionOf($outline, __('غير مصنّف'));
        $this->assertNotNull($section, 'An item with no word for itself is a finding, not a silence.');
        $this->assertSame($orphan->id, $section['bands'][0]['items'][0]->id);
    }

    public function test_an_item_typed_but_not_worded_falls_back_to_its_type(): void
    {
        $typed = $this->item('صنف بالنوع فقط');

        $section = $this->sectionOf($this->outline(), __('حسب نوع الصنف'));

        $this->assertNotNull($section);
        $this->assertSame($typed->id, $section['bands'][0]['items'][0]->id);
    }

    // ── the screen ──────────────────────────────────────────────────────────

    public function test_the_review_screen_renders_the_arrangement(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');

        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $this->actingAs($admin)
            ->get('/admin/menu-review?business_id=' . $this->business->id)
            ->assertOk()
            ->assertSee('بيتزا مارجريتا')
            ->assertSee('بنود المنيو')
            ->assertSee('شوربة');   // the band he was given and never filled
    }

    public function test_the_picker_screen_lists_who_has_a_menu(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');

        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $this->actingAs($admin)
            ->get('/admin/menu-review')
            ->assertOk()
            ->assertSee($this->business->name, false);
    }

    // ── the merchant's own door onto the same reading ───────────────────────

    public function test_the_merchant_reviews_his_own_list(): void
    {
        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $this->actingAs($this->business)
            ->get('/business/menu/review')
            ->assertOk()
            ->assertSee('بيتزا مارجريتا')
            ->assertSee('شوربة');   // the band he may fill and has not
    }

    public function test_a_merchant_reviews_his_own_list_and_nobody_else_s(): void
    {
        // There is no id in that URL, which is the point: the panel answers
        // for the acting business and there is nothing to widen.
        $mine = $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $stranger = User::query()
            ->where('type', 'business')
            ->where('id', '!=', $this->business->id)
            ->orderBy('id')
            ->first() ?: $this->markTestSkipped('Needs a second business.');

        $this->actingAs($stranger)
            ->get('/business/menu/review')
            ->assertOk()
            ->assertDontSee($mine->name_ar);
    }

    public function test_both_panels_read_one_arrangement(): void
    {
        // Same partial, same service. A second copy of «المراجعة» would tell
        // the merchant something the platform does not say about his own menu.
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');

        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        foreach ([
            [$admin, '/admin/menu-review?business_id=' . $this->business->id],
            [$this->business, '/business/menu/review'],
        ] as [$actor, $url]) {
            $this->actingAs($actor)->get($url)->assertOk()
                ->assertSee('بيتزا مارجريتا')
                ->assertSee('بنود المنيو');
        }
    }

    public function test_the_arrangement_stores_nothing(): void
    {
        // The reading is derived. If it ever writes, two answers to «ما الذي
        // يبيعه هذا؟» exist and they will disagree.
        $this->item('بيتزا مارجريتا', (int) $this->bandNamed('بيتزا')->id);

        $sections = DB::table('menu_sections')->where('business_id', $this->business->id)->count();

        $this->outline();

        $this->assertSame(
            $sections,
            DB::table('menu_sections')->where('business_id', $this->business->id)->count()
        );
    }
}
