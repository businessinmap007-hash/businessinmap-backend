<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اقفل الإعلان على مدينتي أو محافظتي» (owner, 2026-10-28): a listing may be limited to the people of its own city or of its
 * own governorate — a flat for rent, a sofa nobody will drive across the country for. `visibility`: all (default) | governorate |
 * city. Who counts as "of that place" is the viewer's own registered governorate / city.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_listings', 'visibility')) {
                $table->string('visibility', 12)->default('all')->after('city_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owner_listings', fn (Blueprint $t) => $t->dropColumn('visibility'));
    }
};
