<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «أكمل دمج الأنواع القديمة تحت الأنواع العشرة» — المالك، 2026-10-04. The ~30 «kinds» written one group at a
 * time are what a group ADDS on top of its type (a stone's thickness, a fish's pieces per kilo): a FIELD SET.
 * Each now belongs to one of the ten detail types — the type of the groups that use it — and the admin
 * screen lists the kinds under their type instead of as thirty peers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_detail_profiles', 'detail_type')) {
            Schema::table('menu_detail_profiles', function (Blueprint $table) {
                $table->string('detail_type', 40)->nullable()->index();
            });
        }

        // A kind's type = the commonest type among the groups that use it; one nobody uses yet follows its code.
        $byCode = ['appliances' => 'tech', 'mobiles' => 'tech', 'computers' => 'tech', 'cars' => 'vehicles', 'fur' => 'furniture'];

        foreach (DB::table('menu_detail_profiles')->whereNull('detail_type')->get() as $profile) {
            $type = DB::table('option_groups')->where('menu_detail_profile_id', $profile->id)->whereNotNull('detail_type')
                ->select('detail_type', DB::raw('count(*) as n'))->groupBy('detail_type')->orderByDesc('n')->value('detail_type')
                ?: ($byCode[$profile->code] ?? 'basic');

            DB::table('menu_detail_profiles')->where('id', $profile->id)->update(['detail_type' => $type]);
        }

        // A group that uses a kind is of that kind's type.
        DB::table('option_groups as g')->join('menu_detail_profiles as p', 'p.id', '=', 'g.menu_detail_profile_id')
            ->whereNull('g.detail_type')->update(['g.detail_type' => DB::raw('p.detail_type')]);
    }

    public function down(): void
    {
    }
};
