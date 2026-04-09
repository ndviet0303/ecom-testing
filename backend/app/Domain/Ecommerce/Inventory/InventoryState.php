<?php

namespace App\Domain\Ecommerce\Inventory;

use App\Domain\Ecommerce\Exception\InsufficientStockException;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

/**
 * on_hand: tồn thực tế, reserved: đang giữ cho đơn chưa giao.
 */
final class InventoryState
{
    public function __construct(
        public readonly int $onHand,
        public readonly int $reserved
    ) {
        if ($onHand < 0 || $reserved < 0) {
            throw new InvalidDomainArgumentException('Stock counts cannot be negative.');
        }

        if ($reserved > $onHand) {
            throw new InvalidDomainArgumentException('Reserved cannot exceed on-hand stock.');
        }
    }

    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    public function reserve(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidDomainArgumentException('Reserve quantity cannot be negative.');
        }

        if ($quantity > $this->available()) {
            throw new InsufficientStockException('Not enough stock to reserve.');
        }

        return new self($this->onHand, $this->reserved + $quantity);
    }

    public function releaseReservation(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidDomainArgumentException('Release quantity cannot be negative.');
        }

        if ($quantity > $this->reserved) {
            throw new InvalidDomainArgumentException('Cannot release more than reserved.');
        }

        return new self($this->onHand, $this->reserved - $quantity);
    }

    /** Giao hàng: trừ tồn và giảm reserved tương ứng. */
    public function fulfillShipped(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidDomainArgumentException('Ship quantity cannot be negative.');
        }

        if ($quantity > $this->reserved) {
            throw new InvalidDomainArgumentException('Cannot ship more than reserved.');
        }

        return new self($this->onHand - $quantity, $this->reserved - $quantity);
    }
}
