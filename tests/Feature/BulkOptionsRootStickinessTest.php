<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «عند التعديل على خيارات الاب سيارات وعمل حفظ والانتقال الى اى اب اخر وعمل
 * رفرش للصفحة يعود الى سيارات اى ان الروت المحفوظ هو روت اخر تعديل تم ولذلك
 * حدثت مشكلة انتقال خيارات الرياضة الى خيارات زراعية وحيوانية».
 *
 * The bulk options screen switches root inside the page and the address bar
 * never heard about it, so the URL kept naming whichever root the last SAVE
 * had redirected to. A refresh put the admin back on that root while he
 * believed he was on the one he had clicked, and everything he did next was
 * written under a root he was not looking at.
 *
 * Three things had to move with a root switch and only one of them did. What
 * is held here is the other two, because together they are the mechanism that
 * turned a wrong-root page into a whole root overwritten in one submit:
 *
 *   1. the URL           — or the refresh lands somewhere else
 *   2. the child ticks   — the switch used to tick EVERY child of the root it
 *                          opened, so a stray submit addressed all of them
 *   3. the option ticks  — seeded from the previous root's child and left
 *                          standing, in «استبدال بالكامل» mode
 *
 * These are assertions about the script the screen ships, which is where the
 * behaviour lives; the server half — a root and a set of children that do not
 * belong together — is asserted against the real endpoint below.
 */
class BulkOptionsRootStickinessTest extends TestCase
{
    use DatabaseTransactions;

    private function script(): string
    {
        return (string) file_get_contents(
            resource_path('views/admin-v2/category-children/options/bulk.blade.php')
        );
    }

    /** The switch writes the root it opened into the address bar. */
    public function test_switching_root_rewrites_the_url(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('rememberRoot(rootId)', $script);
        $this->assertStringContainsString("params.set('parent_id', String(rootId))", $script);

        // replaceState, not pushState: eight root clicks must not become eight
        // back presses between the admin and the page he came from.
        $this->assertStringContainsString('window.history.replaceState', $script);
        $this->assertStringNotContainsString('history.pushState', $script);
    }

    /** …and activateRoot actually calls it. */
    public function test_the_root_switch_is_what_calls_it(): void
    {
        $body = $this->between($this->script(), 'function activateRoot(rootId) {', "\n    }");

        $this->assertStringContainsString('rememberRoot(rootId)', $body);
    }

    /**
     * Opening a root must not tick its children. The markup already promises
     * this — «Nothing is pre-ticked unless the URL asked for it» — and the
     * switch broke the promise one screenful below it.
     */
    public function test_switching_root_ticks_no_child(): void
    {
        $body = $this->between($this->script(), 'function activateRoot(rootId) {', "\n    }");

        $this->assertStringContainsString('input.checked = false', $body);
        $this->assertStringNotContainsString('input.checked = active', $body);
    }

    /** …and drops the options seeded for the root being left. */
    public function test_switching_root_clears_the_option_ticks(): void
    {
        $body = $this->between($this->script(), 'function activateRoot(rootId) {', "\n    }");

        $this->assertMatchesRegularExpression(
            '/optionBoxes\(\)\.forEach\(function \(input\) \{ input\.checked = false; \}\)/',
            $body,
            'the previous root\'s vocabulary is still ticked after the switch'
        );
    }

    /** A save aimed at a root that does not hold the children is refused. */
    public function test_a_child_from_another_root_is_refused(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin to act as.');
        }

        $pair = DB::table('category_parent_child as a')
            ->join('category_parent_child as b', 'b.parent_id', '!=', 'a.parent_id')
            ->select('a.parent_id as root', 'b.child_id as stranger')
            ->whereNotIn('b.child_id', function ($q) {
                $q->select('child_id')->from('category_parent_child')
                    ->whereColumn('parent_id', 'a.parent_id');
            })
            ->first();

        if (! $pair) {
            $this->markTestSkipped('Every child sits under every root.');
        }

        $before = DB::table('category_child_option')
            ->where('child_id', $pair->stranger)
            ->where('category_id', $pair->root)
            ->count();

        $optionId = (int) DB::table('options')->value('id');

        $this->actingAs($admin)
            ->post(route('admin.category-child-options.bulk.update'), [
                'child_ids' => [$pair->stranger],
                'option_ids' => [$optionId],
                'mode' => 'append',
                'parent_id' => $pair->root,
            ])
            ->assertSessionHas('error');

        $this->assertSame(
            $before,
            DB::table('category_child_option')
                ->where('child_id', $pair->stranger)
                ->where('category_id', $pair->root)
                ->count(),
            'a row was written under a root the child does not sit beneath'
        );
    }

    private function between(string $haystack, string $open, string $close): string
    {
        $start = strpos($haystack, $open);

        $this->assertNotFalse($start, "«{$open}» is gone from the screen");

        $end = strpos($haystack, $close, $start);

        return substr($haystack, $start, ($end === false ? strlen($haystack) : $end) - $start);
    }
}
