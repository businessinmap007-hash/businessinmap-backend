<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Taxonomy Lab — the isolated rebuild workspace for the services + options
 * organization. It works only against the `_new` sandbox tables (see the
 * create-taxonomy-lab-tables migration and the `taxonomy-lab:seed` command);
 * the live tables and their screens are never touched from here.
 *
 * This landing is the phase overview: what has been copied, and how much of the
 * grouping we have rebuilt so far. The per-service and per-option-group builders
 * are added on top of it step by step.
 */
class TaxonomyLabController extends Controller
{
    public function index()
    {
        $stats = [
            'services'        => (int) DB::table('platform_services_new')->count(),
            'serviceTypes'    => (int) DB::table('platform_service_item_types_new')->count(),
            'serviceBranches' => (int) DB::table('platform_service_item_groups_new')->count(),
            'typesGrouped'    => (int) DB::table('platform_service_item_group_type_new')->distinct()->count('item_type_id'),
            'options'         => (int) DB::table('options_new')->count(),
            'optionGroups'    => (int) DB::table('option_groups_new')->count(),
            'optionsGrouped'  => (int) DB::table('options_new')->whereNotNull('group_id')->count(),
            'childLinks'      => (int) DB::table('category_child_option_new')->count(),
        ];

        $stats['typesUngrouped']   = max(0, $stats['serviceTypes'] - $stats['typesGrouped']);
        $stats['optionsUngrouped'] = max(0, $stats['options'] - $stats['optionsGrouped']);

        return view('admin-v2.taxonomy-lab.index', ['stats' => $stats]);
    }

    /**
     * Reset the sandbox to a pristine mirror of the live atoms (groupings cleared),
     * so a messy rebuild attempt can be thrown away and restarted.
     */
    public function reset()
    {
        Artisan::call('taxonomy-lab:seed');

        return back()->with('status', 'تمت إعادة ضبط المستودع الرملي من الجداول الحيّة — التجميعات فُرِّغت لإعادة البناء.');
    }
}
