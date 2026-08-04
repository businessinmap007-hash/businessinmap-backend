<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one item type carry more than one priced line.
 *
 * `bsp_business_child_service_item_unique` on (business, child, service,
 * item_type) meant a hospital could hold exactly ONE «كشف». Charging 300 for
 * كشف عظام and 250 for كشف باطنة was refused by the database itself — the very
 * gap that priced options exist to close.
 *
 * The line lives in `offering_options` like every other coordinate, but a
 * unique key cannot reach across tables, so it is mirrored here. `0` means "no
 * line named", and it is 0 rather than NULL on purpose: MySQL counts NULLs as
 * distinct in a unique index, so nameless rows would stop colliding with each
 * other and the original guard would quietly disappear.
 *
 * HasOfferingOptions::syncOfferingOptions keeps the two in step; nothing else
 * writes this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_service_prices', 'line_option_id')) {
            Schema::table('business_service_prices', function (Blueprint $table) {
                $table->unsignedBigInteger('line_option_id')->default(0)->after('bookable_item_type');
            });
        }

        if ($this->hasIndex('bsp_business_child_service_item_unique')) {
            DB::statement('ALTER TABLE `business_service_prices` DROP INDEX `bsp_business_child_service_item_unique`');
        }

        if (! $this->hasIndex('bsp_business_child_service_item_line_unique')) {
            DB::statement(
                'ALTER TABLE `business_service_prices`
                 ADD UNIQUE `bsp_business_child_service_item_line_unique`
                 (`business_id`, `child_id`, `service_id`, `bookable_item_type`, `line_option_id`)'
            );
        }
    }

    public function down(): void
    {
        // Collapse to one row per (business, child, service, item type) before
        // the narrower key can come back.
        DB::statement(
            'DELETE a FROM business_service_prices a
             JOIN business_service_prices b
               ON a.business_id = b.business_id
              AND a.child_id <=> b.child_id
              AND a.service_id = b.service_id
              AND a.bookable_item_type <=> b.bookable_item_type
              AND a.id > b.id'
        );

        if ($this->hasIndex('bsp_business_child_service_item_line_unique')) {
            DB::statement('ALTER TABLE `business_service_prices` DROP INDEX `bsp_business_child_service_item_line_unique`');
        }

        if (! $this->hasIndex('bsp_business_child_service_item_unique')) {
            DB::statement(
                'ALTER TABLE `business_service_prices`
                 ADD UNIQUE `bsp_business_child_service_item_unique`
                 (`business_id`, `child_id`, `service_id`, `bookable_item_type`)'
            );
        }

        if (Schema::hasColumn('business_service_prices', 'line_option_id')) {
            Schema::table('business_service_prices', function (Blueprint $table) {
                $table->dropColumn('line_option_id');
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return ! empty(DB::select('SHOW INDEX FROM `business_service_prices` WHERE Key_name = ?', [$name]));
    }
};
