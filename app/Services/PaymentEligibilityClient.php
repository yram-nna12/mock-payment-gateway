<?php

namespace App\Services;

use App\Libraries\PaymentException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class PaymentEligibilityClient
{
    public function assertEligible(array $record): string
    {
        $config = config('PaymentGateway');
        $url = preg_replace('~/api/v1/webhooks/payments/?$~', '/api/v1/payments/eligibility', $config->techReserveWebhookUrl);
        if ($config->webhookSecret === '' || !is_string($url) || $url === $config->techReserveWebhookUrl) {
            throw new PaymentException(503, 'eligibility_unavailable', 'Payment cannot continue right now. Your existing transaction has been retained.');
        }
        $payload = ['transaction_reference' => $record['transaction_reference'], 'gateway_transaction_id' => $record['gateway_transaction_id'],
            'amount' => $record['amount'], 'currency' => $record['currency'], 'payment_method' => $record['payment_method']];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $nonce = bin2hex(random_bytes(32));
        $timestamp = (string) time();
        $signature = 'v1=' . hash_hmac('sha256', "eligibility-request-v1\n" . $timestamp . "\n" . $nonce . "\n" . $body, $config->webhookSecret);
        try {
            WebhookUrlPolicy::assertAllowed($config->techReserveWebhookUrl);
            $client = single_service('curlrequest', ['timeout' => 5, 'connect_timeout' => 2, 'http_errors' => false, 'allow_redirects' => false]);
            $response = $client->request('POST', $url, ['headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json',
                'X-Eligibility-Timestamp' => $timestamp, 'X-Eligibility-Nonce' => $nonce, 'X-Eligibility-Signature' => $signature], 'body' => $body]);
            $responseBody = $response->getBody();
            if ($response->getStatusCode() !== 200) throw new \RuntimeException('Invalid eligibility response.');
            return self::validateResponse($responseBody, $response->getHeaderLine('X-Eligibility-Signature'), $nonce, $config->webhookSecret, $record, time());
        } catch (PaymentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            log_message('warning', 'Payment eligibility verification failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            throw new PaymentException(503, 'eligibility_unavailable', 'Payment cannot continue right now. Your existing transaction has been retained.');
        }
    }

    public static function validateResponse(string $responseBody, string $signature, string $nonce, string $secret, array $record, int $now): string
    {
            $expected = 'v1=' . hash_hmac('sha256', "eligibility-response-v1\n" . $nonce . "\n" . $responseBody, $secret);
            if ($secret === '' || !preg_match('/\A[a-f0-9]{64}\z/', $nonce) || !hash_equals($expected, $signature)) throw new \RuntimeException('Invalid eligibility signature.');
            $decoded = json_decode($responseBody, true, 16, JSON_THROW_ON_ERROR);
            $data = $decoded['data'] ?? null;
            if (!is_array($data) || !is_bool($data['eligible'] ?? null) || !is_int($data['issued_at'] ?? null)
                || abs($now - $data['issued_at']) > 30 || ($data['transaction_reference'] ?? null) !== $record['transaction_reference']
                || ($data['gateway_transaction_id'] ?? null) !== $record['gateway_transaction_id']
                || ($data['reservation_reference'] ?? null) !== $record['reservation_reference']
                || ($data['amount'] ?? null) !== $record['amount'] || ($data['currency'] ?? null) !== $record['currency']) {
                throw new \RuntimeException('Mismatched eligibility response.');
            }
            if (!$data['eligible']) {
                throw new PaymentException(409, ($data['reason'] ?? '') === 'deadline_passed' ? 'deadline_passed' : 'reservation_ineligible', 'This reservation is not eligible for payment.');
            }
            $deadline = $data['deadline_utc'] ?? null;
            $parsed = is_string($deadline) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $deadline, new DateTimeZone('UTC')) : false;
            if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $deadline) throw new \RuntimeException('Invalid deadline.');
            if ($parsed->getTimestamp() <= $now) throw new PaymentException(409, 'deadline_passed', 'The payment period has ended.');
            return $deadline;
    }
}
