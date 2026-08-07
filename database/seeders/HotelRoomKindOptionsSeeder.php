<?php

namespace Database\Seeders;

use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * What a hotel actually sells, and the repair that needed it.
 *
 *   php artisan db:seed --class=HotelRoomKindOptionsSeeder
 *
 * «فندق» carried NO line option at all — the room kind lived in the item type
 * (`single_room`, `suite`, `villa` …). When ServiceKindsCollapseSeeder folded
 * those into «حجز فندق», six priced rows landed on one key and
 * `bsp_business_child_service_item_line_unique` allows one row per
 * (business, child, service, kind, LINE OPTION). Five collided. That seeder
 * refused to merge them — merging destroys five prices — and left them on
 * their retired keys for this.
 *
 * The room kind is a LINE by the price test: a customer books a جناح, and the
 * price is the جناح's. «إطلالة بحرية» stays a modifier beside it.
 *
 * «شقة» and «ڤيلا» are NOT created here. They already exist as line options in
 * «عقارات وممتلكات», and a hotel letting a whole flat is selling the same thing
 * an estate agent lists — one word for one thing is the point of the whole
 * vocabulary. The merchant simply sees them under their own group.
 *
 * Owner-approved list, exactly six. Idempotent, and the repair only touches
 * rows still sitting on a retired room key.
 */
class HotelRoomKindOptionsSeeder extends Seeder
{
    /*
     * Was «فئات الغرف» until the owner merged the room kinds into the existing
     * «الغرف» (2026-08-05), which already held استوديو/غرفة/غرفتين for property
     * listings. Renaming it here was not cosmetic: this seeder resolves the
     * group by name and CREATES it when absent, so on the next run it would
     * have rebuilt «فئات الغرف» and pulled the six kinds back out of the merged
     * list — undoing the merge and splitting one vocabulary in two again.
     *
     * The merged group is the better home anyway, and for the reason the
     * docblock above already gives about «شقة» and «ڤيلا»: a hotel's جناح and a
     * flat's ثلاث غرف are the same kind of answer to the same question, and one
     * word for one thing is the point.
     */
    private const GROUP_AR = 'الغرف';

    private const GROUP_EN = 'Rooms';

    /** The children that let a room by the night. */
    private const CHILDREN = ['فندق', 'شقق فندقية', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي'];

    /**
     * The six that carry the stranded-price repair — created always, LINKED
     * only where the room makes sense.
     *
     * They were linked to all six children unconditionally until 2026-08-05,
     * which meant the seeder handed «نُزل / هوستل» a suite and re-handed it one
     * every run, undoing the owner's curation each time. A seeder that puts
     * back what an admin deliberately removed is fighting the person using it —
     * the same rule LinkCategoryChildrenToOptionsSeeder states for itself — so
     * the scope lives here, in data, where it can be argued with.
     *
     * Creation stays unconditional because repairStrandedPrices() maps a
     * retired item-type key onto the option id: the option must exist to be
     * mapped even where no child is offered it.
     *
     * `null` means every child in CHILDREN.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>|null}>
     */
    private const KINDS = [
        // A serviced apartment is not sold by bed count — see APARTHOTEL below.
        'single_room' => ['غرفة فردية', 'Single Room', ['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],
        'double_room' => ['غرفة مزدوجة', 'Double Room', ['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],
        'family_room' => ['غرفة عائلية', 'Family Room', null],

        // A hostel has no suite: it sells a bed, or a plain private room.
        'suite' => ['جناح', 'Suite', ['فندق', 'شقق فندقية', 'منتجع', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],

        // These two live in «عقارات وممتلكات», reused rather than recreated —
        // see the class docblock.
        //
        // NOT a city hotel's stock. In the international room-type standard a
        // hotel sells rooms and suites; whole apartments belong to the
        // aparthotel classification and whole villas to the resort one, which
        // is precisely why «شقق فندقية» and «منتجع» exist as separate children.
        // A hotel with a residences wing is a resort by another name.
        'villa' => ['ڤيلا', 'Villa', ['منتجع']],
        'apartment' => ['شقة', 'Apartment', ['شقق فندقية', 'منتجع']],
    ];

    /**
     * The rest of the room vocabulary, owner-approved 2026-08-05, scoped to the
     * children that actually let each thing.
     *
     * KINDS above is only the six that carried the stranded-price repair. The
     * hotel branch originally held FIFTEEN types — recovered verbatim from
     * database/sql/bim_2_7_16_service_catalog_item_type_seeds.sql, which is why
     * جناح ملكي and جناح تنفيذي are spelled as they were rather than reinvented
     * — and the collapse left only six reachable. These restore the missing
     * eight and complete the list to the international standard.
     *
     * Scoped, not universal: a hostel sells a bed in a shared room and never a
     * royal suite, a resort sells a chalet and a hotel does not. Handing every
     * child the whole list is what made «قاعات ومناسبات» hold 39 entries and a
     * gym get offered football pitches.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    private const CATALOGUE = [
        // Rooms — anywhere that rents a private room by the night.
        // A hostel grades by dorm size, not by «قياسية» — that is hotel language.
        'standard_room' => ['غرفة قياسية', 'Standard Room', ['فندق', 'منتجع', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],
        'superior_room' => ['غرفة سوبيريور', 'Superior Room', ['فندق', 'منتجع', 'فندق عائم / بوت نيلي']],
        'deluxe_room' => ['غرفة ديلوكس', 'Deluxe Room', ['فندق', 'منتجع', 'فندق عائم / بوت نيلي']],
        // Back on نُزل: a twin private room is the commonest thing a hostel
        // sells after the bed. What the owner removed in August was its SUITE.
        'twin_room' => ['غرفة توأم', 'Twin Room', ['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],
        'triple_room' => ['غرفة ثلاثية', 'Triple Room', ['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],
        'quad_room' => ['غرفة رباعية', 'Quad Room', ['فندق', 'منتجع', 'نُزل / هوستل', 'بيت ضيافة']],
        'connecting_room' => ['غرفة متصلة', 'Connecting Room', ['فندق', 'منتجع']],
        'accessible_room' => ['غرفة ذوي احتياجات خاصة', 'Accessible Room', ['فندق', 'منتجع', 'بيت ضيافة', 'فندق عائم / بوت نيلي']],

        // Suites — the graded ladder a hotel prices upward.
        'junior_suite' => ['جناح جونيور', 'Junior Suite', ['فندق', 'منتجع', 'فندق عائم / بوت نيلي']],
        'executive_suite' => ['جناح تنفيذي', 'Executive Suite', ['فندق', 'منتجع']],
        'presidential_suite' => ['جناح رئاسي', 'Presidential Suite', ['فندق', 'منتجع', 'فندق عائم / بوت نيلي']],
        'royal_suite' => ['جناح ملكي', 'Royal Suite', ['فندق', 'منتجع']],
        'penthouse' => ['بنتهاوس', 'Penthouse', ['فندق', 'شقق فندقية', 'منتجع']],

        // Standalone units — a resort's own stock. A city hotel does not let a
        // chalet, and a guest house that does is running a resort.
        'chalet' => ['شاليه', 'Chalet', ['منتجع']],
        'bungalow' => ['بنجلو', 'Bungalow', ['منتجع']],

        // What a hostel actually sells, and could not say before.
        'dorm_bed' => ['سرير في غرفة مشتركة', 'Dorm Bed', ['نُزل / هوستل']],

        /*
        |----------------------------------------------------------------------
        | The aparthotel, which was selling «غرفة فردية»
        |----------------------------------------------------------------------
        | «شقق فندقية» carried four entries, all of them bed counts, which is
        | the wrong axis entirely: a serviced apartment is sold by UNIT SIZE —
        | studio, one bedroom, two bedrooms, penthouse — exactly as every
        | aparthotel brand lists it. Those words already exist in «الغرف»,
        | where the owner merged the hotel kinds into the property-listing
        | vocabulary on 2026-08-05; this is the merge finally paying off.
        */
        'studio' => ['استوديو', 'Studio', ['فندق', 'شقق فندقية', 'منتجع']],
        'one_bedroom' => ['غرفة', 'One Bedroom', ['شقق فندقية']],
        'two_bedroom' => ['غرفتين', 'Two Bedrooms', ['شقق فندقية']],
        'three_bedroom' => ['ثلاث غرف', 'Three Bedrooms', ['شقق فندقية']],
        'four_bedroom' => ['أربع غرف', 'Four Bedrooms', ['شقق فندقية']],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $children = DB::table('category_children_master')
                ->whereIn('name_ar', self::CHILDREN)
                ->pluck('name_ar', 'id');

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن فندقي — لم يُربط شيء.');

                return;
            }

            $groupId = $this->group();
            $created = $linked = 0;
            $optionOf = [];

            // childId => option ids this file says that child may let. Built as
            // we link, then used to withdraw everything it does NOT say.
            $declared = [];

            foreach (array_values(self::KINDS) as $i => [$ar, $en, $childNames]) {
                $optionId = $this->option($ar, $en, $groupId, $created);
                $optionOf[array_keys(self::KINDS)[$i]] = $optionId;

                $scoped = $childNames === null
                    ? $children
                    : $children->filter(fn ($name) => in_array($name, $childNames, true));

                if ($scoped->isNotEmpty()) {
                    $linked += $this->link($optionId, $scoped, $i);
                }

                foreach ($scoped->keys() as $childId) {
                    $declared[(int) $childId][] = $optionId;
                }
            }

            // The rest of the vocabulary, each entry only to the children that
            // let that particular thing — see CATALOGUE.
            $sort = count(self::KINDS);

            foreach (self::CATALOGUE as [$ar, $en, $childNames]) {
                $optionId = $this->option($ar, $en, $groupId, $created);

                $scoped = $children->filter(fn ($name) => in_array($name, $childNames, true));

                if ($scoped->isNotEmpty()) {
                    $linked += $this->link($optionId, $scoped, $sort);
                }

                foreach ($scoped->keys() as $childId) {
                    $declared[(int) $childId][] = $optionId;
                }

                $sort++;
            }

            [$repaired, $stuck] = $this->repairStrandedPrices($optionOf);
            [$pruned, $held] = $this->prune($children, $declared);

            $this->command?->info('Hotel room kinds:');
            $this->command?->line("  - خيارات جديدة : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line("  - روابط أُزيلت : {$pruned}");
            $this->command?->line("  - أسعار أُصلحت : {$repaired}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));

            if ($held !== []) {
                $this->command?->warn('  ! أُبقيت رغم خروجها عن النطاق (مُسعَّرة فعلًا): ' . implode('، ', $held));
            }

            if ($stuck !== []) {
                $this->command?->warn('  ! أسعار مكرّرة لنفس الفئة بقيت كما هي: ' . implode('، ', $stuck));
            }
        });
    }

    /**
     * Give every price still on a retired room key the option that says which
     * room it is, then move it onto «حجز فندق». The line is what makes the
     * unique key admit six rows where it admitted one.
     *
     * @param  array<string,int>  $optionOf
     * @return array{0:int,1:array<int,string>}
     */
    private function repairStrandedPrices(array $optionOf): array
    {
        $stayKey = 'booking_stay';
        $repaired = 0;
        $stuck = [];

        $rows = BusinessServicePrice::query()
            ->whereIn('bookable_item_type', array_keys(self::KINDS))
            ->get();

        foreach ($rows as $row) {
            $optionId = $optionOf[(string) $row->bookable_item_type] ?? null;

            if (! $optionId) {
                continue;
            }

            $taken = BusinessServicePrice::query()
                ->where('business_id', $row->business_id)
                ->where('child_id', $row->child_id)
                ->where('service_id', $row->service_id)
                ->where('bookable_item_type', $stayKey)
                ->where('line_option_id', $optionId)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($taken) {
                $stuck[] = (string) $row->bookable_item_type;
                continue;
            }

            // syncOfferingOptions writes offering_options AND mirrors
            // line_option_id — it is the only thing allowed to set that column.
            $row->syncOfferingOptions((int) $optionId);
            $row->forceFill(['bookable_item_type' => $stayKey])->save();

            $repaired++;
        }

        return [$repaired, array_unique($stuck)];
    }

    private function group(): int
    {
        $id = DB::table('option_groups')->where('name_ar', self::GROUP_AR)->value('id');

        if ($id) {
            DB::table('option_groups')->where('id', $id)
                ->update(['price_role' => OptionGroup::ROLE_LINE, 'is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => self::GROUP_AR,
            'name_en' => self::GROUP_EN,
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'price_role' => OptionGroup::ROLE_LINE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Matched on name_en then name_ar; a found option keeps its group. This is
     * how «شقة» resolves to the existing option in «عقارات وممتلكات» (whose
     * name_en is 'Flat', not 'Apartment') instead of being duplicated.
     */
    private function option(string $ar, string $en, int $groupId, int &$created): int
    {
        $id = DB::table('options')->where('name_en', $en)->value('id')
            ?: DB::table('options')->where('name_ar', $ar)->value('id');

        if ($id) {
            return (int) $id;
        }

        $created++;

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Withdraw the room kinds this file does NOT give a child.
     *
     * Until now the seeder could only add. That is why it kept handing نُزل a
     * suite every run after the owner removed one by hand, and why «فندق» was
     * still offering a chalet: nothing ever took a link away, so the table was
     * the union of every version of this list that had ever existed. A seeder
     * that can only add is not a declaration, it is an accumulation.
     *
     * Scope is narrow on purpose — only the option ids THIS file names, and
     * only on the six hotel children. Nothing else in «الغرف», and nothing in
     * any other group, is touched.
     *
     * One thing outranks the list: a link a merchant is actually PRICING. The
     * vocabulary is what a merchant may choose from, so withdrawing a word it
     * has already sold in would leave a live priced row it can no longer edit.
     * Those are kept and reported rather than cut.
     *
     * @param  \Illuminate\Support\Collection<int,string>  $children
     * @param  array<int,array<int,int>>  $declared
     * @return array{0:int,1:array<int,string>}
     */
    private function prune($children, array $declared): array
    {
        $managed = collect($declared)->flatten()->unique()->values();

        if ($managed->isEmpty()) {
            return [0, []];
        }

        $pruned = 0;
        $held = [];

        foreach ($children as $childId => $childName) {
            $childId = (int) $childId;
            $keep = collect($declared[$childId] ?? []);
            $extra = $managed->diff($keep);

            if ($extra->isEmpty()) {
                continue;
            }

            // Only what is actually linked can be withdrawn; the rest of $extra
            // was never given to this child in the first place.
            $linkedNow = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $extra->all())
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            if ($linkedNow->isEmpty()) {
                continue;
            }

            // Line options this child's own businesses have priced in.
            $priced = DB::table('business_service_prices as p')
                ->join('users as u', 'u.id', '=', 'p.business_id')
                ->where('u.category_child_id', $childId)
                ->whereIn('p.line_option_id', $linkedNow->all())
                ->pluck('p.line_option_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            foreach ($priced as $optionId) {
                $name = DB::table('options')->where('id', $optionId)->value('name_ar');
                $held[] = "{$childName} / {$name}";
            }

            $removable = $linkedNow->diff($priced);

            if ($removable->isEmpty()) {
                continue;
            }

            $pruned += DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $removable->all())
                ->delete();
        }

        return [$pruned, $held];
    }

    private function link(int $optionId, $children, int $order): int
    {
        $rows = [];

        foreach ($children->keys() as $childId) {
            $rows[] = [
                'child_id' => (int) $childId,
                'category_id' => 0,   // shared: follows the child under every root
                'option_id' => $optionId,
                'reorder' => $order,
            ];
        }

        return DB::table('category_child_option')->insertOrIgnore($rows);
    }
}
