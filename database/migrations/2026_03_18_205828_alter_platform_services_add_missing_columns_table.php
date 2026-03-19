<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_services', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_services', 'key')) {
                $table->string('key', 100)->nullable()->after('id');
            }

            if (! Schema::hasColumn('platform_services', 'name_ar')) {
                $table->string('name_ar', 191)->nullable()->after('key');
            }

            if (! Schema::hasColumn('platform_services', 'name_en')) {
                $table->string('name_en', 191)->nullable()->after('name_ar');
            }

            if (! Schema::hasColumn('platform_services', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name_en');
            }

            if (! Schema::hasColumn('platform_services', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }

            if (! Schema::hasColumn('platform_services', 'supports_deposit')) {
                $table->boolean('supports_deposit')->default(false)->after('sort_order');
            }

            if (! Schema::hasColumn('platform_services', 'max_deposit_percent')) {
                $table->unsignedInteger('max_deposit_percent')->default(0)->after('supports_deposit');
            }

            if (! Schema::hasColumn('platform_services', 'meta')) {
                $table->json('meta')->nullable()->after('max_deposit_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_services', function (Blueprint $table) {
            $drops = [];

            foreach ([
                'meta',
                'max_deposit_percent',
                'supports_deposit',
                'sort_order',
                'is_active',
            ] as $column) {
                if (Schema::hasColumn('platform_services', $column)) {
                    $drops[] = $column;
                }
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};