<?php

namespace App\Services;

use App\Models\ProcessedWebhookEvent;

/**
 * Dùng cho sau này (VNPay, Stripe…). Không gọi cổng thanh toán ở đây.
 */
class WebhookIdempotencyService
{
    public function tryConsume(string $provider, string $eventId, ?string $checksum = null): bool
    {
        if ($eventId === '' || $provider === '') {
            return false;
        }

        try {
            ProcessedWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'checksum' => $checksum,
            ]);

            return true;
        } catch (\Illuminate\Database\QueryException) {
            return false;
        }
    }
}
