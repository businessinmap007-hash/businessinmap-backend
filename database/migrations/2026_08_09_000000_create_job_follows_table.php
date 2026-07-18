<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user follows job FIELDS (a whole category, or one specialty) so that a
 * vacancy posted there notifies them live. Simpler than offer_follows — jobs
 * have no price/audience axis — so this is its own small table rather than
 * overloading offer_follows and mixing two notification streams.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_follows')) {
            return;
        }

        Schema::create('job_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // A whole root category (child_id null) OR one specialty. At least
            // one of the two is always set (enforced in the controller).
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('category_child_id')->nullable()->constrained('category_children_master')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_matched_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'category_child_id'], 'job_follows_unique');
            $table->index(['category_id', 'is_active']);
            $table->index(['category_child_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_follows');
    }
};
