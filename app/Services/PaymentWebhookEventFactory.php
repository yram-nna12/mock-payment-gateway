<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final class PaymentWebhookEventFactory
{
    public static function newEventId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public static function encode(array $payment, string $eventId, string $occurredAt): string
    {
        $status = $payment['payment_status'] ?? null;
        if (!in_array($status, ['paid', 'failed', 'cancelled', 'expired'], true)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $eventId) !== 1
            || !self::isDate($occurredAt)
            || !is_string($payment['transaction_reference'] ?? null)
            || !is_string($payment['reservation_reference'] ?? null)
            || !is_string($payment['gateway_transaction_id'] ?? null)
            || !is_string($payment['amount'] ?? null)
            || !is_string($payment['currency'] ?? null)
            || !is_string($payment['payment_method'] ?? null)
            || !self::isDate($payment['expires_at'] ?? null)
            || ($status === 'paid' && !self::isDate($payment['paid_at'] ?? null))
            || ($status !== 'paid' && ($payment['paid_at'] ?? null) !== null)) {
            throw new InvalidArgumentException('The payment cannot be encoded as a webhook event.');
        }

        $event = [
            'event_id' => $eventId,
            'event_type' => 'payment.status.changed',
            'event_version' => 1,
            'occurred_at' => $occurredAt,
            'transaction_reference' => $payment['transaction_reference'],
            'reservation_reference' => $payment['reservation_reference'],
            'gateway_transaction_id' => $payment['gateway_transaction_id'],
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'payment_method' => $payment['payment_method'],
            'status' => $status,
            'paid_at' => $payment['paid_at'] ?? null,
            'expires_at' => $payment['expires_at'],
        ];

        try {
            return json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The payment webhook event could not be encoded.', 0, $exception);
        }
    }

    private static function isDate(mixed $value): bool
    {
        if (!is_string($value)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d H:i:s') === $value;
    }
}
