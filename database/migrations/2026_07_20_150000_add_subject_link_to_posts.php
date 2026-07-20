<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a post point at something the business actually sells, so a post can stop
 * being a dead end: read the offer, then act on it (open the menu at that item,
 * open the bookable unit).
 *
 * Deliberately NOT a second offering system. There is no price, discount,
 * quantity or audience here — that is what `commercial_offers` is for, and
 * duplicating it is the mistake this codebase already paid for once. A post
 * REFERENCES an offering; it never becomes one.
 *
 * The link is optional and will stay that way: menu_items and catalog_products
 * are both empty today, so for now a post's own title is still the only thing
 * most businesses have to advertise with.
 *
 * `subject_type` holds a SHORT key ('menu_item'), not a class name — matching
 * commercial_offers.offerable_type. A Laravel morph map is not an option: the
 * existing morph columns store full class names (images.imageable_type has 1039
 * such rows), and enforcing a global map would break them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('subject_type', 50)->nullable()->after('type');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');

            $table->index(['subject_type', 'subject_id'], 'posts_subject_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_subject_index');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
