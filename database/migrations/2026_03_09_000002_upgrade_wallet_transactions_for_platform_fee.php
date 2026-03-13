<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'service_fee_id')) {
                $table->foreignId('service_fee_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('service_fees')
                    ->nullOnDelete();
            }

            // تصنيف العملية: payment / hold / release / refund / fee
            if (!Schema::hasColumn('wallet_transactions', 'category')) {
                $table->string('category', 30)
                    ->nullable()
                    ->after('service_fee_id')
                    ->index();
            }

            // الرسم من أي نوع
            if (!Schema::hasColumn('wallet_transactions', 'fee_type')) {
                $table->string('fee_type', 50)
                    ->nullable()
                    ->after('category')
                    ->index();
            }

            if (!Schema::hasColumn('wallet_transactions', 'fee_code')) {
                $table->string('fee_code', 100)
                    ->nullable()
                    ->after('fee_type')
                    ->index();
            }

            // على من تم تحميل الرسم
            if (!Schema::hasColumn('wallet_transactions', 'payer_id')) {
                $table->foreignId('payer_id')
                    ->nullable()
                    ->after('fee_code')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // لصالح من
            if (!Schema::hasColumn('wallet_transactions', 'beneficiary_id')) {
                $table->foreignId('beneficiary_id')
                    ->nullable()
                    ->after('payer_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // ربط رسمي بالسياق
            if (!Schema::hasColumn('wallet_transactions', 'booking_id')) {
                $table->foreignId('booking_id')
                    ->nullable()
                    ->after('beneficiary_id')
                    ->constrained('bookings')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('wallet_transactions', 'deposit_id')) {
                $table->foreignId('deposit_id')
                    ->nullable()
                    ->after('booking_id')
                    ->constrained('deposits')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('wallet_transactions', 'meta')) {
                $table->json('meta')
                    ->nullable()
                    ->after('deposit_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $foreigns = [
                'service_fee_id',
                'payer_id',
                'beneficiary_id',
                'booking_id',
                'deposit_id',
            ];

            foreach ($foreigns as $col) {
                if (Schema::hasColumn('wallet_transactions', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }

            foreach (['category', 'fee_type', 'fee_code', 'meta'] as $col) {
                if (Schema::hasColumn('wallet_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};