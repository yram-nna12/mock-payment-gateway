<?php

namespace App\Services;

use RuntimeException;

final class WebhookDeliverySigner
{
    public static function headers(string $secret, string $eventId, string $body, ?int $timestamp = null): array
    {
        if (strlen($secret) < 32) {
            throw new RuntimeException('Payment webhook signing is not configured.');
        }

        $sentAt = (string) ($timestamp ?? time());
        $signature = hash_hmac('sha256', $sentAt . "\n" . $eventId . "\n" . $body, $secret);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Payment-Event-Id' => $eventId,
            'X-Payment-Timestamp' => $sentAt,
            'X-Payment-Signature' => 'v1=' . $signature,
        ];
    }
}
