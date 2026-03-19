<?php

namespace Database\Seeders;
use Database\Seeders\ServiceFeesSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([

       PlatformServiceSeeder::class,
           // ServiceFeesSeeder::class,
         
            // CategorySeeder::class,
            // CategoryOptionSeeder::class,
            // CategoryUserSeeder::class,
            // CategoryTargetSeeder::class,
           CategoryPlatformServiceSeeder::class,




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
    