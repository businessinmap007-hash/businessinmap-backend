<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->timestamp('dispute_opened_at')->nullable()->after('status');
            $table->enum('dispute_opened_by', ['client','business','admin'])->nullable()->after('dispute_opened_at');
            $table->text('dispute_reason')->nullable()->after('dispute_opened_by');

            // agreements for resolution
            $table->tinyInteger('release_agreed_client')->default(0)->after('business_confirmed');
            $table->tinyInteger('release_agreed_business')->default(0)->after('release_agreed_client');

            $table->tinyInteger('refund_agreed_client')->default(0)->after('release_agreed_business');
            $table->tinyInteger('refund_agreed_business')->default(0)->after('refund_agreed_client');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn([
                'dispute_opened_at',
                'dispute_opened_by',
                'dispute_reason',
                'release_agreed_client',
                'release_agreed_business',
                'refund_agreed_client',
                'refund_agreed_business',
            ]);
        });
    }
};