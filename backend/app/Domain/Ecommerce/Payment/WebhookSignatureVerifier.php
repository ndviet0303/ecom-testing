<?php

namespace App\Domain\Ecommerce\Payment;

final class WebhookSignatureVerifier
{
    /**
     * So khớp HMAC-SHA256 (hex lowercase) với secret.
     */
    public function isValid(string $rawBody, string $secret, string $providedSignatureHex): bool
    {
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = strtolower(trim($providedSignatureHex));

        return hash_equals($expected, $provided);
    }
}
