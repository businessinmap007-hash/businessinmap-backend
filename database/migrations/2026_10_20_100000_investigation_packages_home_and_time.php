<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «المعمل والأشعة كما في اللوحة» — المالك، 2026-10-20:
 *   - باقات: a centre prices a named set of its tests as one (the package's own price);
 *   - سحب العينة من المنزل: a lab's fee for it (null = it does not do it), and the patient asks for it on an order;
 *   - الموعد: the patient names the day and time he wants (`requested_at`); the centre may keep it or propose another.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('investigation_packages')) {
            Schema::create('investigation_packages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('center_id')->index();
                $t->string('name', 120);
                $t->string('note', 255)->nullable();
                $t->decimal('price', 10, 2);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('investigation_package_items')) {
            Schema::create('investigation_package_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('package_id')->index();
                $t->unsignedBigInteger('option_id');
                $t->unique(['package_id', 'option_id']);
            });
        }

        if (! Schema::hasTable('investigation_center_settings')) {
            Schema::create('investigation_center_settings', function (Blueprint $t) {
                $t->unsignedBigInteger('center_id')->primary();
                $t->decimal('home_fee', 10, 2)->nullable();
                $t->timestamps();
            });
        }

        Schema::table('investigation_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('investigation_orders', 'requested_at')) {
                $t->timestamp('requested_at')->nullable()->after('appointment_at');
            }
            if (! Schema::hasColumn('investigation_orders', 'home_collection')) {
                $t->boolean('home_collection')->default(false)->after('requested_at');
            }
            if (! Schema::hasColumn('investigation_orders', 'home_fee')) {
                $t->decimal('home_fee', 10, 2)->nullable()->after('home_collection');
            }
        });

        Schema::table('investigation_order_items', function (Blueprint $t) {
            if (! Schema::hasColumn('investigation_order_items', 'package_id')) {
                $t->unsignedBigInteger('package_id')->nullable()->after('option_id');
            }
            if (! Schema::hasColumn('investigation_order_items', 'details')) {
                $t->string('details', 500)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investigation_order_items', function (Blueprint $t) {
            foreach (['package_id', 'details'] as $c) {
                if (Schema::hasColumn('investigation_order_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('investigation_orders', function (Blueprint $t) {
            foreach (['requested_at', 'home_collection', 'home_fee'] as $c) {
                if (Schema::hasColumn('investigation_orders', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::dropIfExists('investigation_center_settings');
        Schema::dropIfExists('investigation_package_items');
        Schema::dropIfExists('investigation_packages');
    }
};
