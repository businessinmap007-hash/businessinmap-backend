<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الكيس المختوم» (owner, 2026-10-29): a parcel travels in a bag carrying the sender's mark that is destroyed when opened, so nobody
 * can tamper with it on the way.
 *
 *  - sealed_ack_at / seal_number: the sender says, when he asks for the ride, that it is sealed so (and the seal's number, if any).
 *  - seal_photo / seal_photo_at: the driver photographs the sealed bag with the camera when he takes it — the proof of its state. Private.
 *  - refused_unsealed_at: the driver refused it at pickup because it was not sealed.
 *  - seal_state / seal_checked_at: at delivery the receiver says whether the seal is intact or open; open opens a dispute at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_parcels', function (Blueprint $table) {
            $table->timestamp('sealed_ack_at')->nullable();
            $table->string('seal_number', 40)->nullable();
            $table->string('seal_photo')->nullable();
            $table->timestamp('seal_photo_at')->nullable();
            $table->timestamp('refused_unsealed_at')->nullable();
            $table->string('seal_state', 10)->nullable(); // intact | broken
            $table->timestamp('seal_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_parcels', function (Blueprint $table) {
            $table->dropColumn(['sealed_ack_at', 'seal_number', 'seal_photo', 'seal_photo_at', 'refused_unsealed_at', 'seal_state', 'seal_checked_at']);
        });
    }
};
