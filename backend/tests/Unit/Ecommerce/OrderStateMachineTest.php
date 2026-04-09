<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidOrderTransitionException;
use App\Domain\Ecommerce\Order\OrderStateMachine;
use App\Domain\Ecommerce\Order\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStateMachineTest extends TestCase
{
    private OrderStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new OrderStateMachine;
    }

    public function test_pending_to_paid_allowed(): void
    {
        $this->machine->assertCanTransition(OrderStatus::Pending, OrderStatus::Paid);
        $this->assertTrue(true);
    }

    public function test_pending_to_cancelled_allowed(): void
    {
        $this->machine->assertCanTransition(OrderStatus::Pending, OrderStatus::Cancelled);
        $this->assertTrue(true);
    }

    public function test_paid_to_pending_forbidden(): void
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->machine->assertCanTransition(OrderStatus::Paid, OrderStatus::Pending);
    }

    public function test_paid_to_packed_allowed(): void
    {
        $this->machine->assertCanTransition(OrderStatus::Paid, OrderStatus::Packed);
        $this->assertTrue(true);
    }

    public function test_packed_to_shipped_allowed(): void
    {
        $this->machine->assertCanTransition(OrderStatus::Packed, OrderStatus::Shipped);
        $this->assertTrue(true);
    }

    public function test_shipped_to_delivered_allowed(): void
    {
        $this->machine->assertCanTransition(OrderStatus::Shipped, OrderStatus::Delivered);
        $this->assertTrue(true);
    }

    public function test_delivered_is_terminal(): void
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->machine->assertCanTransition(OrderStatus::Delivered, OrderStatus::Shipped);
    }

    public function test_can_transition_helper(): void
    {
        $this->assertTrue($this->machine->canTransition(OrderStatus::Pending, OrderStatus::Paid));
        $this->assertFalse($this->machine->canTransition(OrderStatus::Paid, OrderStatus::Pending));
    }
}
