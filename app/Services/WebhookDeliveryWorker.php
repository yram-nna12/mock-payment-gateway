<?php

namespace App\Services;

use Config\PaymentGateway;
use RuntimeException;
use Throwable;

final class WebhookDeliveryWorker
{
    private WebhookDeliveryStoreInterface $store;
    private WebhookDeliveryTransportInterface $transport;
    private WebhookDeliveryRetryPolicy $retryPolicy;
    private PaymentGateway $settings;

    public function __construct(
        ?WebhookDeliveryStoreInterface $store = null,
        ?WebhookDeliveryTransportInterface $transport = null,
        ?WebhookDeliveryRetryPolicy $retryPolicy = null,
        ?PaymentGateway $settings = null
    ) {
        $this->settings = $settings ?? config('PaymentGateway');
        $this->store = $store ?? new DatabaseWebhookDeliveryStore();
        $this->transport = $transport ?? new CodeIgniterWebhookDeliveryTransport($this->settings);
        $this->retryPolicy = $retryPolicy ?? new WebhookDeliveryRetryPolicy();
    }

    public function run(int $limit = 25): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Webhook delivery limit must be between 1 and 100.');
        }
        if (strlen($this->settings->webhookSecret) < 32) {
            throw new RuntimeException('Payment webhook signing is not configured.');
        }
        WebhookUrlPolicy::assertAllowed($this->settings->techReserveWebhookUrl);

        $report = ['processed' => 0, 'delivered' => 0, 'retrying' => 0, 'failed' => 0];
        while ($report['processed'] < $limit) {
            $now = gmdate('Y-m-d H:i:s');
            $claim = $this->store->claimNext($now, $this->settings->webhookLeaseSeconds);
            if ($claim === null) {
                break;
            }

            $report['processed']++;
            $this->deliver($claim, $report);
        }

        return $report;
    }

    private function deliver(array $claim, array &$report): void
    {
        $now = time();
        $nowText = gmdate('Y-m-d H:i:s', $now);
        $eventId = (string) ($claim['event_id'] ?? '');
        $body = $claim['payload'] ?? null;

        if (!is_string($body) || strlen($body) > 8192 || !$this->validStoredEvent($body, $eventId)) {
            $this->finish($claim, 'failed', null, 'invalid_event_record', null, null, $nowText);
            $report['failed']++;
            return;
        }

        try {
            if (!is_string($claim['callback_url'] ?? null)
                || !hash_equals($this->settings->techReserveWebhookUrl, $claim['callback_url'])) {
                throw new \InvalidArgumentException('The stored webhook destination does not match server configuration.');
            }
            WebhookUrlPolicy::assertAllowed($claim['callback_url']);
            $headers = WebhookDeliverySigner::headers(
                $this->settings->webhookSecret,
                $eventId,
                $body,
                $now
            );
            $status = $this->transport->post((string) $claim['callback_url'], $body, $headers);
        } catch (WebhookDeliveryTransportException) {
            log_message('warning', 'Payment webhook delivery {deliveryId} encountered a transport failure.', [
                'deliveryId' => (int) $claim['id'],
            ]);
            $this->temporaryFailure($claim, null, 'network_error', $now, $nowText, $report);
            return;
        } catch (\InvalidArgumentException) {
            $this->finish($claim, 'failed', null, 'invalid_destination', null, null, $nowText);
            $report['failed']++;
            return;
        } catch (Throwable $exception) {
            log_message('error', 'Payment webhook delivery {deliveryId} failed ({exceptionClass}).', [
                'deliveryId' => (int) $claim['id'],
                'exceptionClass' => $exception::class,
            ]);
            $this->temporaryFailure($claim, null, 'worker_error', $now, $nowText, $report);
            return;
        }

        if ($status >= 200 && $status < 300) {
            $this->finish($claim, 'delivered', $status, null, null, $nowText, $nowText);
            $report['delivered']++;
            return;
        }

        $errorCode = 'http_' . $status;
        if ($this->retryPolicy->isTemporaryHttpStatus($status)) {
            $this->temporaryFailure($claim, $status, $errorCode, $now, $nowText, $report);
            return;
        }

        $this->finish($claim, 'failed', $status, $errorCode, null, null, $nowText);
        $report['failed']++;
    }

    private function temporaryFailure(
        array $claim,
        ?int $status,
        string $errorCode,
        int $now,
        string $nowText,
        array &$report
    ): void {
        $maxAttempts = max(1, (int) ($claim['max_attempts'] ?? 3));
        if ((int) $claim['attempts'] >= $maxAttempts) {
            $this->finish($claim, 'failed', $status, $errorCode, null, null, $nowText);
            $report['failed']++;
            return;
        }

        $nextRetryAt = $this->retryPolicy->nextRetryAt(
            (int) $claim['attempts'],
            $now,
            $this->settings->webhookRetryBaseDelaySeconds,
            $this->settings->webhookRetryMaxDelaySeconds
        );
        $this->finish($claim, 'pending', $status, $errorCode, $nextRetryAt, null, $nowText);
        $report['retrying']++;
    }

    private function finish(
        array $claim,
        string $status,
        ?int $responseCode,
        ?string $errorCode,
        ?string $nextRetryAt,
        ?string $deliveredAt,
        string $updatedAt
    ): void {
        $this->store->complete(
            $claim,
            $status,
            $responseCode,
            $errorCode,
            $nextRetryAt,
            $deliveredAt,
            $updatedAt
        );
    }

    private function validStoredEvent(string $body, string $eventId): bool
    {
        try {
            $event = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($event)
            && ($event['event_id'] ?? null) === $eventId
            && ($event['event_type'] ?? null) === 'payment.status.changed'
            && ($event['event_version'] ?? null) === 1;
    }
}
