<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "branch" (group) layer between a platform service and its item types.
 *
 * Item types were a flat list per service. This introduces
 * platform_service_item_groups (e.g. hotel / clinic / sports under booking)
 * and links each item type to a group via group_id. Existing groupings were
 * already encoded in platform_service_item_types.meta->domain_key, so we
 * backfill from there; rows without a domain_key stay ungrouped (group_id NULL).
 */
return new class extends Migration
{
    /**
     * Human labels for the known domain_key values seeded earlier, so the
     * generated branches read nicely in Arabic instead of raw keys.
     */
    private array $labels = [
        'hotel' => ['ar' => 'فنادق ووحدات سكنية', 'en' => 'Hotels & Units'],
        'clinic' => ['ar' => 'عيادات ومواعيد طبية', 'en' => 'Clinics & Appointments'],
        'sports' => ['ar' => 'ملاعب رياضية', 'en' => 'Sports Fields'],
        'restaurant_table' => ['ar' => 'طاولات المطاعم', 'en' => 'Restaurant Tables'],
        'training' => ['ar' => 'تدريب ودورات', 'en' => 'Training & Courses'],
        'supermarket' => ['ar' => 'سوبر ماركت', 'en' => 'Supermarket'],
        'restaurant_menu' => ['ar' => 'منيو المطاعم', 'en' => 'Restaurant Menu'],
        'delivery' => ['ar' => 'توصيل', 'en' => 'Delivery'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('platform_service_item_groups')) {
            Schema::create('platform_service_item_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platform_service_id')
                    ->constrained('platform_services')
                    ->cascadeOnDelete();
                $table->string('key', 100);
                $table->string('name_ar', 191)->nullable();
                $table->string('name_en', 191)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['platform_service_id', 'key'], 'psig_service_key_unique');
                $table->index(['platform_service_id', 'is_active'], 'psig_service_active_index');
            });
        }

        if (Schema::hasTable('platform_service_item_types') && ! Schema::hasColumn('platform_service_item_types', 'group_id')) {
            Schema::table('platform_service_item_types', function (Blueprint $table) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('platform_service_id')
                    ->constrained('platform_service_item_groups')
                    ->nullOnDelete();
            });
        }

        $this->backfillFromDomainKey();
    }

    /**
     * Create one group per distinct (service, meta->domain_key) pair and point
     * the matching item types at it. Idempotent: existing groups are reused and
     * only item types still lacking a group_id are updated.
     */
    private function backfillFromDomainKey(): void
    {
        if (! Schema::hasTable('platform_service_item_types')
            || ! Schema::hasColumn('platform_service_item_types', 'group_id')
            || ! Schema::hasTable('platform_service_item_groups')) {
            return;
        }

        $pairs = DB::table('platform_service_item_types')
            ->selectRaw("platform_service_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.domain_key')) AS domain_key, COUNT(*) AS n")
            ->whereRaw("JSON_EXTRACT(meta, '$.domain_key') IS NOT NULL")
            ->groupBy('platform_service_id', 'domain_key')
            ->get();

        $sortByService = [];

        foreach ($pairs as $pair) {
            $serviceId = (int) $pair->platform_service_id;
            $domainKey = trim((string) $pair->domain_key);

            if ($serviceId <= 0 || $domainKey === '' || $domainKey === 'null') {
                continue;
            }

            $sortByService[$serviceId] = ($sortByService[$serviceId] ?? 0) + 1;
            $label = $this->labels[$domainKey] ?? ['ar' => null, 'en' => $domainKey];

            $group = DB::table('platform_service_item_groups')
                ->where('platform_service_id', $serviceId)
                ->where('key', $domainKey)
                ->first();

            if ($group) {
                $groupId = (int) $group->id;
            } else {
                $groupId = (int) DB::table('platform_service_item_groups')->insertGetId([
                    'platform_service_id' => $serviceId,
                    'key' => $domainKey,
                    'name_ar' => $label['ar'],
                    'name_en' => $label['en'],
                    'sort_order' => $sortByService[$serviceId],
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('platform_service_item_types')
                ->where('platform_service_id', $serviceId)
                ->whereNull('group_id')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.domain_key')) = ?", [$domainKey])
                ->update(['group_id' => $groupId, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_service_item_types') && Schema::hasColumn('platform_service_item_types', 'group_id')) {
            Schema::table('platform_service_item_types', function (Blueprint $table) {
                $table->dropForeign(['group_id']);
                $table->dropColumn('group_id');
            });
        }

        Schema::dropIfExists('platform_service_item_groups');
    }
};
