<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('platform_service_id')->nullable();

    $table->morphs('disputeable');

    $table->unsignedBigInteger('opened_by_user_id');
    $table->unsignedBigInteger('against_user_id')->nullable();

    $table->string('status')->default('open');

    $table->string('reason_code')->nullable();
    $table->text('reason_text')->nullable();

    $table->string('resolution_type')->nullable();
    $table->json('resolution_payload')->nullable();

    $table->timestamp('opened_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('closed_at')->nullable();

    $table->timestamps();

});
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
