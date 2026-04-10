<?php

namespace App\Services\Ecommerce;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartService
{
    public function __construct(
        private readonly InventoryReservationService $reservation
    ) {}

    /**
     * @return array{0: Cart|null, 1: string|null}
     */
    public function resolveCart(?User $user, ?string $guestTokenFromHeader, bool $createGuestCartIfMissing = false): array
    {
        if ($user !== null) {
            $cart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['guest_token' => null]
            );

            return [$cart, null];
        }

        $token = $guestTokenFromHeader;

        if ($token === null || $token === '') {
            if (! $createGuestCartIfMissing) {
                return [null, null];
            }

            $token = (string) Str::uuid();
            $cart = Cart::query()->create([
                'user_id' => null,
                'guest_token' => $token,
            ]);

            return [$cart, $token];
        }

        $cart = Cart::query()->where('guest_token', $token)->first();

        if ($cart === null) {
            abort(404, 'Cart not found for token.');
        }

        return [$cart, $token];
    }

    public function mergeGuestIntoUser(string $guestToken, User $user): void
    {
        DB::transaction(function () use ($guestToken, $user): void {
            $guestCart = Cart::query()->where('guest_token', $guestToken)->lockForUpdate()->first();

            if ($guestCart === null) {
                return;
            }

            $userCart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['guest_token' => null]
            );

            $productIds = $guestCart->items->pluck('product_id')
                ->merge($userCart->items->pluck('product_id'))
                ->unique()
                ->values()
                ->all();

            foreach ($guestCart->items as $item) {
                $existing = $userCart->items()->where('product_id', $item->product_id)->first();

                if ($existing !== null) {
                    $existing->update([
                        'quantity' => $existing->quantity + $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                    ]);
                } else {
                    CartItem::query()->create([
                        'cart_id' => $userCart->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                    ]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->delete();

            $this->reservation->syncProducts($productIds);
        });
    }

    public function addOrUpdateLine(Cart $cart, Product $product, int $quantity, bool $increment = false): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $quantity, $increment): CartItem {
            $price = $product->effectivePriceCents();
            $line = $cart->items()->where('product_id', $product->id)->lockForUpdate()->first();

            if ($line === null) {
                $line = $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price_cents' => $price,
                ]);
            } else {
                $line->update([
                    'quantity' => $increment ? ($line->quantity + $quantity) : $quantity,
                    'unit_price_cents' => $price,
                ]);
            }

            $this->reservation->syncProduct($product->id);

            return $line->fresh();
        });
    }

    public function addMultipleLines(Cart $cart, array $items): void
    {
        DB::transaction(function () use ($cart, $items): void {
            $productIds = [];
            foreach ($items as $itemData) {
                $productId = $itemData['product_id'];
                $quantity = (int) ($itemData['quantity'] ?? 1);
                
                $product = Product::query()->findOrFail($productId);
                $this->addOrUpdateLine($cart, $product, $quantity, true);
                $productIds[] = $productId;
            }
            // addOrUpdateLine already calls syncProduct, 
            // but we can call syncProducts once at the end if we wanted to be more efficient.
            // For now, addOrUpdateLine is fine.
        });
    }

    public function removeLine(Cart $cart, int $productId): void
    {
        DB::transaction(function () use ($cart, $productId): void {
            $line = $cart->items()->where('product_id', $productId)->lockForUpdate()->first();

            if ($line === null) {
                return;
            }

            $pid = (int) $line->product_id;
            $line->delete();
            $this->reservation->syncProduct($pid);
        });
    }

    public function subtotalCents(Cart $cart): int
    {
        $sum = 0;

        foreach ($cart->items as $item) {
            $sum += $item->lineTotalCents();
        }

        return $sum;
    }
}
