<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InsufficientStockException;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Inventory\InventoryState;
use PHPUnit\Framework\TestCase;

class InventoryStateTest extends TestCase
{
    public function test_available_is_on_hand_minus_reserved(): void
    {
        $state = new InventoryState(10, 3);
        $this->assertSame(7, $state->available());
    }

    public function test_rejects_reserved_greater_than_on_hand(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        new InventoryState(5, 6);
    }

    public function test_reserve_reduces_available(): void
    {
        $next = (new InventoryState(10, 0))->reserve(4);
        $this->assertSame(10, $next->onHand);
        $this->assertSame(4, $next->reserved);
        $this->assertSame(6, $next->available());
    }

    public function test_reserve_fails_when_not_enough_available(): void
    {
        $this->expectException(InsufficientStockException::class);
        (new InventoryState(5, 4))->reserve(2);
    }

    public function test_release_reservation_increases_available(): void
    {
        $next = (new InventoryState(10, 5))->releaseReservation(2);
        $this->assertSame(10, $next->onHand);
        $this->assertSame(3, $next->reserved);
    }

    public function test_cannot_release_more_than_reserved(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        (new InventoryState(10, 1))->releaseReservation(2);
    }

    public function test_fulfill_shipped_reduces_on_hand_and_reserved(): void
    {
        $next = (new InventoryState(10, 4))->fulfillShipped(3);
        $this->assertSame(7, $next->onHand);
        $this->assertSame(1, $next->reserved);
    }

    public function test_cancel_flow_reserve_then_release_restores_available(): void
    {
        $afterReserve = (new InventoryState(20, 0))->reserve(5);
        $afterRelease = $afterReserve->releaseReservation(5);
        $this->assertSame(20, $afterRelease->available());
    }
}
