<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «تطبيق الكمبيوتر» phase 1 — inventory, purchases and the cashier (owner, 2026-10-29; canvas approved the same day).
 *
 * The stock is a LEDGER (`inv_moves`), never a number written over a number: every receipt, sale, waste, transfer,
 * stocktake difference and supplier return is a row of its own, made with a client-generated `uuid` so a device that
 * replays its queue after a lost connection never books a move twice. `inv_levels` is only the running sum of those
 * rows, kept in the same transaction.
 *
 * «ابنى نواه تصلح للكل وتظهر الحقول بناء على التصنيفات كما فعلنا فى الخدمات» — the features are the item types of a
 * new platform service `inventory`, ticked per (root, child) on the same services screens as every other service.
 */
return new class extends Migration
{
    private const FEATURES = [
        ['units', 'الأصناف والوحدات', 'Items and units', 1],
        ['sales_link', 'الربط بالمنيو والمتجر', 'Link to menu and store', 2],
        ['recipes', 'الوصفات (المكونات)', 'Recipes (ingredients)', 3],
        ['lots_expiry', 'التشغيلات وتاريخ الصلاحية', 'Lots and expiry dates', 4],
        ['barcode', 'الباركود', 'Barcode', 5],
        ['multi_warehouse', 'أكثر من مخزن', 'Several warehouses', 6],
        ['waste', 'الهالك والتالف', 'Waste and damage', 7],
        ['purchases', 'المشتريات والموردون', 'Purchases and suppliers', 8],
        ['pos', 'الكاشير', 'Cashier', 9],
    ];

    public function up(): void
    {
        DB::table('platform_services')->updateOrInsert(
            ['key' => 'inventory'],
            ['name_ar' => 'المخزون والمشتريات', 'name_en' => 'Inventory & purchases', 'is_active' => 1, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
        );
        $serviceId = (int) DB::table('platform_services')->where('key', 'inventory')->value('id');

        foreach (self::FEATURES as [$key, $ar, $en, $sort]) {
            DB::table('platform_service_item_types')->updateOrInsert(
                ['platform_service_id' => $serviceId, 'key' => $key],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => 1, 'is_default' => 0, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        Schema::create('inv_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->unique();
            $t->boolean('enabled')->default(false);
            // the optional features this business switched OFF (null = everything its category allows)
            $t->json('features_off')->nullable();
            $t->decimal('po_approval_threshold', 14, 2)->nullable();
            $t->unsignedInteger('next_po_number')->default(1);
            $t->unsignedInteger('next_receipt_number')->default(1);
            $t->unsignedInteger('next_sale_number')->default(1);
            $t->timestamps();
        });

        Schema::create('inv_warehouses', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('name', 120);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('inv_items', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('name_ar', 191);
            $t->string('name_en', 191)->nullable();
            $t->string('group_name', 120)->nullable();
            $t->string('base_unit', 40);
            $t->string('unit_kind', 12)->default('count');
            $t->string('barcode', 64)->nullable();
            $t->decimal('reorder_level', 16, 4)->nullable();
            $t->decimal('avg_cost', 14, 4)->default(0);
            $t->boolean('track_lots')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['business_id', 'barcode']);
        });

        Schema::create('inv_item_units', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('item_id')->index();
            $t->string('name', 40);
            $t->decimal('factor', 16, 4);
            $t->string('barcode', 64)->nullable();
            $t->timestamps();
            $t->foreign('item_id')->references('id')->on('inv_items')->cascadeOnDelete();
        });

        Schema::create('inv_lots', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('item_id');
            $t->string('code', 64);
            $t->date('expires_on')->nullable();
            $t->timestamps();
            $t->unique(['item_id', 'code']);
            $t->foreign('item_id')->references('id')->on('inv_items')->cascadeOnDelete();
        });

        Schema::create('inv_devices', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('name', 80);
            $t->string('role', 20);
            $t->json('screens')->nullable();
            $t->boolean('is_hub')->default(false);
            $t->unsignedBigInteger('registered_by')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_moves', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id');
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('lot_id')->nullable();
            $t->decimal('qty', 16, 4);
            $t->decimal('unit_cost', 14, 4)->default(0);
            $t->string('type', 24);
            $t->string('reason', 191)->nullable();
            $t->string('reference_type', 40)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->unsignedBigInteger('reverses_move_id')->nullable();
            $t->uuid('device_uuid')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamp('created_at')->nullable();
            $t->index(['business_id', 'id']);
            $t->index(['item_id', 'occurred_at']);
            $t->index(['reference_type', 'reference_id']);
            $t->foreign('item_id')->references('id')->on('inv_items');
        });

        Schema::create('inv_levels', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('lot_id')->default(0);
            $t->decimal('qty', 16, 4)->default(0);
            $t->timestamps();
            $t->unique(['item_id', 'warehouse_id', 'lot_id']);
        });

        Schema::create('inv_recipe_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('owner_type', 20);
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('item_id');
            $t->decimal('qty', 16, 4);
            $t->decimal('waste_pct', 5, 2)->default(0);
            $t->timestamps();
            $t->index(['owner_type', 'owner_id']);
            $t->foreign('item_id')->references('id')->on('inv_items')->cascadeOnDelete();
        });

        Schema::create('inv_suppliers', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('name', 160);
            $t->string('phone', 30)->nullable();
            $t->unsignedBigInteger('linked_business_id')->nullable()->index();
            $t->string('notes', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('inv_purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedInteger('number');
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->string('status', 24)->default('draft');
            $t->decimal('total', 14, 2)->default(0);
            $t->date('expected_on')->nullable();
            $t->string('notes', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('rejected_reason', 300)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('supplier_confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'number']);
            $t->foreign('supplier_id')->references('id')->on('inv_suppliers');
        });

        Schema::create('inv_purchase_order_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('purchase_order_id')->index();
            $t->unsignedBigInteger('item_id');
            $t->string('unit_name', 40);
            $t->decimal('unit_factor', 16, 4)->default(1);
            $t->decimal('qty', 16, 4);
            $t->decimal('unit_price', 14, 4);
            $t->decimal('received_qty', 16, 4)->default(0);
            $t->decimal('billed_qty', 16, 4)->default(0);
            $t->timestamps();
            $t->foreign('purchase_order_id')->references('id')->on('inv_purchase_orders')->cascadeOnDelete();
        });

        Schema::create('inv_receipts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedInteger('number');
            $t->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->uuid('device_uuid')->nullable();
            $t->string('notes', 500)->nullable();
            $t->timestamp('received_at');
            $t->timestamps();
        });

        Schema::create('inv_receipt_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('receipt_id')->index();
            $t->unsignedBigInteger('po_line_id')->nullable();
            $t->unsignedBigInteger('item_id');
            $t->string('unit_name', 40);
            $t->decimal('unit_factor', 16, 4)->default(1);
            $t->decimal('qty', 16, 4);
            $t->decimal('damaged_qty', 16, 4)->default(0);
            $t->decimal('unit_price', 14, 4);
            $t->unsignedBigInteger('lot_id')->nullable();
            $t->timestamps();
            $t->foreign('receipt_id')->references('id')->on('inv_receipts')->cascadeOnDelete();
        });

        Schema::create('inv_supplier_bills', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $t->string('number', 60)->nullable();
            $t->date('bill_date')->nullable();
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });

        Schema::create('inv_supplier_bill_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('bill_id')->index();
            $t->unsignedBigInteger('po_line_id');
            $t->decimal('qty', 16, 4);
            $t->decimal('unit_price', 14, 4);
            $t->timestamps();
            $t->foreign('bill_id')->references('id')->on('inv_supplier_bills')->cascadeOnDelete();
        });

        Schema::create('inv_supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('supplier_id')->index();
            $t->unsignedBigInteger('bill_id')->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('method', 20);
            $t->date('paid_on');
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_supplier_returns', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('supplier_id')->index();
            $t->unsignedBigInteger('purchase_order_id')->nullable();
            $t->unsignedBigInteger('warehouse_id');
            $t->decimal('total', 14, 2)->default(0);
            $t->string('reason', 300)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_stocktakes', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('warehouse_id');
            $t->string('title', 120)->nullable();
            $t->string('status', 16)->default('counting');
            $t->boolean('blind')->default(true);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_stocktake_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('stocktake_id')->index();
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('lot_id')->default(0);
            $t->decimal('expected_qty', 16, 4);
            $t->decimal('counted_qty', 16, 4)->nullable();
            $t->string('reason', 191)->nullable();
            $t->timestamps();
            $t->unique(['stocktake_id', 'item_id', 'lot_id']);
            $t->foreign('stocktake_id')->references('id')->on('inv_stocktakes')->cascadeOnDelete();
        });

        Schema::create('inv_pos_shifts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->uuid('device_uuid')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->decimal('opening_cash', 14, 2)->default(0);
            $t->decimal('counted_cash', 14, 2)->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_pos_sales', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedInteger('number');
            $t->unsignedBigInteger('shift_id')->nullable()->index();
            $t->uuid('device_uuid')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('warehouse_id');
            $t->string('status', 16)->default('paid');
            $t->decimal('total', 14, 2);
            $t->decimal('paid', 14, 2);
            $t->timestamp('sold_at');
            $t->timestamp('returned_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_pos_sale_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sale_id')->index();
            $t->string('owner_type', 20);
            $t->unsignedBigInteger('owner_id');
            $t->string('name', 191);
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->json('extras')->nullable();
            $t->decimal('qty', 16, 4);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('total', 14, 2);
            $t->timestamps();
            $t->foreign('sale_id')->references('id')->on('inv_pos_sales')->cascadeOnDelete();
        });

        Schema::create('business_suite_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->unique();
            $t->string('status', 16)->default('active');
            $t->unsignedInteger('months_paid')->default(0);
            $t->timestamp('paid_until')->nullable();
            $t->timestamp('grace_until')->nullable();
            $t->timestamp('warned_for')->nullable();
            $t->timestamps();
        });

        // the monthly subscription, from the business's wallet — off (free) until the owner prices it in the control panel
        DB::table('platform_event_fees')->updateOrInsert(
            ['event_key' => 'business_suite_month', 'role' => 'business'],
            ['enabled' => false, 'calc_type' => 'fixed', 'amount' => 0, 'currency' => 'EGP', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        foreach ([
            'business_suite_subscriptions', 'inv_pos_sale_lines', 'inv_pos_sales', 'inv_pos_shifts', 'inv_stocktake_lines', 'inv_stocktakes',
            'inv_supplier_returns', 'inv_supplier_payments', 'inv_supplier_bill_lines', 'inv_supplier_bills', 'inv_receipt_lines', 'inv_receipts',
            'inv_purchase_order_lines', 'inv_purchase_orders', 'inv_suppliers', 'inv_recipe_lines', 'inv_levels', 'inv_moves', 'inv_devices',
            'inv_lots', 'inv_item_units', 'inv_items', 'inv_warehouses', 'inv_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $serviceId = DB::table('platform_services')->where('key', 'inventory')->value('id');
        if ($serviceId) {
            DB::table('platform_service_item_types')->where('platform_service_id', $serviceId)->delete();
            DB::table('platform_services')->where('id', $serviceId)->delete();
        }
    }
};
