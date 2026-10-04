<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «اضف امكانية كيلو وربع ونص ولكل منتجات الاكل حتى الخضار والفواكة» — المالك، 2026-10-05. A line sold by the kilo
 * (or the litre) is ordered in 250 g steps: 0.25, 0.5, 1.5… The quantity of an order line is a decimal; whole
 * numbers stay exactly as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items MODIFY qty DECIMAL(10,3) NOT NULL DEFAULT 1');
    }

    public function down(): void
    {
    }
};
