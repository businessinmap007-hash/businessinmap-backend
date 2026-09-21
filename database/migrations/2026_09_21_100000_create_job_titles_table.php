<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A closed list of job titles (طباخ، ويتر، كاشير…) a business picks from when
 * it posts a vacancy, instead of typing a free-text title. Scoped the same
 * way the browse taxonomy is: a title belongs to one child, or to a whole
 * root (category_child_id null), or to everyone (both null — محاسب، سائق).
 * posts.title stays as the display text; posts.job_title_id is the
 * normalised handle the search/follow/report side keys on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->foreignId('category_child_id')->nullable()->constrained('category_children_master')->cascadeOnDelete();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_child_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->after('category_child_id')
                ->constrained('job_titles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');
        });

        Schema::dropIfExists('job_titles');
    }
};
