<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تسعير وفاتورة الصيدلية على الدواء — المالك، 2026-08-27.
 *
 * حتى الآن الصيدلية تصرف الدواء بلا أي سعر مسجَّل على الإطلاق. السعر فعل
 * الصيدلية وحدها (يختلف من صيدلية لأخرى، ولا علاقة له بسعرها فى «قاموس
 * الأدوية» بالضرورة)، فيُكتب لحظة التسعير لا يُقرأ من مكان آخر تلقائيًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescriptions')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('prescriptions', 'medicine_total')) {
                    $table->decimal('medicine_total', 10, 2)->nullable()->after('dispensed_at');
                }
                if (! Schema::hasColumn('prescriptions', 'priced_at')) {
                    $table->timestamp('priced_at')->nullable()->after('medicine_total');
                }
            });
        }

        if (Schema::hasTable('prescription_items')) {
            Schema::table('prescription_items', function (Blueprint $table) {
                if (! Schema::hasColumn('prescription_items', 'unit_price')) {
                    $table->decimal('unit_price', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('prescription_items', 'billed_quantity')) {
                    $table->unsignedInteger('billed_quantity')->nullable();
                }
                if (! Schema::hasColumn('prescription_items', 'line_total')) {
                    $table->decimal('line_total', 10, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            foreach (['medicine_total', 'priced_at'] as $column) {
                if (Schema::hasColumn('prescriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            foreach (['unit_price', 'billed_quantity', 'line_total'] as $column) {
                if (Schema::hasColumn('prescription_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
