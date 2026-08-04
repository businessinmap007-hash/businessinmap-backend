<?php

namespace App\Models\Concerns;

use App\Models\OfferingOption;
use App\Models\Option;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives a priced row the platform's own vocabulary for what it sells.
 *
 * A price row used to say «كشف» and stop there. What the customer searched for
 * — «عظام» — appeared nowhere on it, so the two could never be matched, and
 * the booking that came out of it had no name worth showing. An offering now
 * carries one LINE option (what is sold) and any number of MODIFIER options
 * (what qualifies it), and can say so:
 *
 *     كشف — عظام
 *     غرفة نوم — مودرن
 *     شقة — غرفتين — سوبر لوكس
 *
 * @see \App\Models\OfferingOption
 * @see \App\Services\MerchantOfferingVocabulary  what a given merchant may pick
 */
trait HasOfferingOptions
{
    public function offeringOptions(): MorphMany
    {
        return $this->morphMany(OfferingOption::class, 'offering')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** The option this offering sells, if the merchant named one. */
    public function lineOption(): ?Option
    {
        return $this->optionsWithRole(OfferingOption::ROLE_LINE)->first();
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\Option> */
    public function modifierOptions(): Collection
    {
        return $this->optionsWithRole(OfferingOption::ROLE_MODIFIER);
    }

    /**
     * Replace this offering's vocabulary.
     *
     * A line is optional — plenty of offerings are just «كشف» with no specialty
     * behind them, and forcing one would block every merchant whose child has
     * no `line` group at all. What is NOT optional is that there be at most
     * one: two lines would mean two different things being sold at one price.
     *
     * @param  array<int,int>  $modifierIds
     */
    public function syncOfferingOptions(?int $lineId, array $modifierIds = []): void
    {
        $modifierIds = collect($modifierIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $lineId)
            ->unique()
            ->values();

        DB::transaction(function () use ($lineId, $modifierIds) {
            $this->offeringOptions()->delete();

            $rows = [];

            if ($lineId) {
                $rows[] = [
                    'option_id' => (int) $lineId,
                    'role' => OfferingOption::ROLE_LINE,
                    'sort_order' => 0,
                ];
            }

            foreach ($modifierIds as $i => $id) {
                $rows[] = [
                    'option_id' => $id,
                    'role' => OfferingOption::ROLE_MODIFIER,
                    'sort_order' => $i + 1,
                ];
            }

            foreach ($rows as $row) {
                $this->offeringOptions()->create($row);
            }

            // A unique key cannot reach across tables, and one item type has to
            // be able to carry «كشف عظام» beside «كشف باطنة». Where the table
            // mirrors the line for that key, keep the mirror honest here — this
            // is the only place that writes it.
            if ($this->exists && in_array('line_option_id', $this->getFillable(), true)) {
                $this->forceFill(['line_option_id' => (int) $lineId])->saveQuietly();
            }
        });

        $this->unsetRelation('offeringOptions');
    }

    /**
     * «كشف — عظام», «شقة — غرفتين — سوبر لوكس».
     *
     * Falls back to whatever the row already called itself, so a listing that
     * predates the vocabulary still reads sensibly.
     */
    public function offeringLabel(?string $fallback = null): string
    {
        $parts = collect([$this->lineOption()])
            ->merge($this->modifierOptions())
            ->filter()
            ->map(fn (Option $o) => $o->displayName ?? $o->name_ar)
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return (string) ($fallback ?? '');
        }

        $label = $parts->implode(' — ');

        return $fallback ? $fallback . ' — ' . $label : $label;
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\Option> */
    private function optionsWithRole(string $role): Collection
    {
        $links = $this->relationLoaded('offeringOptions')
            ? $this->offeringOptions
            : $this->offeringOptions()->with('option')->get();

        return $links->where('role', $role)
            ->map(fn (OfferingOption $link) => $link->option)
            ->filter()
            ->values();
    }
}
