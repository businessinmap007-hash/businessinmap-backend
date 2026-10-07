<?php

namespace App\Services;

use App\Models\OfferingOption;
use App\Models\OfferingOptionGroupSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * «تسعير نظام الوجبات منفصل كالإضافات في المطاعم» — what a guest may add to a room, and the price of a room's own
 * features, written once for the whole business (the same rows the booking engine and the guest's form already read).
 *
 *   add-ons   decided by the guest   «نظام الوجبات»: بلا وجبات / شامل الإفطار / نصف إقامة / إقامة كاملة — one choice by default
 *   features  carried by one room    «إطلالة الوحدة»: إطلالة على المسبح +150 — priced here once, ticked on the rooms that have it
 *
 * Which group is which comes from {@see BookingVocabularyRoles} (the merchant's declaration, else the platform's).
 */
final class BookingAddOnsService
{
    public function __construct(
        private readonly MerchantOfferingVocabulary $vocabulary,
        private readonly BookingVocabularyRoles $roles,
    ) {
    }

    /** @return Collection<string,Collection> group name → options */
    public function addOnVocabulary(User $business): Collection
    {
        return $this->vocabularyFor($business, BookingVocabularyRoles::ROLE_ADDON);
    }

    /** @return Collection<string,Collection> group name → options */
    public function featureVocabulary(User $business): Collection
    {
        return $this->vocabularyFor($business, BookingVocabularyRoles::ROLE_UNIT);
    }

    private function vocabularyFor(User $business, string $role): Collection
    {
        $id = (int) $business->id;
        $child = (int) ($business->category_child_id ?? 0);
        $root = (int) ($business->category_id ?? 0);

        return $this->roles->only(
            $this->vocabulary->for($id, $child, $root)['modifiers'],
            $id,
            $role,
            $this->vocabulary->everythingOffered($id, $child, $root)
        );
    }

    /** What the screen shows: every group with its options, each with the price the business wrote (or not). */
    public function payload(User $business): array
    {
        $current = $business->currentOfferingAdjustments();
        $selection = OfferingOptionGroupSetting::forOwnOffering((new User)->getMorphClass(), (int) $business->id);

        $groups = function (Collection $vocabulary, bool $withSelection) use ($current, $selection) {
            return $vocabulary->map(function ($options, $groupName) use ($current, $selection, $withSelection) {
                $groupId = (int) ($options->first()->group_id ?? 0);

                return array_filter([
                    'group_id' => $groupId,
                    'group' => $groupName,
                    'selection_type' => $withSelection ? ($selection[$groupId] ?? OfferingOptionGroupSetting::defaultFor($groupId)) : null,
                    // the meals of a Day use are added to the DAY's price, a night's meal plan to each night's
                    'applies_to' => $withSelection ? (in_array($groupName, BookingVocabularyRoles::DAY_USE_GROUPS, true) ? 'day_use' : 'night') : null,
                    'options' => collect($options)->map(fn ($o) => [
                        'id' => (int) $o->id,
                        'name' => app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en),
                        'enabled' => isset($current[(int) $o->id]),
                        'adjust_type' => $current[(int) $o->id]['type'] ?? OfferingOption::ADJUST_AMOUNT,
                        'value' => $current[(int) $o->id]['value'] ?? 0,
                        'per_person' => $current[(int) $o->id]['per_person'] ?? false,
                    ])->values()->all(),
                ], fn ($v) => $v !== null);
            })->values()->all();
        };

        return [
            'add_ons' => $groups($this->addOnVocabulary($business), true),
            'features' => $groups($this->featureVocabulary($business), false),
        ];
    }

    /**
     * Writes what the screen sent. Rows of the business that this screen does not show are kept untouched —
     * `syncOfferingOptions` wipes then writes, so what is not sent back would silently vanish.
     *
     * @param  list<array<string,mixed>>  $addOns   `{option_id, enabled, value, adjust_type, per_person}`
     * @param  list<array<string,mixed>>  $features same shape
     * @param  array<int|string,string>  $selectionTypes  group id → single|multiple
     */
    public function save(User $business, array $addOns, array $features, array $selectionTypes): array
    {
        $shown = $this->addOnVocabulary($business)->flatten(1)->merge($this->featureVocabulary($business)->flatten(1))
            ->map(fn ($o) => (int) $o->id)->unique()->values();

        $current = $business->currentOfferingAdjustments();
        $chosen = collect(array_keys($current))->reject(fn ($id) => $shown->contains($id))->values();
        $adjustments = [];

        foreach ($current as $id => $adjust) {
            if (! $shown->contains($id)) {
                $adjustments[$id] = $adjust;
            }
        }

        foreach (array_merge($addOns, $features) as $row) {
            $id = (int) ($row['option_id'] ?? 0);

            if ($id <= 0 || ! $shown->contains($id) || empty($row['enabled'])) {
                continue;
            }

            $type = (string) ($row['adjust_type'] ?? OfferingOption::ADJUST_AMOUNT);
            $chosen->push($id);
            $adjustments[$id] = [
                'type' => in_array($type, OfferingOption::adjustTypes(), true) ? $type : OfferingOption::ADJUST_AMOUNT,
                'value' => round(max((float) ($row['value'] ?? 0), 0), 2),
                'per_person' => (bool) ($row['per_person'] ?? false),
            ];
        }

        $business->syncOfferingOptions(null, $chosen->unique()->values()->all(), $adjustments);
        $this->saveSelectionTypes($business, $selectionTypes);

        return $this->payload($business);
    }

    /** Single (radio) or multiple (checkbox), group by group — only for a group this business offers as an add-on. */
    private function saveSelectionTypes(User $business, array $byGroupId): void
    {
        $allowed = $this->addOnVocabulary($business)
            ->map(fn ($options) => (int) ($options->first()->group_id ?? 0))->filter()->values();
        $offeringType = (new User)->getMorphClass();

        foreach ($byGroupId as $groupId => $type) {
            $groupId = (int) $groupId;

            if (! $allowed->contains($groupId) || ! in_array($type, OfferingOptionGroupSetting::SELECTION_TYPES, true)) {
                continue;
            }

            // what matches the platform's default for the group is not stored — an extra row
            if ($type === OfferingOptionGroupSetting::defaultFor($groupId)) {
                OfferingOptionGroupSetting::query()
                    ->where('offering_type', $offeringType)->where('offering_id', $business->id)->where('option_group_id', $groupId)->delete();

                continue;
            }

            OfferingOptionGroupSetting::query()->updateOrCreate(
                ['offering_type' => $offeringType, 'offering_id' => $business->id, 'option_group_id' => $groupId],
                ['selection_type' => $type]
            );
        }
    }
}
