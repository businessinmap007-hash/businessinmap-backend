<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshotted at checkout, from BusinessMenuSetting::deposit_required_above —
 * a later change to the business's setting must never rewrite an order
 * already placed. Nothing is actually held/frozen in this first pass (see
 * CustomerCartService::assessDeposit()'s docblock): `deposit_covered` is
 * advisory, read by the business at accept time
 * (Api\V2\OrderController::businessAccept) to decide, not a payment gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'requires_deposit')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('requires_deposit')->default(false)->after('final_total');
                $table->decimal('deposit_amount', 10, 2)->nullable()->after('requires_deposit');
                $table->boolean('deposit_covered')->default(false)->after('deposit_amount');
                // wallet | guarantee | null (uncovered)
                $table->string('deposit_covered_by', 20)->nullable()->after('deposit_covered');
                // The business explicitly chose to accept an uncovered order —
                // the audit trail for "who took the risk, and when".
                $table->boolean('deposit_accepted_without_cover')->default(false)->after('deposit_covered_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'requires_deposit')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn([
                    'requires_deposit', 'deposit_amount', 'deposit_covered',
                    'deposit_covered_by', 'deposit_accepted_without_cover',
                ]);
            });
        }
    }
};
