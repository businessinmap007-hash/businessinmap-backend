<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug', 191)->nullable()->after('name_en');
            }

            if (!Schema::hasColumn('categories', 'meta')) {
                $table->json('meta')->nullable()->after('slug');
            }
        });

        if (Schema::hasColumn('categories', 'slug')) {
            DB::table('categories')
                ->select('id', 'name_en', 'name_ar')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $base = $row->name_en ?: $row->name_ar ?: ('category-' . $row->id);

                        $slug = str($base)
                            ->lower()
                            ->replaceMatches('/[^a-z0-9]+/u', '-')
                            ->trim('-')
                            ->value();

                        if ($slug === '') {
                            $slug = 'category-' . $row->id;
                        }

                        $original = $slug;
                        $counter = 1;

                        while (
                            DB::table('categories')
                                ->where('slug', $slug)
                                ->where('id', '!=', $row->id)
                                ->exists()
                        ) {
                            $slug = $original . '-' . $counter;
                            $counter++;
                        }

                        DB::table('categories')
                            ->where('id', $row->id)
                            ->update(['slug' => $slug]);
                    }
                });
        }

        Schema::table('categories', function (Blueprint $table) {
            try {
                $table->unique('slug', 'categories_slug_unique');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            try {
                $table->dropUnique('categories_slug_unique');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('categories', 'meta')) {
                $table->dropColumn('meta');
            }

            if (Schema::hasColumn('categories', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};