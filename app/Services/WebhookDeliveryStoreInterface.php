<?php

namespace App\Services;

interface WebhookDeliveryStoreInterface
{
    public function claimNext(string $now, int $leaseSeconds): ?array;

    public function complete(
        array $claim,
        string $status,
        ?int $responseCode,
        ?string $errorCode,
        ?string $nextRetryAt,
        ?string $deliveredAt,
        string $updatedAt
    ): void;
}
