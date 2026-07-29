<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Taxonomy Lab "lists" — the clean, unified, hierarchical structure we build
 * on top of the sandbox atoms.
 *
 * A `lab_list` is a section (قائمة) that can nest (parent_id) — e.g. «سيارات»
 * holds sub-lists «ماركات سيارات» and «ماركات موتوسيكلات». A `lab_list_item`
 * places one atom into a list, and the atom may come from ANY of the sources we
 * work with — an option (`options_new`), a service item type
 * (`platform_service_item_types_new`), or a category child / medical specialty
 * (`category_children_master`) — which is the whole point: one list can unify
 * what used to be split across those systems.
 *
 * `key` marks lists built by the re-runnable seeder so it can rebuild only its
 * own lists and never clobber hand-built ones (null key = hand-built).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('lab_lists')->cascadeOnDelete();
            $table->string('key')->nullable()->unique();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_id');
        });

        Schema::create('lab_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('lab_lists')->cascadeOnDelete();
            $table->enum('source', ['option', 'item_type', 'category_child']);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['list_id', 'source', 'source_id'], 'lab_list_items_unique');
            $table->index(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_list_items');
        Schema::dropIfExists('lab_lists');
    }
};
