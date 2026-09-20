<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartyTrust;
use App\Models\TrustedPartner;
use App\Models\User;
use App\Services\Guarantees\TrustedPartnerService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * POST /api/v2/orders/{order}/trust (customer / driver) and
 * POST /api/v2/business/orders/{order}/trust (merchant) - the caller ticks
 * or unticks "I trust" for one other party of that order. The caller must
 * themselves be a party to the order. Merchant->customer additionally
 * vouches through TrustedPartnerService, which waives the order deposit and
 * therefore needs the customer to hold an active guarantee (422 otherwise).
 */
final class OrderTrustController extends Controller
{
    public function __construct(private readonly TrustedPartnerService $trustedPartners)
    {
    }

    public function update(Request $request, int $order)
    {
        $data = $request->validate([
            'party' => ['required', 'in:customer,business,driver'],
            'trusted' => ['required', 'boolean'],
        ]);

        $model = Order::query()->with('deliveryDriver')->where('status', '!=', 'cart')->findOrFail($order);
        $parties = self::partyIds($model);

        $callerId = BusinessContext::id($request);
        if (! in_array($callerId, array_filter($parties), true)) {
            abort(404, __('الطلب غير موجود.'));
        }

        $targetId = $parties[$data['party']] ?? null;
        if (! $targetId) {
            abort(422, __('هذا الطرف غير موجود في الطلب.'));
        }
        if ($targetId === $callerId) {
            abort(422, __('لا يمكنك توثيق نفسك.'));
        }

        $trusted = (bool) $data['trusted'];

        if ($callerId === $parties['business'] && $data['party'] === 'customer') {
            if ($trusted) {
                $this->trustedPartners->vouchUser($callerId, User::query()->findOrFail($targetId));
            } else {
                TrustedPartner::query()->where('business_id', $callerId)->where('user_id', $targetId)->update(['is_active' => false]);
            }
        }

        if ($trusted) {
            PartyTrust::query()->firstOrCreate(['truster_id' => $callerId, 'trusted_id' => $targetId]);
        } else {
            PartyTrust::query()->where('truster_id', $callerId)->where('trusted_id', $targetId)->delete();
        }

        return response()->json(['success' => true, 'data' => [
            'party' => $data['party'],
            'trusted' => PartyTrust::exists($callerId, $targetId),
        ]]);
    }

    /** @return array{customer: ?int, business: ?int, driver: ?int} */
    public static function partyIds(Order $order): array
    {
        return [
            'customer' => $order->user_id ? (int) $order->user_id : null,
            'business' => $order->business_id ? (int) $order->business_id : null,
            'driver' => optional($order->deliveryDriver)->user_id ? (int) $order->deliveryDriver->user_id : null,
        ];
    }
}
