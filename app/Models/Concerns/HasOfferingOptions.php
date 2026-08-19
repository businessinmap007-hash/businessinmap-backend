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
    /**
     * هل يقبل عمودُ `line_option_id` قيمة NULL على هذا النموذج؟
     *
     * يُعلَن ولا يُستنتج: قراءةُ المخطّط استعلامٌ فى كل حفظ، وتخمينُه صامتٌ.
     */
    protected function lineOptionColumnIsNullable(): bool
    {
        return false;
    }

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
     * زياداتُ السعر المكتوبة الآن، بالشكل الذى تقبله syncOfferingOptions.
     *
     * `syncOfferingOptions` تمسح ثم تكتب، فمن نادى عليها ولا شأن له بالأسعار
     * يمرّر هذه — وإلا محا حفظُ الأدمن سعرًا كتبه صاحبُ المحل بيده، صامتًا.
     *
     * @return array<int,array{type:string,value:float}>
     */
    public function currentOfferingAdjustments(): array
    {
        return $this->offeringOptions()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            ->get(['option_id', 'adjust_type', 'adjust_value'])
            ->mapWithKeys(fn ($row) => [(int) $row->option_id => [
                'type' => (string) $row->adjust_type,
                'value' => (float) $row->adjust_value,
            ]])->all();
    }

    /**
     * Replace this offering's vocabulary.
     *
     * A line is optional — plenty of offerings are just «كشف» with no specialty
     * behind them, and forcing one would block every merchant whose child has
     * no `line` group at all. What is NOT optional is that there be at most
     * one: two lines would mean two different things being sold at one price.
     *
     * ── وزيادةُ السعر ──────────────────────────────────────────────────────
     *
     * `$adjustments` تعطى كل مُوصِّفٍ سعرَه: `[option_id => ['type' =>
     * 'amount'|'percent', 'value' => float]]`. المُوصِّفُ الذى لا ذكرَ له فيها
     * يبقى مُوصِّفًا بلا سعر — يوصِّف ما اشتُرى ولا يغيّر رقمه، وهى الحال التى
     * كانت وحدها ممكنة قبل 2026-08-19.
     *
     * @param  array<int,int>  $modifierIds
     * @param  array<int,array{type?:string,value?:float}>  $adjustments
     */
    public function syncOfferingOptions(?int $lineId, array $modifierIds = [], array $adjustments = []): void
    {
        $modifierIds = collect($modifierIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $lineId)
            ->unique()
            ->values();

        DB::transaction(function () use ($lineId, $modifierIds, $adjustments) {
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
                $adjust = $adjustments[$id] ?? [];
                $type = (string) ($adjust['type'] ?? OfferingOption::ADJUST_AMOUNT);

                $rows[] = [
                    'option_id' => $id,
                    'role' => OfferingOption::ROLE_MODIFIER,
                    'adjust_type' => in_array($type, OfferingOption::adjustTypes(), true)
                        ? $type
                        : OfferingOption::ADJUST_AMOUNT,
                    'adjust_value' => round((float) ($adjust['value'] ?? 0), 2),
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
                /*
                 * «لا سطر» تُكتب كما يقبلها العمود.
                 *
                 * `(int) null` صفر. وعلى `bookable_items` مفتاحٌ أجنبىٌّ على
                 * هذا العمود، فوحدةٌ بلا نوعٍ كانت تُسقط الحفظ كلَّه بخطأ قيد.
                 * وعلى `business_service_prices` العمودُ NOT NULL، فالصفرُ هو
                 * جوابُه الوحيد. والجدولان يستعملان السِّمة نفسها، فالنموذجُ
                 * يعلن أيَّهما هو بدل أن تخمّن هى.
                 */
                $this->forceFill([
                    'line_option_id' => $lineId
                        ? (int) $lineId
                        : ($this->lineOptionColumnIsNullable() ? null : 0),
                ])->saveQuietly();
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
