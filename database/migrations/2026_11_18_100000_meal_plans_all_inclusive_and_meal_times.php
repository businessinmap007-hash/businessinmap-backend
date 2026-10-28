<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meal plans as Egyptian hotels sell them (owner, 2026-10-28): room only, breakfast (BB), half board (HB: breakfast + dinner), full
 * board (FB: three meals) and all inclusive (AI: every meal and soft drinks), priced per person a night. «نظام الوجبات» held the first
 * three; all inclusive joins it, on every trade that already offers full board.
 *
 * And the hotel's meal times — breakfast, lunch, dinner, each from … to … — which a guest reads on his booking for the plan he chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'نظام الوجبات')->value('id');
        $fullBoard = $group ? DB::table('options')->where('group_id', $group)->where('name_en', 'Full Board')->value('id') : null;

        if ($group && $fullBoard && ! DB::table('options')->where('group_id', $group)->where('name_en', 'All Inclusive')->exists()) {
            $id = DB::table('options')->insertGetId([
                'group_id' => $group, 'sort_order' => 4, 'name_ar' => 'شامل كليًا', 'name_en' => 'All Inclusive', 'created_at' => now(), 'updated_at' => now(),
            ]);

            // wherever full board is offered, all inclusive is too
            foreach (DB::table('category_child_option')->where('option_id', $fullBoard)->get() as $link) {
                DB::table('category_child_option')->insert(['child_id' => $link->child_id, 'category_id' => $link->category_id, 'option_id' => $id, 'reorder' => (int) $link->reorder + 1]);
            }
        }

        Schema::create('business_meal_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
                $table->string($meal . '_from', 5)->nullable();
                $table->string($meal . '_to', 5)->nullable();
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_meal_times');

        $id = DB::table('options')->where('name_en', 'All Inclusive')->value('id');

        if ($id) {
            DB::table('category_child_option')->where('option_id', $id)->delete();
            DB::table('options')->where('id', $id)->delete();
        }
    }
};
