<?php

namespace App\Services\Ecommerce;

use App\Domain\Ecommerce\Checkout\OrderTotalCalculator;
use App\Domain\Ecommerce\Order\OrderStatus;
use App\Domain\Ecommerce\Promotion\CouponApplicator;
use App\Domain\Ecommerce\Tax\TaxCalculator;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CouponApplicator $couponApplicator,
        private readonly TaxCalculator $taxCalculator,
        private readonly OrderTotalCalculator $orderTotalCalculator,
        private readonly OrderStatusEventRecorder $orderStatusEventRecorder,
    ) {}

    /**
     * @param  array{fulfillment_method?: string, shipping_address_id?: int|null, weight_grams: int, tax_rate_basis_points: int, coupon_code?: string|null, customer_note?: string|null, payment_method?: string}  $options
     */
    public function checkout(User $user, Cart $cart, array $options): Order
    {
        $cart->load(['items.product.inventory']);

        $paymentMethod = $options['payment_method'] ?? 'immediate';
        $fulfillmentMethod = $options['fulfillment_method'] ?? 'shipping';
        if (! in_array($paymentMethod, ['immediate', 'sepay_qr'], true)) {
            throw ValidationException::withMessages(['payment_method' => ['Phương thức thanh toán không hợp lệ.']]);
        }
        if (! in_array($fulfillmentMethod, ['shipping', 'pickup'], true)) {
            throw ValidationException::withMessages(['fulfillment_method' => ['Hình thức nhận hàng không hợp lệ.']]);
        }

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => ['Cart is empty.']]);
        }

        if ($cart->user_id !== $user->id) {
            abort(403, 'Cart does not belong to this user.');
        }

        $address = null;
        if ($fulfillmentMethod === 'shipping') {
            if (empty($options['shipping_address_id'])) {
                throw ValidationException::withMessages(['shipping_address_id' => ['Shipping address is required.']]);
            }

            $address = Address::query()
                ->where('id', $options['shipping_address_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages(['shipping_address_id' => ['Invalid shipping address.']]);
            }
        }

        $subtotal = 0;

        foreach ($cart->items as $item) {
            $subtotal += $item->lineTotalCents();
        }

        $couponCode = $options['coupon_code'] ?? null;
        $discountCents = 0;
        $coupon = null;

        if ($couponCode) {
            $coupon = Coupon::query()
                ->whereRaw('lower(code) = ?', [strtolower($couponCode)])
                ->first();

            if ($coupon === null || ! $coupon->isUsableAt(now())) {
                throw ValidationException::withMessages(['coupon_code' => ['Invalid or expired coupon.']]);
            }

            try {
                $discountCents = $this->couponApplicator->appliedDiscountCents(
                    $subtotal,
                    $coupon->min_subtotal_cents,
                    $coupon->discount_cents
                );
            } catch (\App\Domain\Ecommerce\Exception\InvalidDomainArgumentException $e) {
                throw ValidationException::withMessages(['coupon_code' => [$e->getMessage()]]);
            }
        }

        $weightGrams = (int) $options['weight_grams'];
        $taxBps = (int) $options['tax_rate_basis_points'];
        $customerNote = $options['customer_note'] ?? null;

        $shippingCents = $fulfillmentMethod === 'pickup'
            ? 0
            : $this->shippingFeeByAddress($address);

        $taxable = $subtotal - $discountCents;

        if ($taxable < 0) {
            $taxable = 0;
        }

        $taxCents = $this->taxCalculator->taxAmountCents($taxable, $taxBps);

        try {
            $totalCents = $this->orderTotalCalculator->grandTotalCents(
                $subtotal,
                $discountCents,
                $shippingCents,
                $taxCents
            );
        } catch (\App\Domain\Ecommerce\Exception\InvalidDomainArgumentException $e) {
            throw ValidationException::withMessages(['totals' => [$e->getMessage()]]);
        }

        $snapshot = $address?->toSnapshotArray();

        $initialStatus = $paymentMethod === 'sepay_qr' ? OrderStatus::Pending : OrderStatus::Paid;

        return DB::transaction(function () use (
            $user,
            $cart,
            $subtotal,
            $discountCents,
            $shippingCents,
            $taxCents,
            $totalCents,
            $couponCode,
            $address,
            $weightGrams,
            $snapshot,
            $coupon,
            $customerNote,
            $initialStatus,
        ): Order {
            if ($coupon !== null) {
                $coupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
                $global = CouponRedemption::query()->where('coupon_id', $coupon->id)->count();
                if ($coupon->max_uses !== null && $global >= $coupon->max_uses) {
                    throw ValidationException::withMessages(['coupon_code' => ['Coupon đã hết lượt sử dụng.']]);
                }
                $perUser = CouponRedemption::query()
                    ->where('coupon_id', $coupon->id)
                    ->where('user_id', $user->id)
                    ->count();
                if ($coupon->max_uses_per_user !== null && $perUser >= $coupon->max_uses_per_user) {
                    throw ValidationException::withMessages(['coupon_code' => ['Bạn đã dùng hết lượt với mã này.']]);
                }
            }

            foreach ($cart->items as $item) {
                $product = $item->product;
                $inv = $product->inventory;

                if ($inv === null) {
                    throw ValidationException::withMessages([
                        'stock' => ["Missing inventory for product {$product->sku}."],
                    ]);
                }

                $inv = $inv->newQuery()->whereKey($inv->id)->lockForUpdate()->first();

                if ($inv->reserved < $item->quantity || $inv->on_hand < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => ["Insufficient stock for product {$product->sku}."],
                    ]);
                }

                $inv->decrement('on_hand', $item->quantity);
                $inv->decrement('reserved', $item->quantity);
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'shipping_address_id' => $address?->id,
                'shipping_address_snapshot' => $snapshot,
                'weight_grams' => $weightGrams,
                'shipping_zone_id' => null,
                'status' => $initialStatus->value,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discountCents,
                'shipping_cents' => $shippingCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,
                'coupon_code' => $couponCode,
                'customer_note' => $customerNote,
            ]);

            $order->update([
                'order_number' => sprintf('ORD-%s-%08d', now()->format('Ymd'), $order->id),
            ]);

            foreach ($cart->items as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                ]);
            }

            if ($coupon !== null) {
                CouponRedemption::query()->create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ]);
            }

            $cart->items()->delete();

            $this->orderStatusEventRecorder->record(
                $order->fresh(),
                null,
                $initialStatus->value,
                'system',
                null,
                null
            );

            $placed = $order->fresh()->load(['orderItems', 'shippingAddress', 'shippingZone', 'statusEvents']);

            $user->notify(new OrderPlacedNotification($placed));

            return $placed;
        });
    }

    private function shippingFeeByAddress(?Address $address): int
    {
        $defaultShipping = (int) config('vn.default_shipping_cents', 30_000);
        if ($address === null) {
            return $defaultShipping;
        }

        $innerCityProvince = (string) config('vn.inner_city_province', 'Thành phố Hà Nội');
        /** @var array<int, string> $innerCityDistricts */
        $innerCityDistricts = (array) config('vn.inner_city_districts', []);
        $incomingProvince = trim((string) ($address->province ?? ''));
        $incomingDistrict = trim((string) ($address->district ?? ''));

        $isHanoi = $incomingProvince !== ''
            && $this->normalizeText($incomingProvince) === $this->normalizeText($innerCityProvince);

        if (! $isHanoi || $incomingDistrict === '') {
            return $defaultShipping;
        }

        $normalizedDistrict = $this->normalizeText($incomingDistrict);
        $isInnerDistrict = collect($innerCityDistricts)
            ->map(fn (string $district): string => $this->normalizeText($district))
            ->contains($normalizedDistrict);

        if ($isInnerDistrict) {
            return 0;
        }

        return $defaultShipping;
    }

    private function normalizeText(string $value): string
    {
        return Str::lower(trim(Str::ascii($value)));
    }
}
