<?php

namespace App\Services;

interface WebhookDeliveryTransportInterface
{
    public function post(string $url, string $body, array $headers): int;
}
