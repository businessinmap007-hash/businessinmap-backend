<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «زر ابلاغ عن مشكلة بالغرفة وزر طلب خدمة»: what a guest asks of the hotel while the stay is running.
        // The hotel's own list of things a guest may order (coffee, breakfast, extra towels…). A hotel with no rows
        // at all is offered the platform's starting list; the first row it adds replaces it.
        Schema::create('stay_service_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('title', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stay_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id')->index();
            // the room as it was when the request was made — the hotel reads "room 305", never a booking id
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('room_number', 40)->nullable();
            $table->string('kind', 12);            // issue | service
            $table->string('category', 30)->nullable(); // an issue's category key
            $table->string('title', 160);
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_requests');
        Schema::dropIfExists('stay_service_options');
    }
};
