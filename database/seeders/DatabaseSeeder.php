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
           RetailBranchesSeeder::class,
           RetailChildBranchesSeeder::class,
           RetailProductTaxonomySeeder::class,
           BusinessOffersEnablementSeeder::class,

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
    