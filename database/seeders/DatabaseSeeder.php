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

           // Before every seeder that resolves a branch by key: the twelve
           // booking branches the collapse retired were later deleted from the
           // admin panel, and three seeders below still look them up by name.
           // It restores the ROW only — inactive and empty, which is what the
           // collapse left behind and never meant to remove.
           RetiredBranchRowsSeeder::class,

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
           FullServiceCentreSeeder::class,
           TalentChildSeeder::class,
           PlayerScoutSeeder::class,

           // «بنود المنيو» was four vocabularies wearing one name. Moves options
           // between groups only — no child link is touched, so every merchant
           // keeps every heading he had.
           MenuBandSplitSeeder::class,

           // …and «أقسام السوبر ماركت», which came out of that split, was five.
           // Must run AFTER it: it takes the source group apart, so the source
           // has to exist and be full first.
           GroceryAisleSplitSeeder::class,

           // …and «أصناف الخضار والفاكهة» is two stalls, not one list. Order
           // does not bind it to the two above — it takes a different group
           // apart — but it is kept beside them because it is the same move.
           ProduceAisleSplitSeeder::class,

           // Four accessory children become one, and what KIND of accessory
           // becomes an option. After the remodel, before the detachments.
           AccessoryMergeSeeder::class,

           // A keeper that has swallowed a sibling has to answer to the wider
           // name. Runs before the detachments, which name their destination by
           // the NEW name — and it was reachable only by hand until 2026-08-23,
           // so «مستلزمات مطاعم» would have folded into a child that did not
           // exist yet.
           ChildRenameSeeder::class,

           // …and a keeper standing in fewer storefronts than the sibling it is
           // about to swallow has to take the missing ones first: a fold cannot
           // cross a root, because the merchant keeps his `category_id`.
           ChildRootAttachSeeder::class,

           // «احذف س من أبناء ص» — a child taken off a root it does not belong
           // under. After the remodel, because two of its entries only make
           // sense once the workshop domains exist to receive the merchants.
           ChildRootDetachSeeder::class,

           // The same cure for «كوافير», which had gone further and made itself
           // a ROOT: the trade folds back under مهن وحرفيين, and رجالي/حريمي
           // stop being two children a family salon has to choose between.
           SalonRemodelSeeder::class,
           SalonRootRestoreSeeder::class,

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

           // What each child of «سيارات» may put a price on. It was never in
           // this list, so its rulings had never run: a car wash, a parking
           // garage and a tow truck were each offering «نقل أفراد» and
           // «ليموزين VIP», and the data file had said `off` for all three the
           // whole time. MUST come after ServiceKindsCollapseSeeder — it used
           // to write the price fallback type `category` into the kinds field
           // and the two would have fought over root 13 forever.
           ChildServiceScopeSeeder::class,

           // «منطقة عمل مشتركة» was classified `units` and had no line group to
           // name a unit with — the flag demanded a list whose words did not
           // exist. AFTER the collapse and after the branch seeder, both of
           // which have an opinion about its kind.
           CoworkingWorkspaceOptionsSeeder::class,

           // A child that cannot name its trade — «مكاتب» had six such children and
           // «تكنولوجيا» all three: a printing house could not say it prints.
           // Consults the withdrawal record, so it may run anywhere; kept beside
           // the coworking seeder, which closed the same gap on the same day.
           ChildTradeVocabulariesSeeder::class,

           /*
            * The private half of the car market, 2026-08-17. It creates a
            * CHILD, so it must run before anything that reads the root's
            * children — and after the vocabulary seeder, because it copies its
            * words from «معرض سيارات» and one of them («كسر زيرو») is minted
            * there.
            *
            * VehicleDealTypeSeeder beside it splits «بيع وشراء» into بيع and
            * شراء for the showrooms alone. Neither was in this list before —
            * VehicleDealTypeSeeder had been running by hand since 2026-08-08,
            * which is the same defect PropertyModifierOptionsSeeder was found
            * with the day before: a seeder that self-heals in a chain it is not
            * part of heals nothing.
            */
           VehicleDealTypeSeeder::class,
           CarOwnerListingSeeder::class,

           // Sixteen children that could say what they DO and not what they are
           // made of, lent an existing list from a sibling. After the seeder
           // above, because a donor must already hold what it lends.
           ChildVocabularyBorrowSeeder::class,

           // «دفع مسبق» belongs to carriers. Last of the option seeders, so
           // whatever else granted the payment group has already run and this
           // has the final word.
           PrepaymentScopeSeeder::class,

           // The option links, service wiring and FEE rows held by children
           // detached from every root. Runs after everything that could
           // legitimately re-attach one.
           OrphanChildLinksCleanupSeeder::class,

           /*
            * شكل عملية الحجز — إقامة، طاولة، مدّة، استشارة، كورس، موعد.
            *
            * بعد كل بذرةٍ تكتب `allowed_item_types`، لأن هذه تصف شكل العملية
            * لا ما يُباع فيها، وتشتقّ المفاتيح الستّة القديمة من النمط. لو
            * سبقتها بذرةُ فروعٍ لأعادت `newConfigDefaults()` كتابة
            * `requires_bookable_item => true` فوق ما قرّره النمط — وهو الخطأ
            * نفسه الذى جعل خمسين إعدادًا يبيع موعدًا ويطالب بوحدةٍ محجوزة،
            * بنى منها ٢٥٤ نشاطًا صفرًا.
            */
           BookingPatternSeeder::class,

           /*
            * منيو طعامٍ لمكانِ ترفيهٍ يبيع شايًا وساندويتشًا بجوار خدمته
            * الأصلية. بعد بذرات الفروع لأنه يكتب `allowed_item_types` بنفسه،
            * وبعد الأنماط لأنه لا يمسّ الحجز أصلًا فلا ترتيبَ بينهما يُخشى.
            */
           SnackMenuSeeder::class,

           /*
            * One group, one question. Moves `options.group_id` and nothing
            * else — no child link is touched, so this is safe anywhere after
            * the option seeders and it must run before the roles below: a row
            * that has not reached its group yet gets the role of the group it
            * is still sitting in.
            *
            * It had no place in a full seed either, the same absence
            * OptionPriceRolesSeeder had. Four groups' worth of splitting lived
            * only in the live database, so `php artisan db:seed --class=…` on a
            * fresh copy produced a hotel amenity list still holding its own
            * view and meal plan.
            */
           /*
            * «سيراميك وأدوات صحية» #138 — the ceramics trade the platform did
            * not have, and the sanitary ware nobody could find. Renames the
            * child, stands it under «معارض» and «المحلات» beside the two roots
            * it already had, and copies its service shape from «صينى وخزف»,
            * which stands under all four. Before the option seeders: the
            * vocabulary in factory_child_vocabularies.php is keyed by child id,
            * but every branch map naming this child is keyed by NAME.
            */
           CeramicsAndSanitaryWareSeeder::class,

           /*
            * «مفاتيح» under «مصانع» becomes «كابلات وقواطع كهرباء» — one child
            * row was two trades, and a name cannot be root-scoped. Before the
            * option seeders, which name the new child in
            * factory_child_vocabularies.php and need it to exist.
            */
           CableAndSwitchgearFactorySeeder::class,

           OptionGroupSplitSeeder::class,

           /*
            * What each group DOES — line, modifier or descriptive — from
            * `option_price_roles.php`, which is the declared authority on it
            * and had no place in a full seed at all until 2026-08-16.
            *
            * That absence was live: several of the option seeders above write
            * a role of their own when they touch a group, and running
            * ChildTradeVocabulariesSeeder on its own is enough to turn «أنواع
            * الزجاج» and «أنواع الأجهزة الرياضية» from modifier into line. A
            * group's role decides where it surfaces — a line is offered to the
            * merchant and becomes a priced row, a descriptive only ever filters
            * a search — so a flipped role puts «سيكوريت» on the pricing screen
            * as a thing to sell rather than a property of the pane.
            *
            * Nothing downstream corrected it, which meant `php artisan db:seed`
            * ended with roles the authority file disagrees with, and the only
            * reason the database was right is that the roles had been restored
            * by hand after each of those runs.
            *
            * Placed after every seeder that writes a role and before the
            * display order below, which sorts BY the role.
            */
           OptionPriceRolesSeeder::class,

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
    