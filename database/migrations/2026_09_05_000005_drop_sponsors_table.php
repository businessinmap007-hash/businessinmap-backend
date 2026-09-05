<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sponsors (home-page ad banner) feature is retired: never ported to
 * Api/V2, and by 2026-09-05 every remaining row had already expired with no
 * replacement bought since 2023. Data was purged via `sponsors:purge --all`
 * before this migration ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sponsors');
    }

    public function down(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('image', 191);
            $table->string('price', 191)->nullable();
            $table->timestamp('expire_at')->nullable();
            $table->enum('type', ['paid', 'free'])->default('free');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['activated_at', 'type', 'expire_at'], 'sponsors_active_type_expire_idx');
            $table->index('user_id', 'sponsors_user_idx');
            $table->index('expire_at', 'sponsors_expire_idx');
        });
    }
};
