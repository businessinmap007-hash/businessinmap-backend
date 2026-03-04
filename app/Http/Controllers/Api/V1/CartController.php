<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemExtra;
use App\Models\Extra;
use App\Models\Item;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function myCart(Request $request)
    {
        $cart = $this->getOrCreateActiveCart($request);

        $cart->load([
            'items.item',
            'items.variant',
            'items.extras.extra'
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->cartPayload($cart),
        ]);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:variants,id',
            'qty' => 'nullable|integer|min:1',
            'extras' => 'nullable|array',
            'extras.*.extra_id' => 'required|exists:extras,id',
            'extras.*.qty' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $qty = (int)($data['qty'] ?? 1);

        return DB::transaction(function () use ($request, $data, $qty) {
            $cart = $this->getOrCreateActiveCart($request);

            $item = Item::where('is_active', 1)->findOrFail($data['item_id']);

            $variant = null;
            $unitPrice = (float)$item->base_price;

            if (!empty($data['variant_id'])) {
                $variant = Variant::where('item_id', $item->id)
                    ->where('is_active', 1)
                    ->findOrFail($data['variant_id']);

                $unitPrice = (float)$variant->price;
            }

            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'item_id' => $item->id,
                'variant_id' => $variant?->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'notes' => $data['notes'] ?? null,
            ]);

            // Extras (snapshot)
            foreach (($data['extras'] ?? []) as $ex) {
                $exQty = (int)($ex['qty'] ?? 1);

                $extra = Extra::where('item_id', $item->id)
                    ->where('is_active', 1)
                    ->findOrFail($ex['extra_id']);

                // max_qty (لو موجود)
                if (!is_null($extra->max_qty) && $exQty > (int)$extra->max_qty) {
                    abort(422, "Extra qty exceeds max_qty for extra_id={$extra->id}");
                }

                CartItemExtra::create([
                    'cart_item_id' => $cartItem->id,
                    'extra_id' => $extra->id,
                    'qty' => $exQty,
                    'unit_price' => (float)$extra->price,
                ]);
            }

            $cart->load(['items.item','items.variant','items.extras.extra']);

            return response()->json([
                'success' => true,
                'message_ar' => 'تمت الإضافة إلى السلة',
                'message_en' => 'Added to cart',
                'data' => $this->cartPayload($cart),
            ]);
        });
    }

    public function updateQty(Request $request, $cartItemId)
    {
        $data = $request->validate([
            'qty' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $cartItemId, $data) {
            $cart = $this->getOrCreateActiveCart($request);

            $cartItem = CartItem::where('cart_id', $cart->id)->findOrFail($cartItemId);
            $cartItem->update(['qty' => (int)$data['qty']]);

            $cart->load(['items.item','items.variant','items.extras.extra']);

            return response()->json([
                'success' => true,
                'message_ar' => 'تم تحديث الكمية',
                'message_en' => 'Quantity updated',
                'data' => $this->cartPayload($cart),
            ]);
        });
    }

    public function removeItem(Request $request, $cartItemId)
    {
        return DB::transaction(function () use ($request, $cartItemId) {
            $cart = $this->getOrCreateActiveCart($request);

            $cartItem = CartItem::where('cart_id', $cart->id)->findOrFail($cartItemId);

            // حذف extras أولاً
            $cartItem->extras()->delete();
            $cartItem->delete();

            $cart->load(['items.item','items.variant','items.extras.extra']);

            return response()->json([
                'success' => true,
                'message_ar' => 'تم حذف العنصر من السلة',
                'message_en' => 'Item removed',
                'data' => $this->cartPayload($cart),
            ]);
        });
    }

    public function clear(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $cart = $this->getOrCreateActiveCart($request);

            foreach ($cart->items as $i) {
                $i->extras()->delete();
                $i->delete();
            }

            $cart->load(['items.item','items.variant','items.extras.extra']);

            return response()->json([
                'success' => true,
                'message_ar' => 'تم تفريغ السلة',
                'message_en' => 'Cart cleared',
                'data' => $this->cartPayload($cart),
            ]);
        });
    }

    // ---------------- Helpers ----------------

    private function getOrCreateActiveCart(Request $request): Cart
    {
        $userId = $request->user()->id;

        return Cart::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            ['notes' => null]
        );
    }

    private function cartPayload(Cart $cart): array
    {
        $items = $cart->items->map(function (CartItem $ci) {
            $ci->loadMissing(['item','variant','extras.extra']);

            $extras = $ci->extras->map(function (CartItemExtra $x) {
                return [
                    'id' => $x->id,
                    'extra_id' => $x->extra_id,
                    'name_ar' => $x->extra?->name_ar,
                    'name_en' => $x->extra?->name_en,
                    'qty' => (int)$x->qty,
                    'unit_price' => (float)$x->unit_price,
                    'total' => (float)$x->unit_price * (int)$x->qty,
                ];
            });

            $extrasTotal = (float)$extras->sum('total');
            $lineTotal = ((float)$ci->unit_price * (int)$ci->qty) + $extrasTotal;

            return [
                'id' => $ci->id,
                'item_id' => $ci->item_id,
                'variant_id' => $ci->variant_id,
                'item' => [
                    'name_ar' => $ci->item?->name_ar,
                    'name_en' => $ci->item?->name_en,
                ],
                'variant' => $ci->variant ? [
                    'name_ar' => $ci->variant->name_ar,
                    'name_en' => $ci->variant->name_en,
                ] : null,
                'qty' => (int)$ci->qty,
                'unit_price' => (float)$ci->unit_price,
                'extras' => $extras->values(),
                'extras_total' => $extrasTotal,
                'line_total' => $lineTotal,
                'notes' => $ci->notes,
            ];
        });

        $grandTotal = (float)$items->sum('line_total');

        return [
            'cart_id' => $cart->id,
            'status' => $cart->status,
            'items' => $items->values(),
            'total' => $grandTotal,
        ];
    }
}
