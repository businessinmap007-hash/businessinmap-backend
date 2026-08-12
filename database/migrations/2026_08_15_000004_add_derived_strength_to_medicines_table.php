<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The strength read OUT of the name, kept apart from the strength a source
 * stated.
 *
 * This register has no strength column — it writes it into the name
 * («AUGMENTIN 1 GM 14 F.C.TABS.»), so it can be read back out for about 58% of
 * rows. But reading is guessing dressed up: «A.ONE SOAP 100 GM» is how heavy
 * the bar is, not a dose. A parsed value must therefore never sit where a
 * stated one goes.
 *
 * It is also kept out of `strength` for a structural reason: (name, strength)
 * is the row's IDENTITY and the importer matches on it. Writing a derived value
 * there would make every future import miss its own rows and create duplicates
 * of all 25,065.
 *
 * `strength_is_derived` is not redundant with "is it null" — a future register
 * may state a strength for a row we had already parsed, and the two must stay
 * distinguishable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('strength_derived', 120)->nullable()->after('strength');
            $table->boolean('strength_is_derived')->default(false)->after('strength_derived');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['strength_derived', 'strength_is_derived']);
        });
    }
};
