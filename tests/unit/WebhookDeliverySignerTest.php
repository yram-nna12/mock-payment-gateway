<?php

use App\Services\WebhookDeliverySigner;
use PHPUnit\Framework\TestCase;

final class WebhookDeliverySignerTest extends TestCase
{
    public function testSignsExactBodyWithEventIdAndTimestamp(): void
    {
        $secret = 'unit-test-webhook-signing-secret-0123456789';
        $eventId = '550e8400-e29b-41d4-a716-446655440000';
        $body = '{"event_id":"' . $eventId . '"}';
        $headers = WebhookDeliverySigner::headers($secret, $eventId, $body, 1763400000);

        $this->assertSame((string) 1763400000, $headers['X-Payment-Timestamp']);
        $this->assertSame($eventId, $headers['X-Payment-Event-Id']);
        $this->assertSame(
            'v1=' . hash_hmac('sha256', "1763400000\n" . $eventId . "\n" . $body, $secret),
            $headers['X-Payment-Signature']
        );
        $nextAttempt = WebhookDeliverySigner::headers($secret, $eventId, $body, 1763400001);
        $this->assertNotSame($headers['X-Payment-Signature'], $nextAttempt['X-Payment-Signature']);
    }

    public function testRequiresAConfiguredSigningSecret(): void
    {
        $this->expectException(RuntimeException::class);
        WebhookDeliverySigner::headers('', 'event-id', '{}', 1763400000);
    }
}
