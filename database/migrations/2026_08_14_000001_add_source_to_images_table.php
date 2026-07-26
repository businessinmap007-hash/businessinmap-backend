<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an image reached us: taken live by the device camera, or picked from
 * storage / uploaded. Project progress photos MUST be camera-captured so the
 * contractor following a shipment or a build can trust the evidence — the app
 * declares this per photo and the API enforces `camera` for that surface.
 *
 * Note (honesty): a server cannot cryptographically prove a file came off the
 * lens rather than the gallery; this records the client's declared origin (the
 * mobile app only offers the live camera for these photos) and the API rejects
 * anything not declared `camera`. Older rows default to `upload`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('source', 16)->default('upload')->after('imageable_type');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
