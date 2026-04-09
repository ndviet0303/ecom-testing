<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidReturnTransitionException;
use App\Domain\Ecommerce\ReturnRequest\ReturnRequestStateMachine;
use App\Domain\Ecommerce\ReturnRequest\ReturnStatus;
use PHPUnit\Framework\TestCase;

class ReturnRequestStateMachineTest extends TestCase
{
    private ReturnRequestStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new ReturnRequestStateMachine;
    }

    public function test_pending_to_approved_or_rejected_only(): void
    {
        $this->machine->assertCanTransition(ReturnStatus::Pending, ReturnStatus::Approved);
        $this->machine->assertCanTransition(ReturnStatus::Pending, ReturnStatus::Rejected);

        $this->expectException(InvalidReturnTransitionException::class);
        $this->machine->assertCanTransition(ReturnStatus::Pending, ReturnStatus::Refunded);
    }

    public function test_received_to_refunded_only(): void
    {
        $this->machine->assertCanTransition(ReturnStatus::Received, ReturnStatus::Refunded);

        $this->expectException(InvalidReturnTransitionException::class);
        $this->machine->assertCanTransition(ReturnStatus::Received, ReturnStatus::Approved);
    }

    public function test_terminal_states_block_transitions(): void
    {
        $this->expectException(InvalidReturnTransitionException::class);
        $this->machine->assertCanTransition(ReturnStatus::Refunded, ReturnStatus::Pending);
    }

    public function test_same_status_rejected(): void
    {
        $this->expectException(InvalidReturnTransitionException::class);
        $this->machine->assertCanTransition(ReturnStatus::Pending, ReturnStatus::Pending);
    }
}
