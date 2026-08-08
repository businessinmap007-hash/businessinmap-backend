<?php

namespace Database\Seeders;
use Database\Seeders\ServiceFeesSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([

           // The treasury must exist before anything can charge a fee into it.
           PlatformAccountSeeder::class,
       PlatformServiceSeeder::class,
           ScheduleVehicleTypesSeeder::class,
           WorldCountriesSeeder::class,
           // ServiceFeesSeeder::class,
         
            // CategorySeeder::class,
            // CategoryOptionSeeder::class,
            // CategoryUserSeeder::class,
            // CategoryTargetSeeder::class,
           // CategoryPlatformServiceSeeder — retired 2026-07-13: it seeded the
           // dropped category_booking_profiles table (removed 2026_03_19) and
           // legacy root-level (child_id NULL) links for hotel/restaurant/sports.
           // Service enablement is now child-level via services-bulk + the branch
           // child-seeders below.
           DeliveryBranchesSeeder::class,
           DeliveryChildBranchesSeeder::class,
           BookingBranchesSeeder::class,
           BookingChildBranchesSeeder::class,
           MenuBranchesSeeder::class,
           MenuChildBranchesSeeder::class,
           // Must follow the booking and menu branch seeders above: they build
           // the 294 + 45 item types this collapses into 4 kinds + 5 selling
           // surfaces, and its prune step then removes what nothing references.
           // Without it a full seed rebuilds the vocabulary the types no longer
           // own — see the class docblock for why the type says HOW, not WHAT.
           ServiceKindsCollapseSeeder::class,

           // The same per-child assignment, on its own, so drift can be undone
           // without re-running the whole collapse. Harmless right after it —
           // it reports «already correct» and writes nothing.
           BookingChildKindsSeeder::class,

           // What each kind is MEASURED in — a stay in days, a clinic slot in
           // minutes. Must follow the collapse: it writes onto the type rows.
           BookingKindGranularitySeeder::class,

           // Renting, on the same mechanism as a hotel stay.
           RentalEnablementSeeder::class,

           RetailBranchesSeeder::class,
           RetailChildBranchesSeeder::class,
           RetailProductTaxonomySeeder::class,
           BusinessOffersEnablementSeeder::class,

           // Last, so it sees the final link/config set: an active config with
           // NO allowed_item_types reads as «every type», not «none». Bounds
           // those from their declared branches and touches nothing else.
           BoundUnboundedConfigsSeeder::class,

           // «دفع مسبق» belongs to carriers. Last of the option seeders, so
           // whatever else granted the payment group has already run and this
           // has the final word.
           PrepaymentScopeSeeder::class,

           // What the remodels left behind: options nothing can reach, and
           // service wiring for children detached from every root. Runs after
           // everything that could legitimately re-attach a child.
           TaxonomyDeadWeightSeeder::class,

           // Order within a price-role tier. The tiers themselves are sorted
           // by OptionGroup::ROLE_RANK and are not stored.
           OptionGroupDisplayOrderSeeder::class,




            //  EgyptCountriesSeeder::class,
            //  EgyptGovernoratesSeeder::class,
            //  EgyptCitiesSeeder::class,
        ]);
    }

}
 
     
     
     
     
     
     
    //  $this->call([
    //         CategorySeeder::class,
    //         CategoryOptionSeeder::class,
    //         CategoryUserSeeder::class,
    //         CategoryTargetSeeder::class,
    //     ]);
    