<?php

namespace App\Http\Middleware;

use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\SuiteSubscriptionService;
use App\Support\BusinessContext;
use Closure;
use Illuminate\Http\Request;

/**
 * After `business.member`: the acting business keeps its stock in the inventory suite (`inventory.ready`), and for a
 * write (`inventory.ready:write`) its monthly subscription is paid or in its grace days. A business in read-only mode
 * still reads and exports everything — nothing is ever deleted — and only writes are refused, with 402 like every
 * other «top up your wallet» answer.
 */
class InventoryReady
{
    public function __construct(
        private readonly InventoryAccess $access,
        private readonly SuiteSubscriptionService $subscription,
    ) {}

    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        $business = BusinessContext::business($request);

        if (! $business || ! $this->access->enabled($business)) {
            return response()->json([
                'success' => false,
                'code' => 'inventory_disabled',
                'message' => __('المخزون غير مفعّل لهذا النشاط. يفعّله صاحب النشاط من إعدادات المخزون.'),
            ], 403);
        }

        if ($mode === 'write' && ! $this->subscription->writable($business)) {
            return response()->json([
                'success' => false,
                'code' => 'suite_read_only',
                'message' => __('اشتراك تطبيق الكمبيوتر غير مدفوع: البيانات للقراءة والتصدير فقط حتى تشحن المحفظة وتجدد الاشتراك.'),
            ], 402);
        }

        return $next($request);
    }
}
