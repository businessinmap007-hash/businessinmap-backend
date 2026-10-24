<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\User;

/**
 * What a guest may add to a room type, and what each costs: «إفطار +٥٠»، «إقامة كاملة +١٥٠».
 *
 * They are the modifiers of the room's price row — plus those the business wrote once for the whole business (the meal plan is the
 * hotel's, a flat price that does not change with the room type). A feature the room already declares («إطلالة بحرية») is inside its
 * price, so it is not offered a second time. The same answer is read by the hotel's own page and by the page of a company that sells
 * the hotel's room through an allocation.
 */
final class UnitChoices
{
    /**
     * @param  \Illuminate\Support\Collection<int,BookableItem>  $inKind
     * @return array<int,array<string,mixed>>
     */
    public function for(?BusinessServicePrice $price, $inKind, bool $featuresAsVariants = false): array
    {
        if (! $price) {
            return [];
        }

        $declared = $inKind->flatMap(
            fn (BookableItem $unit) => $unit->relationLoaded('offeringOptions')
                ? $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')
                : $unit->modifierOptionIds()
        )->map(fn ($id) => (int) $id)->unique();

        /*
         * من سطر السعر ومن النشاط معًا.
         *
         * نظامُ الوجبات يسكن النشاطَ نفسه — سعرٌ ثابت لا يتغيّر بنوع الغرفة —
         * وما يخصّ نوعًا بعينه يبقى على سطره. والسطرُ يغلب عند التكرار.
         */
        // a hotel that lists numbered rooms can sell a feature (a view) only if some open room carries it
        $rooms = app(\App\Services\BookingRoomService::class);
        $listed = $inKind->filter(fn (BookableItem $u) => $u->hasListedRooms())->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $carried = $listed->isEmpty() ? null : \App\Support\RequestMemo::remember('offering-options', 'carried:' . $listed->implode(','), fn () => \App\Models\BookableItemRoomFeature::query()
            ->whereIn('room_id', \App\Models\BookableItemRoom::query()->whereIn('bookable_item_id', $listed->all())->where('status', \App\Models\BookableItemRoom::STATUS_AVAILABLE)->select('id'))
            ->pluck('option_id')->map(fn ($id) => (int) $id)->unique()->all());
        $features = $carried === null ? [] : $rooms->featureVocabularyIds((int) $price->business_id);

        $rows = \App\Support\RequestMemo::remember('offering-options', (int) $price->id . '|' . $declared->sort()->implode(',') . '|' . $listed->implode(',') . '|' . (int) $featuresAsVariants, fn () => OfferingOption::query()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            // مُوصِّفٌ بقيمة صفر يوصِّف ولا يُسعِّر، فلا شأن لشاشة الاختيار به.
            ->where('adjust_value', '!=', 0)
            ->whereNotIn('option_id', $declared->all() ?: [0])
            ->where(function ($query) use ($price) {
                $query->where(function ($sub) use ($price) {
                    $sub->where('offering_type', $price->getMorphClass())
                        ->where('offering_id', (int) $price->id);
                })->orWhere(function ($sub) use ($price) {
                    $sub->where('offering_type', (new User)->getMorphClass())
                        ->where('offering_id', (int) $price->business_id);
                });
            })
            ->with('option:id,name_ar,name_en')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('option_id')
            ->map(fn ($group) => $group->firstWhere('offering_type', $price->getMorphClass()) ?: $group->first())
            ->values());

        if ($carried !== null) {
            // the rooms' views either come as cards of their own (the hotel page) or are ticked by the guest (a partner's room), and are
            // offered only when some room carries them
            $rows = $rows->reject(fn (OfferingOption $row) => in_array((int) $row->option_id, $features, true) && ($featuresAsVariants || ! in_array((int) $row->option_id, $carried, true)))->values();
        }

        return $rows->map(fn (OfferingOption $row) => [
            'option_id' => (int) $row->option_id,
            'name' => $this->say($row->option),
            'adjust_type' => (string) $row->adjust_type,
            'adjust_value' => (float) $row->adjust_value,
            // «لكل فرد» تُضرب فى عدد النزلاء وقت الحساب، فالمعروضُ هنا سعرُ
            // الفرد الواحد ومعه العَلَم — والتطبيقُ يضربه فى عدد من يحجز.
            'per_person' => (bool) $row->per_person,
            'amount' => $row->appliedTo(round((float) $price->baseUnitPrice(), 2)),
        ])->values()->all();
    }

    private function say(?object $option): ?string
    {
        if (! $option) {
            return null;
        }

        $primary = app()->getLocale() === 'en' ? $option->name_en : $option->name_ar;

        return ($primary !== null && $primary !== '') ? $primary : (($option->name_ar ?: $option->name_en) ?: null);
    }
}
