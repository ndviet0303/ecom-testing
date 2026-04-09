<?php

namespace App\Domain\Ecommerce\Payment;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

/**
 * Lưu id sự kiện đã xử lý (in-memory). Trả false nếu trùng (idempotent skip).
 */
final class InMemoryWebhookEventStore
{
    /** @var array<string, true> */
    private array $seen = [];

    public function tryConsume(string $eventId): bool
    {
        if ($eventId === '') {
            throw new InvalidDomainArgumentException('Webhook event id cannot be empty.');
        }

        if (isset($this->seen[$eventId])) {
            return false;
        }

        $this->seen[$eventId] = true;

        return true;
    }
}
