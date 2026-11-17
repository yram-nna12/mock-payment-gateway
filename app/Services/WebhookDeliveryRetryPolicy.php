<?php

namespace App\Services;

final class WebhookDeliveryRetryPolicy
{
    public function isTemporaryHttpStatus(int $status): bool
    {
        return $status === 408 || $status === 429 || ($status >= 500 && $status <= 599);
    }

    public function nextRetryAt(
        int $attempt,
        int $now,
        int $baseDelaySeconds,
        int $maxDelaySeconds
    ): string {
        if ($attempt < 1 || $baseDelaySeconds < 1 || $maxDelaySeconds < $baseDelaySeconds) {
            throw new \InvalidArgumentException('Webhook retry policy parameters are invalid.');
        }

        $exponent = min($attempt - 1, 20);
        $delay = min($maxDelaySeconds, $baseDelaySeconds * (2 ** $exponent));

        return gmdate('Y-m-d H:i:s', $now + $delay);
    }
}
