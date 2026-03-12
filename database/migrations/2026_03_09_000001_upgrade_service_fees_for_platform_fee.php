<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            // لو الأعمدة دي موجودة عندك بالفعل احذف المكرر منها
            if (!Schema::hasColumn('service_fees', 'business_id')) {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('service_fees', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('services')
                    ->nullOnDelete();
            }

            // كود القاعدة: booking_execution / booking_deposit / dispute_freeze ...
            if (!Schema::hasColumn('service_fees', 'fee_code')) {
                $table->string('fee_code', 100)
                    ->after('service_id')
                    ->index();
            }

            // نوع الرسم الرسمي
            if (!Schema::hasColumn('service_fees', 'fee_type')) {
                $table->string('fee_type', 50)
                    ->default('platform_fee')
                    ->after('fee_code')
                    ->index();
            }

            // من الذي يتحمل الرسم
            if (!Schema::hasColumn('service_fees', 'payer')) {
                $table->string('payer', 20)
                    ->default('client')
                    ->after('fee_type')
                    ->index();
            }

            // طريقة الحساب
            if (!Schema::hasColumn('service_fees', 'calc_type')) {
                $table->string('calc_type', 20)
                    ->default('fixed')
                    ->after('payer');
            }

            if (!Schema::hasColumn('service_fees', 'amount')) {
                $table->decimal('amount', 14, 2)
                    ->default(0)
                    ->after('calc_type');
            }

            if (!Schema::hasColumn('service_fees', 'min_amount')) {
                $table->decimal('min_amount', 14, 2)
                    ->nullable()
                    ->after('amount');
            }

            if (!Schema::hasColumn('service_fees', 'max_amount')) {
                $table->decimal('max_amount', 14, 2)
                    ->nullable()
                    ->after('min_amount');
            }

            if (!Schema::hasColumn('service_fees', 'currency')) {
                $table->string('currency', 3)
                    ->default('EGP')
                    ->after('max_amount');
            }

            if (!Schema::hasColumn('service_fees', 'priority')) {
                $table->unsignedInteger('priority')
                    ->default(100)
                    ->after('currency');
            }

            if (!Schema::hasColumn('service_fees', 'is_active')) {
                $table->boolean('is_active')
                    ->default(true)
                    ->after('priority')
                    ->index();
            }

            if (!Schema::hasColumn('service_fees', 'rules')) {
                $table->json('rules')
                    ->nullable()
                    ->after('is_active');
            }

            if (!Schema::hasColumn('service_fees', 'notes')) {
                $table->text('notes')
                    ->nullable()
                    ->after('rules');
            }
        });

        Schema::table('service_fees', function (Blueprint $table) {
            // unique منطقي لمنع تكرار نفس قاعدة الرسم
            $table->unique(
                ['business_id', 'service_id', 'fee_code', 'fee_type', 'payer'],
                'service_fees_unique_rule'
            );
        });
    }

    public function down(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            try {
                $table->dropUnique('service_fees_unique_rule');
            } catch (\Throwable $e) {
            }

            $dropCols = [
                'fee_code',
                'fee_type',
                'payer',
                'calc_type',
                'amount',
                'min_amount',
                'max_amount',
                'currency',
                'priority',
                'is_active',
                'rules',
                'notes',
            ];

            foreach ($dropCols as $col) {
                if (Schema::hasColumn('service_fees', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('service_fees', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }

            if (Schema::hasColumn('service_fees', 'business_id')) {
                $table->dropConstrainedForeignId('business_id');
            }
        });
    }
};