<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مواصفات لكل رقم غرفة»: a numbered room behind a room type can carry its own features (sea view, pool view…) from the hotel's feature
 * vocabulary. The type stays what the guest books; when the stay starts the number given is one that carries what the guest asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_item_room_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('option_id');
            $table->timestamps();

            $table->unique(['room_id', 'option_id'], 'room_features_unique');
            $table->index('option_id', 'room_features_option_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_item_room_features');
    }
};
