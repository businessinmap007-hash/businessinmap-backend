<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BIM-11.1 — make an address possible.
 *
 * `addresses.country_id`, `governorate_id` and `city_id` all had foreign keys to
 * `locations`: a self-referencing tree holding 71 country rows whose name_ar AND
 * name_en are empty for every single one, and not one governorate or city. So
 * the database itself refused any real address — a governorate id from
 * `governorates` simply is not a `locations` id, and the insert died on the
 * constraint. That is the floor under the bug: the controller validation, the
 * Address model relations and the legacy form were all wrong too, but even with
 * those fixed the row could not land.
 *
 * The live tables are `countries` (249, full ISO 3166-1), `governorates` (27)
 * and `cities` (1,339) — what the v1 pickers, the scheduling service and the
 * BIM-3.5 fee-rule admin have always read.
 *
 * No data migration: `addresses` has zero rows, because neither writer could
 * ever produce one. Nothing to convert, nothing to lose.
 *
 * `locations` itself is left alone — v1 controllers still reference it and the
 * decision to keep legacy code stands.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard rather than assume: this is only safe because there is nothing
        // to convert. If rows ever appear, stop and decide deliberately.
        $existing = DB::table('addresses')->count();

        if ($existing > 0) {
            throw new RuntimeException(
                "addresses has {$existing} row(s). This migration assumes an empty table because the ids "
                . 'point at `locations` and would need converting to `governorates`/`cities` first.'
            );
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign('addresses_country_id_foreign');
            $table->dropForeign('addresses_governorate_id_foreign');
            $table->dropForeign('addresses_city_id_foreign');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('governorate_id')->references('id')->on('governorates')->nullOnDelete();
            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['city_id']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('governorate_id')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('city_id')->references('id')->on('locations')->nullOnDelete();
        });
    }
};
