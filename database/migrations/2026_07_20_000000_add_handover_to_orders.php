<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order-handover confirmation (BIM-13.5). A one-time `handover_token` is issued
 * when an order is ready (pending); scanning it flips the order to completed and
 * consumes the token (nulled). `handover_confirmed_at` records the moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'handover_token')) {
                $table->string('handover_token', 64)->nullable()->unique()->after('share_token');
            }
            if (! Schema::hasColumn('orders', 'handover_confirmed_at')) {
                $table->timestamp('handover_confirmed_at')->nullable()->after('handover_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'handover_token')) {
                $table->dropUnique(['handover_token']);
                $table->dropColumn('handover_token');
            }
            if (Schema::hasColumn('orders', 'handover_confirmed_at')) {
                $table->dropColumn('handover_confirmed_at');
            }
        });
    }
};
