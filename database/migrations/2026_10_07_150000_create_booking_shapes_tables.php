<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «أشكال الحجز»: how a booking trade page looks to the guest — the sibling of «أشكال المنيو». The booking PATTERN
        // (stay / table / duration / appointment…) says how one booking works; the SHAPE says how the page is laid out
        // (rooms grouped under their kind with photo and price, or one flat list; what the guest is asked, in what order).
        Schema::create('booking_shapes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('icon', 40)->nullable();
            // the BookingPattern this shape is for — a shape never changes how the booking works, only how it is shown
            $table->string('pattern', 20);
            $table->text('description')->nullable();
            // layout, show_photos, show_price, … — see App\Models\BookingShape::SETTINGS for the closed list
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // one shape per trade (child): the trade, not the shelf it stands on, decides how it is booked
        Schema::create('booking_shape_children', function (Blueprint $table) {
            $table->unsignedBigInteger('child_id')->primary();
            $table->unsignedBigInteger('booking_shape_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_shape_children');
        Schema::dropIfExists('booking_shapes');
    }
};
