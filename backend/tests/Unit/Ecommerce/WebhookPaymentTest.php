<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Payment\InMemoryWebhookEventStore;
use App\Domain\Ecommerce\Payment\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

class WebhookPaymentTest extends TestCase
{
    public function test_accepts_valid_hmac_signature(): void
    {
        $verifier = new WebhookSignatureVerifier;
        $body = '{"id":"evt_1"}';
        $secret = 'whsec_test';
        $sig = hash_hmac('sha256', $body, $secret);

        $this->assertTrue($verifier->isValid($body, $secret, $sig));
    }

    public function test_rejects_tampered_body(): void
    {
        $verifier = new WebhookSignatureVerifier;
        $secret = 'whsec_test';
        $sig = hash_hmac('sha256', '{"id":"evt_1"}', $secret);

        $this->assertFalse($verifier->isValid('{"id":"evt_2"}', $secret, $sig));
    }

    public function test_signature_comparison_is_case_insensitive_hex(): void
    {
        $verifier = new WebhookSignatureVerifier;
        $body = 'x';
        $secret = 's';
        $sig = strtoupper(hash_hmac('sha256', $body, $secret));

        $this->assertTrue($verifier->isValid($body, $secret, $sig));
    }

    public function test_idempotent_store_accepts_first_event_only(): void
    {
        $store = new InMemoryWebhookEventStore;
        $this->assertTrue($store->tryConsume('evt_a'));
        $this->assertFalse($store->tryConsume('evt_a'));
        $this->assertTrue($store->tryConsume('evt_b'));
    }

    public function test_idempotent_store_rejects_empty_id(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        (new InMemoryWebhookEventStore)->tryConsume('');
    }
}
