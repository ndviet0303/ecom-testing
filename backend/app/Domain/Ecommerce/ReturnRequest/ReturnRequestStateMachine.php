<?php

namespace App\Domain\Ecommerce\ReturnRequest;

use App\Domain\Ecommerce\Exception\InvalidReturnTransitionException;

final class ReturnRequestStateMachine
{
    /**
     * Luồng: pending → approved|rejected; approved → received|rejected; received → refunded; rejected/refunded kết thúc.
     */
    public function assertCanTransition(ReturnStatus $from, ReturnStatus $to): void
    {
        if ($from === $to) {
            throw new InvalidReturnTransitionException(
                sprintf('Trạng thái đã là %s.', $to->value)
            );
        }

        $allowed = match ($from) {
            ReturnStatus::Pending => [ReturnStatus::Approved, ReturnStatus::Rejected],
            ReturnStatus::Approved => [ReturnStatus::Received, ReturnStatus::Rejected],
            ReturnStatus::Received => [ReturnStatus::Refunded],
            ReturnStatus::Rejected => [],
            ReturnStatus::Refunded => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidReturnTransitionException(
                sprintf('Không thể chuyển yêu cầu trả từ %s sang %s.', $from->value, $to->value)
            );
        }
    }

    public function canTransition(ReturnStatus $from, ReturnStatus $to): bool
    {
        try {
            $this->assertCanTransition($from, $to);

            return true;
        } catch (InvalidReturnTransitionException) {
            return false;
        }
    }
}
