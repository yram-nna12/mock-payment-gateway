<?php

use App\Services\WebhookDeliveryRetryPolicy;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryRetryPolicyTest extends TestCase
{
    public function testRetriesOnlyTransientHttpStatuses(): void
    {
        $policy = new WebhookDeliveryRetryPolicy();

        foreach ([408, 429, 500, 503, 599] as $status) {
            $this->assertTrue($policy->isTemporaryHttpStatus($status));
        }
        foreach ([200, 301, 400, 401, 404] as $status) {
            $this->assertFalse($policy->isTemporaryHttpStatus($status));
        }
    }

    public function testUsesCappedExponentialBackoff(): void
    {
        $policy = new WebhookDeliveryRetryPolicy();
        $now = 1763400000;

        $this->assertSame(gmdate('Y-m-d H:i:s', $now + 5), $policy->nextRetryAt(1, $now, 5, 20));
        $this->assertSame(gmdate('Y-m-d H:i:s', $now + 10), $policy->nextRetryAt(2, $now, 5, 20));
        $this->assertSame(gmdate('Y-m-d H:i:s', $now + 20), $policy->nextRetryAt(6, $now, 5, 20));
    }
}
