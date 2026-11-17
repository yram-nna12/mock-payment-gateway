<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

final class DatabaseWebhookDeliveryStore implements WebhookDeliveryStoreInterface
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function claimNext(string $now, int $leaseSeconds): ?array
    {
        if ($leaseSeconds < 1) {
            throw new RuntimeException('Webhook delivery lease must be positive.');
        }

        $leaseExpiredAt = gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') - $leaseSeconds);
        $this->db->transException(true);
        try {
            if (!$this->db->transBegin()) {
                throw new RuntimeException('Could not begin webhook delivery claim.');
            }

            $row = $this->db->query(
                'SELECT * FROM ' . $this->db->prefixTable('webhook_deliveries')
                    . ' WHERE delivery_status = ?'
                    . ' AND (next_retry_at IS NULL OR next_retry_at <= ?)'
                    . ' AND (locked_at IS NULL OR locked_at <= ?)'
                    . ' ORDER BY next_retry_at, id LIMIT 1 FOR UPDATE SKIP LOCKED',
                ['pending', $now, $leaseExpiredAt]
            )->getRowArray();

            if ($row === null) {
                if (!$this->db->transCommit()) {
                    throw new RuntimeException('Webhook delivery claim transaction failed.');
                }
                return null;
            }

            $leaseToken = PaymentWebhookEventFactory::newEventId();
            $attempts = (int) $row['attempts'] + 1;
            $this->db->table('webhook_deliveries')->where('id', $row['id'])->update([
                'attempts' => $attempts,
                'locked_at' => $now,
                'lease_token' => $leaseToken,
                'updated_at' => $now,
            ]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Webhook delivery could not be claimed.');
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Webhook delivery claim transaction failed.');
            }

            $row['attempts'] = $attempts;
            $row['locked_at'] = $now;
            $row['lease_token'] = $leaseToken;

            return $row;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        } finally {
            $this->db->transException(false);
        }
    }

    public function complete(
        array $claim,
        string $status,
        ?int $responseCode,
        ?string $errorCode,
        ?string $nextRetryAt,
        ?string $deliveredAt,
        string $updatedAt
    ): void {
        if (!in_array($status, ['pending', 'delivered', 'failed'], true)) {
            throw new RuntimeException('Invalid webhook delivery status.');
        }

        $this->db->table('webhook_deliveries')
            ->where('id', (int) $claim['id'])
            ->where('lease_token', (string) $claim['lease_token'])
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => $status,
                'response_code' => $responseCode,
                'last_error' => $errorCode,
                'next_retry_at' => $nextRetryAt,
                'locked_at' => null,
                'lease_token' => null,
                'delivered_at' => $deliveredAt,
                'updated_at' => $updatedAt,
            ]);

        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('Webhook delivery lease was lost before completion.');
        }
    }
}
