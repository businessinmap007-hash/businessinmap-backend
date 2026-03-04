<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('wallet_pins', function (Blueprint $table) {

            // One pin per user
            $table->unique('user_id', 'wpins_user_unique');

            // Useful for finding unlocked/locked users
            $table->index('locked_until', 'wpins_locked_until_idx');

            // FK (إن لم تكن موجودة)
            // ملاحظة: لو عندك FK موجودة بالفعل قد يفشل، في هذه الحالة احذف هذا الجزء
            $table->foreign('user_id', 'wpins_user_fk')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_pins', function (Blueprint $table) {
            // drop FK first
            $table->dropForeign('wpins_user_fk');
            $table->dropUnique('wpins_user_unique');
            $table->dropIndex('wpins_locked_until_idx');
        });
    }
};