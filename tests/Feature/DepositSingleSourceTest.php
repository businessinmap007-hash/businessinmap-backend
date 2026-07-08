<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Services\BookingFoodService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 4: deposit is single-source (business_deposit_policies). The per-price
 * columns are retired and the unified invoice reflects the actually-held
 * deposit, never a second computation.
 */
class DepositSingleSourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_service_prices_has_no_deposit_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('business_service_prices', 'deposit_enabled'));
        $this->assertFalse(Schema::hasColumn('business_service_prices', 'deposit_percent'));
    }

    public function test_price_model_no_longer_computes_a_deposit(): void
    {
        $this->assertFalse(method_exists(BusinessServicePrice::class, 'depositPercent'));
        $this->assertFalse(method_exists(BusinessServicePrice::class, 'depositAmount'));

        $price = BusinessServicePrice::query()->first();
        if ($price) {
            $this->assertArrayNotHasKey('deposit_amount', $price->priceSnapshot(1));
            $this->assertArrayNotHasKey('deposit_percent', $price->priceSnapshot(1));
        }
    }

    public function test_unified_invoice_deposit_equals_the_held_policy_deposit(): void
    {
        $booking = Booking::withTrashed()->whereNotNull('business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking) {
            $this->markTestSkipped('Needs at least one booking.');
        }

        $invoice = app(BookingFoodService::class)->unifiedInvoice($booking);

        $this->assertSame(
            round($booking->depositAmount(), 2),
            (float) $invoice['deposit_amount'],
            'invoice deposit must equal the resolved/held policy deposit'
        );
    }
}
