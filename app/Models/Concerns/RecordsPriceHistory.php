<?php

namespace App\Models\Concerns;

use App\Models\OfferingPriceChange;

/**
 * Remembers what this row used to cost.
 *
 * A model using this declares which column is the price and which column names
 * the business, and every save that moves that number leaves a row behind.
 *
 *     protected string $priceHistoryColumn = 'base_price';
 *
 * ── Why the model and not the controller ────────────────────────────────────
 *
 * Three screens write a menu item's price — the owner panel, the admin matrix
 * and the API — and a fourth will exist by the time anyone reads this. A guard
 * that lives in one of them is a guard the other three walk past, and the
 * whole point of the history is that it is complete: «the last increase was
 * three days ago» is only true if EVERY increase was recorded.
 *
 * The cost is that a seeder writing prices also writes history, which is
 * correct — a seeder that raises a price has raised a price.
 *
 * ── Creation is not an increase ─────────────────────────────────────────────
 *
 * The first row carries `old_price = null`. A shop that opens at 200 has not
 * raised anything, and treating it as a rise would put every new item under a
 * month's embargo before it could ever be discounted.
 *
 * ⚠ The gap this leaves, stated rather than hidden: delete the row and create
 * it again at a higher price and the history starts over. Closing it means
 * matching a new row to a deleted one, which is guesswork on anything but an
 * exact name match. Left open, and the offer's own audit trail
 * (`meta.checked_against`) is what makes it visible after the fact.
 */
trait RecordsPriceHistory
{
    public static function bootRecordsPriceHistory(): void
    {
        static::created(function ($model) {
            $model->recordPriceChange(null, $model->currentTrackedPrice());
        });

        static::updated(function ($model) {
            $column = $model->priceHistoryColumn();

            if (! $model->wasChanged($column)) {
                return;
            }

            $model->recordPriceChange(
                $model->getOriginal($column) === null ? null : (float) $model->getOriginal($column),
                $model->currentTrackedPrice()
            );
        });
    }

    /** The column this model prices itself in. */
    public function priceHistoryColumn(): string
    {
        return property_exists($this, 'priceHistoryColumn') ? $this->priceHistoryColumn : 'price';
    }

    /** The column naming the business that owns the row. */
    public function priceHistoryBusinessColumn(): string
    {
        return property_exists($this, 'priceHistoryBusinessColumn')
            ? $this->priceHistoryBusinessColumn
            : 'business_id';
    }

    public function currentTrackedPrice(): ?float
    {
        $value = $this->{$this->priceHistoryColumn()};

        return $value === null ? null : (float) $value;
    }

    public function priceChanges()
    {
        return $this->morphMany(OfferingPriceChange::class, 'priceable');
    }

    /**
     * When this row last went UP, or null if it never has.
     *
     * The one question the offer rule asks.
     */
    public function lastPriceIncreaseAt(): ?\Illuminate\Support\Carbon
    {
        $at = OfferingPriceChange::query()
            ->for($this)
            ->increases()
            ->max('changed_at');

        return $at ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    private function recordPriceChange(?float $old, ?float $new): void
    {
        if ($new === null) {
            return;
        }

        // A no-op save is not a change. `wasChanged` already filters most of
        // these, but a decimal cast can turn 200 into 200.00 and back.
        if ($old !== null && abs($old - $new) < 0.005) {
            return;
        }

        $businessColumn = $this->priceHistoryBusinessColumn();

        OfferingPriceChange::create([
            'priceable_type' => $this->getMorphClass(),
            'priceable_id' => $this->getKey(),
            'business_id' => $this->{$businessColumn} ?? null,
            'old_price' => $old,
            'new_price' => $new,
            'currency' => (string) ($this->currency ?? 'EGP'),
            'is_increase' => $old !== null && $new > $old,
            'source' => app()->runningInConsole() ? 'console' : 'app',
            'changed_at' => now(),
        ]);
    }
}
