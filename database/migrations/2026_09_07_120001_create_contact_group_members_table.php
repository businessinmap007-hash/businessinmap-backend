<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_group_id');
            $table->unsignedBigInteger('user_id'); // the member — an existing registered user
            $table->timestamps();

            $table->unique(['contact_group_id', 'user_id'], 'contact_group_members_group_user_unique');
            $table->index('user_id', 'contact_group_members_user_idx');

            $table->foreign('contact_group_id', 'contact_group_members_group_fk')
                ->references('id')->on('contact_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'contact_group_members_user_fk')
                ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_group_members');
    }
};
