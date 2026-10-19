<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «إعلان من المالك» (owner, 2026-10-26): any account — a customer, or a business of any other trade, such as a doctor
 * with a car to sell — lists ONE item per category (one car, one flat, one phone…) for sale, rent or swap, with no
 * business account behind it. The listing lives until it is sold or rented, then is removed three days later.
 *
 * Buyers do not see the seller's phone: they talk in the app's chat (an «inquiry» is the chat's subject), phone numbers
 * cannot be written there, and when the two agree either may ask to swap numbers — the platform's fee is paid then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 24)->index();            // car | property | phones … (OwnerListingCategories)
            $table->string('deal', 8);                          // sale | rent | swap
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('price_unit', 8)->nullable();        // day | week | month, for a rent
            $table->string('currency', 8)->default('EGP');
            $table->unsignedBigInteger('governorate_id')->index();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->json('attributes')->nullable();
            // active → (done | expired) → purged; flagged = hidden after reports until looked at
            $table->string('status', 12)->default('active')->index();
            $table->string('done_reason', 12)->nullable();      // sold | rented | swapped | withdrawn
            $table->timestamp('done_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('purge_at')->nullable()->index();
            $table->unsignedSmallInteger('reports_count')->default(0);
            $table->timestamps();

            $table->index(['category', 'status', 'governorate_id']);
            $table->index(['user_id', 'category', 'status']);
        });

        Schema::create('owner_listing_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_listing_id')->constrained('owner_listings')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            // open → (one asks to swap numbers) reveal_requested → revealed; a refusal puts it back to open
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('reveal_requested_by')->nullable();
            $table->timestamp('reveal_requested_at')->nullable();
            $table->timestamp('revealed_at')->nullable();
            $table->decimal('fee_total', 12, 2)->nullable();
            $table->json('fee_split')->nullable();              // {user_id: amount}
            $table->timestamps();

            $table->unique(['owner_listing_id', 'buyer_id']);
        });

        Schema::create('owner_listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_listing_id')->constrained('owner_listings')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['owner_listing_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_listing_reports');
        Schema::dropIfExists('owner_listing_inquiries');
        Schema::dropIfExists('owner_listings');
    }
};
