<?php

namespace App\Services;

use App\Libraries\PaymentException;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

class PaymentService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function initiate(array $input): array
    {
        $payload = PaymentPayload::normalize($input);
        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->db->transException(true);
        $this->db->transBegin();

        try {
            $existing = $this->find($payload['transaction_reference']);
            if ($existing) {
                $existing = $this->find($payload['transaction_reference'], true);
                if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
                    throw new PaymentException(409, 'reference_conflict', 'This transaction reference is already used for a different payment request.');
                }
                $existing = $this->expireIfDue($existing);
                $this->commit();
                return $this->initiationResponse($existing, false);
            }

            $now = gmdate('Y-m-d H:i:s');
            $record = $payload;
            $record['rented_items'] = json_encode($payload['rented_items'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            unset($record['reservation_reference'], $record['student_id'], $record['student_name']);
            $record += [
                'reservation_reference' => $payload['reservation_reference'],
                'student_id' => $payload['student_id'],
                'student_name' => $payload['student_name'],
                'gateway_transaction_id' => 'gw_' . bin2hex(random_bytes(16)),
                'request_hash' => $requestHash,
                'payment_status' => 'pending',
                'paid_at' => null,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + config('PaymentGateway')->expirationMinutes * 60),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (!$this->db->table('payment_transactions')->insert($record)) {
                throw new \RuntimeException('Payment transaction insert failed.');
            }
            $record['id'] = (int) $this->db->insertID();
            $this->commit();
            return $this->initiationResponse($record, true);
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            $this->db->transException(false);
            if ($this->isDuplicateKey($exception)) {
                $existing = $this->find($payload['transaction_reference']);
                if ($existing) {
                    if (!hash_equals((string) $existing['request_hash'], $requestHash)) {
                        throw new PaymentException(409, 'reference_conflict', 'This transaction reference is already used for a different payment request.');
                    }
                    return $this->initiationResponse($existing, false);
                }
            }
            throw $exception;
        } finally {
            $this->db->transException(false);
        }
    }

    public function status(string $reference): array
    {
        return $this->withLocked($reference, fn(array $record): array => $this->publicStatus($this->expireIfDue($record)));
    }

    public function checkout(string $reference, string $token): array
    {
        return $this->withLocked($reference, function (array $record) use ($reference, $token): array {
            $this->assertCheckoutToken($reference, $token);
            $record = $this->expireIfDue($record);
            $items = json_decode((string) $record['rented_items'], true, 512, JSON_THROW_ON_ERROR);
            return $record + ['rented_items_list' => $items];
        });
    }

    public function settle(string $reference, string $token, string $outcome): array
    {
        if (!in_array($outcome, ['paid', 'failed'], true)) {
            throw new PaymentException(422, 'invalid_outcome', 'Unsupported simulated payment outcome.');
        }

        return $this->withLocked($reference, function (array $record) use ($reference, $token, $outcome): array {
            $this->assertCheckoutToken($reference, $token);
            $record = $this->expireIfDue($record);
            if ($record['payment_status'] === 'pending') {
                $now = gmdate('Y-m-d H:i:s');
                $update = ['payment_status' => $outcome, 'updated_at' => $now];
                if ($outcome === 'paid') {
                    $update['paid_at'] = $now;
                }
                $this->db->table('payment_transactions')->where('id', $record['id'])
                    ->where('payment_status', 'pending')->update($update);
                $record = array_merge($record, $update);
                $this->queueWebhookDelivery($record);
            }
            return $record;
        });
    }

    private function withLocked(string $reference, callable $operation): array
    {
        $this->db->transException(true);
        $this->db->transBegin();
        try {
            $record = $this->find($reference, true);
            if (!$record) {
                throw new PaymentException(404, 'payment_not_found', 'Payment transaction not found.');
            }
            $result = $operation($record);
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $exception;
        } finally {
            $this->db->transException(false);
        }
    }

    private function find(string $reference, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM ' . $this->db->prefixTable('payment_transactions')
            . ' WHERE transaction_reference = ?' . ($lock ? ' FOR UPDATE' : '');
        return $this->db->query($sql, [$reference])->getRowArray() ?: null;
    }

    private function expireIfDue(array $record): array
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($record['payment_status'] === 'pending' && $record['expires_at'] <= $now) {
            $this->db->table('payment_transactions')->where('id', $record['id'])
                ->where('payment_status', 'pending')->where('expires_at <=', $now)
                ->update(['payment_status' => 'expired', 'updated_at' => $now]);
            $record['payment_status'] = 'expired';
            $record['updated_at'] = $now;
            $this->queueWebhookDelivery($record);
        }
        return $record;
    }

    private function queueWebhookDelivery(array $payment): void
    {
        $settings = config('PaymentGateway');
        WebhookUrlPolicy::assertAllowed($settings->techReserveWebhookUrl);

        $eventId = PaymentWebhookEventFactory::newEventId();
        $eventType = 'payment.status.changed';
        $occurredAt = $payment['updated_at'] ?? gmdate('Y-m-d H:i:s');
        $body = PaymentWebhookEventFactory::encode($payment, $eventId, $occurredAt);
        $now = gmdate('Y-m-d H:i:s');

        if (!$this->db->table('webhook_deliveries')->insert([
            'payment_transaction_id' => $payment['id'],
            'event_id' => $eventId,
            'callback_url' => $settings->techReserveWebhookUrl,
            'event_type' => $eventType,
            'payload' => $body,
            'delivery_status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'response_code' => null,
            'last_error' => null,
            'next_retry_at' => $now,
            'locked_at' => null,
            'lease_token' => null,
            'delivered_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])) {
            throw new \RuntimeException('The payment webhook event could not be queued.');
        }
    }

    private function assertCheckoutToken(string $reference, string $token): void
    {
        $key = config('PaymentGateway')->apiKey;
        if ($key === '' || !hash_equals(hash_hmac('sha256', $reference, $key), $token)) {
            throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
        }
    }

    private function commit(): void
    {
        if (!$this->db->transStatus() || !$this->db->transCommit()) {
            throw new \RuntimeException('Payment transaction could not be committed.');
        }
        $this->db->resetTransStatus();
    }

    private function isDuplicateKey(Throwable $exception): bool
    {
        for ($cause = $exception; $cause; $cause = $cause->getPrevious()) {
            if ((int) $cause->getCode() === 1062) {
                return true;
            }
        }
        return false;
    }

    private function initiationResponse(array $record, bool $created): array
    {
        $reference = (string) $record['transaction_reference'];
        $token = hash_hmac('sha256', $reference, config('PaymentGateway')->apiKey);
        return [
            'transaction_reference' => $reference,
            'gateway_transaction_id' => $record['gateway_transaction_id'],
            'reservation_reference' => $record['reservation_reference'],
            'amount' => $record['amount'],
            'currency' => $record['currency'],
            'payment_method' => $record['payment_method'],
            'status' => $record['payment_status'],
            'created' => $created,
            'created_at' => $record['created_at'],
            'expires_at' => $record['expires_at'],
            'checkout_url' => rtrim(config('App')->baseURL, '/') . '/checkout/' . rawurlencode($reference) . '/' . $token,
        ];
    }

    private function publicStatus(array $record): array
    {
        return [
            'transaction_reference' => $record['transaction_reference'],
            'gateway_transaction_id' => $record['gateway_transaction_id'],
            'amount' => $record['amount'],
            'currency' => $record['currency'],
            'payment_method' => $record['payment_method'],
            'status' => $record['payment_status'],
            'created_at' => $record['created_at'],
            'paid_at' => $record['paid_at'],
            'expires_at' => $record['expires_at'],
            'updated_at' => $record['updated_at'],
        ];
    }
}
