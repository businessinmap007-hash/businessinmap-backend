<?php

namespace Tests\Feature;

use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Three admin screens answered the same question — what may this child sell? —
 * and each wrote the same two rows its own way.
 *
 * The damage was measurable: the bulk screen rebuilt the config from scratch on
 * every save, so any key it had not heard of was dropped. «notes»,
 * `catalog_source` and `catalog_synced_at` — the matrix's own fields — survived
 * on 20 of 1,055 configs. And the bulk screen wrote `meta => null` on the link,
 * erasing the provenance the matrix had recorded there.
 *
 * @see \App\Services\Catalog\ChildServiceWriter
 */
class ChildServiceWriterTest extends TestCase
{
    use DatabaseTransactions;

    private ChildServiceWriter $writer;

    private int $rootId;

    private int $childId;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(ChildServiceWriter::class);

        $pair = DB::table('category_parent_child')->first(['parent_id', 'child_id']);
        $serviceId = (int) PlatformService::query()->where('is_active', 1)->value('id');

        if (! $pair || $serviceId <= 0) {
            $this->markTestSkipped('Needs a (root, child) pair and an active service.');
        }

        $this->rootId = (int) $pair->parent_id;
        $this->childId = (int) $pair->child_id;
        $this->serviceId = $serviceId;

        CategoryServiceConfig::query()
            ->where('category_id', $this->rootId)->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)->delete();

        CategoryPlatformService::query()
            ->where('category_id', $this->rootId)->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)->delete();
    }

    private function config(): array
    {
        return $this->writer->storedConfig($this->rootId, $this->childId, $this->serviceId);
    }

    private function link(): ?CategoryPlatformService
    {
        return CategoryPlatformService::query()
            ->where('category_id', $this->rootId)->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)->first();
    }

    /** A config nobody can reach is the failure this pairing exists to prevent. */
    public function test_the_link_and_the_config_are_written_together(): void
    {
        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, ['notes' => 'x']);

        $this->assertNotNull($this->link());
        $this->assertTrue((bool) $this->link()->is_active);

        $config = CategoryServiceConfig::query()
            ->where('category_id', $this->rootId)->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)->first();

        $this->assertNotNull($config);
        $this->assertTrue((bool) $config->is_active);
    }

    /**
     * The bug, in one assertion: a screen that never heard of «notes» must not
     * delete it just because it did not mention it.
     */
    public function test_a_key_one_screen_does_not_know_survives_another_screens_save(): void
    {
        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [
            'notes' => 'كتبها الأدمن من المصفوفة',
            'catalog_source' => 'service_catalog_matrix',
        ], source: 'service_catalog_matrix');

        // Now a bulk save that knows nothing about either key.
        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [
            'supports_quantity' => true,
        ], source: 'services_bulk');

        $config = $this->config();

        $this->assertSame('كتبها الأدمن من المصفوفة', $config['notes']);
        $this->assertSame('service_catalog_matrix', $config['catalog_source']);
        $this->assertTrue($config['supports_quantity']);
        $this->assertSame('services_bulk', $config['config_source'], 'the last writer is recorded');
    }

    /** An array the caller names IS replaced — it is authoritative about it. */
    public function test_a_named_list_is_replaced_not_merged(): void
    {
        $keys = $this->writer->allowedTypeKeys($this->serviceId);

        if (count($keys) < 2) {
            $this->markTestSkipped('This service has fewer than two item types.');
        }

        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [
            'allowed_item_types' => [$keys[0], $keys[1]],
        ]);

        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [
            'allowed_item_types' => [$keys[1]],
        ]);

        $this->assertSame([$keys[1]], $this->config()['allowed_item_types']);
    }

    /** No screen may store an item type the service does not have. */
    public function test_an_item_type_from_another_service_is_refused(): void
    {
        $keys = $this->writer->allowedTypeKeys($this->serviceId);

        if ($keys === []) {
            $this->markTestSkipped('This service has no item types.');
        }

        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [
            'allowed_item_types' => [$keys[0], 'a_key_this_service_never_had'],
        ]);

        $this->assertSame([$keys[0]], $this->config()['allowed_item_types']);
    }

    /** Passing nothing about meta must leave it alone; that is how it was wiped. */
    public function test_link_meta_survives_a_writer_that_says_nothing_about_it(): void
    {
        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, [], linkMeta: [
            'source' => 'service_catalog_matrix',
        ]);

        $this->assertSame('service_catalog_matrix', $this->link()->meta['source']);

        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, ['supports_extras' => true]);

        $this->assertSame(
            'service_catalog_matrix',
            $this->link()->meta['source'] ?? null,
            'a screen that said nothing about meta erased it'
        );
    }

    /** Disabling keeps the work: re-enabling must find the item types intact. */
    public function test_disabling_deactivates_both_rows_without_losing_the_config(): void
    {
        $this->writer->enable($this->rootId, $this->childId, $this->serviceId, ['notes' => 'keep me']);
        $this->writer->disable($this->rootId, $this->childId, $this->serviceId);

        $this->assertFalse((bool) $this->link()->is_active);

        $this->assertFalse(
            (bool) CategoryServiceConfig::query()
                ->where('category_id', $this->rootId)->where('child_id', $this->childId)
                ->where('platform_service_id', $this->serviceId)->value('is_active')
        );

        $this->assertSame('keep me', $this->config()['notes'], 'disabling threw the admin work away');
    }

    /** The three screens now share one path, so none can drift from the others. */
    public function test_every_admin_writer_goes_through_this_class(): void
    {
        foreach ([
            'ServiceCatalogMatrixController',
            'CategoryServiceBulkController',
            'ChildWorkbenchController',
        ] as $name) {
            $source = file_get_contents(app_path("Http/Controllers/AdminV2/{$name}.php"));

            $this->assertStringContainsString('ChildServiceWriter', $source, "{$name} bypasses the writer");

            foreach (['CategoryServiceConfig::query()->updateOrCreate', 'CategoryPlatformService::query()->updateOrCreate'] as $bypass) {
                $this->assertStringNotContainsString($bypass, $source, "{$name} still writes the row itself");
            }
        }
    }
}
