<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make platform_service_item_groups.platform_service_id nullable so a "branch"
 * can be a shared pool not owned by a single service. Which services a branch
 * touches is derived from its member item types' group_id (a branch may span
 * two or more services). See the service-branch board (ServiceBranchBoardController).
 *
 * Raw MODIFY (keeps the existing FK) so it needs no doctrine/dbal. Idempotent:
 * only runs when the column is still NOT NULL.
 */
return new class extends Migration
{
    private function isNullable(): ?bool
    {
        if (! Schema::hasTable('platform_service_item_groups')) {
            return null;
        }

        $col = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['platform_service_item_groups', 'platform_service_id']
        );

        if (! $col) {
            return null;
        }

        return strtoupper((string) $col->IS_NULLABLE) === 'YES';
    }

    public function up(): void
    {
        if ($this->isNullable() === false) {
            DB::statement('ALTER TABLE `platform_service_item_groups` MODIFY `platform_service_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Only revert if it is safe (no shared/null-service branches exist),
        // otherwise leave it nullable to avoid failing on NULL rows.
        if ($this->isNullable() === true
            && (int) (DB::table('platform_service_item_groups')->whereNull('platform_service_id')->count()) === 0) {
            DB::statement('ALTER TABLE `platform_service_item_groups` MODIFY `platform_service_id` BIGINT UNSIGNED NOT NULL');
        }
    }
};
