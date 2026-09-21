<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trainer's own photo library — the machine, the grip, the plated meal —
 * kept once and reused across as many clients as he likes.
 *
 * The files sit in private storage like every plan photo. Attaching a library
 * photo to a client's exercise or meal COPIES the file into that plan, so a
 * plan stays self-contained: deleting a photo from the library, or one client's
 * exercise, can never break another client's plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_id')->index();
            $table->string('image');
            $table->string('caption', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_photos');
    }
};
