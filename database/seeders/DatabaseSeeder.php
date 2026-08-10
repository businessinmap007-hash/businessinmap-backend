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

           // Nine clothing children become three: what a shop SELLS moves onto
           // the line options so one shop can carry clothes, shoes and bags.
           // Before the scope seeder, which reads the narrowed map.
           FashionRemodelSeeder::class,

           // Filing corrections: a child hanging from the root a customer would
           // never look in. Moves the child WITH its wiring; retires nothing.
           ChildRootMovesSeeder::class,

           // «بيت ضيافة» and «فندق عائم» after an accidental save emptied them.
           // Additive: it hands options back and never withdraws one, so it
           // cannot undo curation done since.
           HospitalityOptionRestoreSeeder::class,

           // A named list of services switched off where they obviously belong —
           // «مطعم» could not publish a menu. Never a heuristic: every row was
           // read and agreed with, so an admin's deliberate off stays off.
           ServiceReinstatementSeeder::class,

           // «ماركات السيارات» reused three more times — what a food, appliance
           // or sports-equipment trade deals in — plus «مركز حجامة» and its own
           // PRICED session list. Add-only: it never withdraws a curated option.
           TradeVocabularySeeder::class,

           // One trade under several roots — a spare-parts FACTORY could not
           // name a car brand while the shop next door named 43. Gives the
           // survivor every axis, then takes over the empty twins' roots.
           TradeAxesSeeder::class,

           // ورش ومراكز صيانة: twenty-four children, most of them one BENCH in
           // a garage. The bench becomes a priced option and the workshop
           // becomes the child. Root-scoped, so it must follow the moves above.
           WorkshopRemodelSeeder::class,

           // «بنود المنيو» was four vocabularies wearing one name. Moves options
           // between groups only — no child link is touched, so every merchant
           // keeps every heading he had.
           MenuBandSplitSeeder::class,

           // Four accessory children become one, and what KIND of accessory
           // becomes an option. After the remodel, before the detachments.
           AccessoryMergeSeeder::class,

           // «احذف س من أبناء ص» — a child taken off a root it does not belong
           // under. After the remodel, because two of its entries only make
           // sense once the workshop domains exist to receive the merchants.
           ChildRootDetachSeeder::class,

           // The same cure for «كوافير», which had gone further and made itself
           // a ROOT: the trade folds back under مهن وحرفيين, and رجالي/حريمي
           // stop being two children a family salon has to choose between.
           SalonRemodelSeeder::class,

           // The 18 children that carried only delivery + offers and so could
           // sell nothing at all: goods write their own menu, services take a
           // direct booking.
           UnsellableChildrenSeeder::class,

           // «التدريب والتغذية» on the shelf: a gym could deliver a plan and
           // had no way to be found for one or to price it.
           TrainingServiceSeeder::class,

           // «حجز بدون توصيل هو حجز وقت او مدة» — a rule, not a list. MUST come
           // after DeliveryChildBranchesSeeder, whose root-keyed map would
           // otherwise re-wire delivery onto the children this switches off.
           BookingWithoutDeliverySeeder::class,

           // «دفع مسبق» belongs to carriers. Last of the option seeders, so
           // whatever else granted the payment group has already run and this
           // has the final word.
           PrepaymentScopeSeeder::class,

           // The option links, service wiring and FEE rows held by children
           // detached from every root. Runs after everything that could
           // legitimately re-attach one.
           OrphanChildLinksCleanupSeeder::class,

           // Order within a price-role tier. The tiers themselves are sorted
           // by OptionGroup::ROLE_RANK and are not stored.
           OptionGroupDisplayOrderSeeder::class,

           // DEAD LAST, and it has to be: «انسحب البذرة، اتبع تنظيمي اليدوي».
           // Five of the broad option seeders consult the withdrawal record
           // before granting, but thirty-six others still write their own
           // curated lists without asking. This is the backstop — whatever the
           // chain granted, a recorded withdrawal takes away again. Anything
           // added after this line can undo a hand removal unnoticed.
           ChildOptionDecisionsSeeder::class,




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
    