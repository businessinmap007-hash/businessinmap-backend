<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Support\Collection;

/**
 * One business's whole menu, arranged — «قسم البيتزا تحته كل أنواع البيتزا،
 * وفاكهة تحتها كل الفواكه» — المالك، 2026-08-24.
 *
 * ── Three levels, and each one already existed ──────────────────────────────
 *
 *   قسم    the option GROUP        «الفواكه» · «بنود المنيو»
 *   بند    the line OPTION         «مانجو» · «بيتزا»
 *   صنف    the merchant's row      «مانجو عويس — ١٢٠ / كجم»
 *
 * Nothing new is stored. The arrangement is READ from the vocabulary the
 * platform already curated per child, so a menu cannot drift from the words
 * its trade was given: a second grouping kept beside the taxonomy would be a
 * second answer to «ما الذي يبيعه هذا؟», and the two would disagree the first
 * time either was edited.
 *
 * ── The empty band is the point ─────────────────────────────────────────────
 *
 * A review screen that lists only what a merchant HAS is a list he already
 * has. What he cannot see is the band he was given and never filled — a
 * pizzeria with no «مشروبات باردة», a greengrocer with eleven fruits out of
 * eighteen. So every band his vocabulary allows appears, with its count, and
 * an empty one is a finding rather than an absence.
 *
 * ── It must place an item where the CUSTOMER sees it ────────────────────────
 *
 * `MenuItem::heading()` decides that: hand-written section first, then the
 * option combination, then the item type. This follows the same precedence —
 * a review that grouped by its own rule would be a second opinion about a
 * screen it is supposed to be reviewing. The one difference is deliberate: a
 * combination («مانجو — بلدي») is shown as an ITEM under its line, not as a
 * band of its own, because at review time «كل أنواع المانجو» is the question.
 */
class MenuOutline
{
    public const SOURCE_SECTION = 'section';
    public const SOURCE_VOCABULARY = 'vocabulary';
    public const SOURCE_ITEM_TYPE = 'item_type';
    public const SOURCE_UNPLACED = 'unplaced';

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    /**
     * @return array{
     *     business: User,
     *     sections: array<int,array<string,mixed>>,
     *     totals: array<string,int>
     * }
     */
    public function for(User $business): array
    {
        $items = MenuItem::query()
            ->where('business_id', $business->id)
            // `heading()` reads both, and reading them per item is an N+1 for
            // every row on the page.
            ->with(['section', 'offeringOptions.option'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $sections = $this->skeleton($business);
        $unplaced = [];

        foreach ($items as $item) {
            [$sectionKey, $bandKey] = $this->placeOf($item);

            if (! isset($sections[$sectionKey]['bands'][$bandKey])) {
                // A band nothing offered him — a hand-written section, an item
                // type, or a line he was allowed and has since lost. It is
                // still on the menu, so it is still on the review.
                $sections[$sectionKey] ??= $this->emptySection($sectionKey, $this->strayLabel($item, $sectionKey));
                $sections[$sectionKey]['bands'][$bandKey] = $this->emptyBand($bandKey, $this->bandLabel($item), null);
                $sections[$sectionKey]['bands'][$bandKey]['unexpected'] = true;
            }

            $sections[$sectionKey]['bands'][$bandKey]['items'][] = $item;

            if ($sectionKey === self::SOURCE_UNPLACED) {
                $unplaced[] = $item;
            }
        }

        $sections = $this->tidy($sections);

        return [
            'business' => $business,
            'sections' => $sections,
            'totals' => $this->totals($sections, $items, $unplaced),
        ];
    }

    /**
     * The bands this business was GIVEN — before a single item is placed.
     *
     * @return array<string,array<string,mixed>>
     */
    private function skeleton(User $business): array
    {
        $sections = [];

        // The merchant's own headings come first: he wrote them, and the
        // customer sees them ahead of anything the platform arranged.
        $hand = MenuSection::query()
            ->where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($hand as $row) {
            $key = 'section:' . $row->id;

            $sections[$key] = [
                'key' => $key,
                'label' => (string) $row->loc('name'),
                'source' => self::SOURCE_SECTION,
                'is_active' => (bool) $row->is_active,
                'bands' => [],
            ];
        }

        $lines = $this->vocabulary->for(
            (int) $business->id,
            (int) $business->category_child_id,
            (int) $business->category_id
        )['lines'];

        foreach ($lines as $groupName => $options) {
            /** @var Collection $options */
            $first = $options->first();
            $key = 'group:' . (int) ($first->group_id ?? 0);

            $sections[$key] ??= [
                'key' => $key,
                'label' => (string) $groupName,
                'source' => self::SOURCE_VOCABULARY,
                'is_active' => true,
                'bands' => [],
            ];

            foreach ($options as $option) {
                $bandKey = 'line:' . (int) $option->id;

                $sections[$key]['bands'][$bandKey] = $this->emptyBand(
                    $bandKey,
                    (string) ($option->name_ar ?: $option->name_en),
                    (int) $option->id
                );
            }
        }

        return $sections;
    }

    /**
     * Where this item sits, in the customer's terms.
     *
     * @return array{0:string,1:string} [sectionKey, bandKey]
     */
    private function placeOf(MenuItem $item): array
    {
        $section = $item->section;

        if ($section && $section->is_active) {
            $line = $item->lineOption();

            return [
                'section:' . $section->id,
                $line ? 'line:' . $line->id : 'section-loose:' . $section->id,
            ];
        }

        $line = $item->lineOption();

        if ($line) {
            return ['group:' . (int) $line->group_id, 'line:' . (int) $line->id];
        }

        if ($item->item_type) {
            return ['types', 'type:' . $item->item_type];
        }

        return [self::SOURCE_UNPLACED, 'none'];
    }

    /** @return array<string,mixed> */
    private function emptyBand(string $key, string $label, ?int $optionId): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'option_id' => $optionId,
            'items' => [],
            'unexpected' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function emptySection(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'source' => str_starts_with($key, 'group:') ? self::SOURCE_VOCABULARY
                : ($key === 'types' ? self::SOURCE_ITEM_TYPE : self::SOURCE_UNPLACED),
            'is_active' => true,
            'bands' => [],
        ];
    }

    private function strayLabel(MenuItem $item, string $sectionKey): string
    {
        if ($sectionKey === 'types') {
            return __('حسب نوع الصنف');
        }

        if ($sectionKey === self::SOURCE_UNPLACED) {
            return __('غير مصنّف');
        }

        $line = $item->lineOption();

        return $line && $line->group
            ? (string) ($line->group->name_ar ?: $line->group->name_en)
            : __('خارج المفردات');
    }

    /**
     * A band's name, in Arabic, whatever the reader's locale.
     *
     * `Option::displayName()` follows the app locale and the vocabulary keys
     * its groups by `name_ar` — so mixing the two named the SAME band «بيتزا»
     * when the skeleton drew it and «Pizza» when an item created it, and the
     * screen would have shown one heading with two names depending on which
     * path got there first.
     */
    private function bandLabel(MenuItem $item): string
    {
        $line = $item->lineOption();

        if ($line) {
            return (string) ($line->name_ar ?: $line->name_en);
        }

        if ($item->item_type) {
            return MenuItem::itemTypeLabel($item->item_type);
        }

        return __('بلا بند');
    }

    /**
     * Sections in reading order, bands with their counts.
     *
     * @param  array<string,array<string,mixed>>  $sections
     * @return array<int,array<string,mixed>>
     */
    private function tidy(array $sections): array
    {
        $rank = [
            self::SOURCE_SECTION => 0,
            self::SOURCE_VOCABULARY => 1,
            self::SOURCE_ITEM_TYPE => 2,
            self::SOURCE_UNPLACED => 3,
        ];

        $out = [];

        foreach ($sections as $section) {
            $bands = [];
            $items = 0;
            $filled = 0;

            foreach ($section['bands'] as $band) {
                $band['count'] = count($band['items']);
                $items += $band['count'];
                $filled += $band['count'] > 0 ? 1 : 0;
                $bands[] = $band;
            }

            // Filled bands first inside a section: a review reads what is
            // there, then what is missing.
            usort($bands, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']));

            $section['bands'] = $bands;
            $section['items'] = $items;
            $section['filled_bands'] = $filled;
            $section['empty_bands'] = count($bands) - $filled;

            $out[] = $section;
        }

        usort($out, function ($a, $b) use ($rank) {
            return [$rank[$a['source']] ?? 9, -$a['items']] <=> [$rank[$b['source']] ?? 9, -$b['items']];
        });

        return $out;
    }

    /**
     * @param  array<int,array<string,mixed>>  $sections
     * @return array<string,int>
     */
    private function totals(array $sections, Collection $items, array $unplaced): array
    {
        $bands = 0;
        $filled = 0;

        foreach ($sections as $section) {
            $bands += count($section['bands']);
            $filled += $section['filled_bands'];
        }

        return [
            'items' => $items->count(),
            'sections' => count($sections),
            'bands' => $bands,
            'filled_bands' => $filled,
            'empty_bands' => $bands - $filled,
            'unplaced' => count($unplaced),
        ];
    }
}
