<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six columns the register carried and the dictionary threw away.
 *
 * 25,065 drugs were imported by trade name alone, and a doctor thinks in ACTIVE
 * INGREDIENT — that is how medicine is taught. Typing «DICLOFENAC» returned
 * nothing at all, though 102 registered products contain it.
 *
 * - `scientific_name` — the second search axis, and the important one. 91% of
 *   the register carries it: 8,553 distinct ingredients.
 * - `name_ar` — a SEARCH ALIAS and nothing else. The source states its Arabic
 *   column is a deterministic phonetic transliteration, not the registered
 *   Arabic brand, so it must never be shown as the drug's name or written onto
 *   a prescription. It earns its place because an Egyptian doctor types Arabic
 *   and got zero results.
 * - `manufacturer`, `drug_class`, `route` — how you tell two near-identical
 *   names apart in a list of twenty.
 * - `price_egp` + `price_captured_at` — for the pharmacy end. The date is not
 *   decoration: the source's own disclaimer says prices change constantly, so a
 *   price with no date is a claim nobody can check.
 * - `source` — where a row came from, so a better register can later replace
 *   these rows without touching what doctors typed themselves.
 *
 * All nullable: a drug a doctor adds by hand has none of it, and must not be
 * blocked for that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('scientific_name', 500)->nullable()->after('strength');
            $table->string('name_ar', 300)->nullable()->after('scientific_name');
            $table->string('manufacturer', 191)->nullable()->after('name_ar');
            $table->string('drug_class', 191)->nullable()->after('manufacturer');
            $table->string('route', 100)->nullable()->after('drug_class');
            $table->decimal('price_egp', 10, 2)->nullable()->after('route');
            $table->date('price_captured_at')->nullable()->after('price_egp');
            $table->string('source', 100)->nullable()->after('price_captured_at');

            // Both are searched on every keystroke. Prefixed length because a
            // 500-char utf8mb4 column will not fit in an index key.
            $table->index([DB::raw('scientific_name(100)')], 'medicines_scientific_index');
            $table->index([DB::raw('name_ar(100)')], 'medicines_name_ar_index');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('medicines_scientific_index');
            $table->dropIndex('medicines_name_ar_index');
            $table->dropColumn([
                'scientific_name', 'name_ar', 'manufacturer', 'drug_class',
                'route', 'price_egp', 'price_captured_at', 'source',
            ]);
        });
    }
};
