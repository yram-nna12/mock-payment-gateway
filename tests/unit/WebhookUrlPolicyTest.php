<?php

use App\Services\WebhookUrlPolicy;
use PHPUnit\Framework\TestCase;

final class WebhookUrlPolicyTest extends TestCase
{
    public function testAllowsFixedLoopbackHttpAndHttpsDestinations(): void
    {
        WebhookUrlPolicy::assertAllowed('http://127.0.0.1:8083/api/v1/webhooks/payments');
        WebhookUrlPolicy::assertAllowed('https://techreserve.example.test/api/v1/webhooks/payments');

        $this->assertTrue(true);
    }

    public function testRejectsUntrustedHttpArbitraryPathsAndCredentials(): void
    {
        foreach ([
            'http://example.test/api/v1/webhooks/payments',
            'https://techreserve.example.test/other',
            'https://user:pass@techreserve.example.test/api/v1/webhooks/payments',
            'https://techreserve.example.test/api/v1/webhooks/payments?next=http://attacker.test',
        ] as $url) {
            try {
                WebhookUrlPolicy::assertAllowed($url);
                $this->fail('An unsafe webhook destination was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
