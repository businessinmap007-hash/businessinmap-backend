<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_group_id');
            $table->unsignedBigInteger('business_id'); // the member — an existing business user
            $table->timestamps();

            $table->unique(['business_group_id', 'business_id'], 'business_group_members_group_business_unique');
            $table->index('business_id', 'business_group_members_business_idx');

            $table->foreign('business_group_id', 'business_group_members_group_fk')
                ->references('id')->on('business_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('business_id', 'business_group_members_business_fk')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_group_members');
    }
};
