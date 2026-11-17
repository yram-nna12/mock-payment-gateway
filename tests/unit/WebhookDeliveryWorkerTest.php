<?php

use App\Services\WebhookDeliveryTransportInterface;
use App\Services\WebhookDeliveryWorker;
use App\Services\WebhookDeliveryStoreInterface;
use Config\PaymentGateway;
use PHPUnit\Framework\TestCase;

final class WebhookDeliveryWorkerTest extends TestCase
{
    private const SECRET = 'unit-test-webhook-signing-secret-0123456789';
    private const EVENT_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testRetriesTransientResponseAndKeepsEventBodyStable(): void
    {
        $store = new FakeWebhookDeliveryStore($this->delivery());
        $transport = new FakeWebhookDeliveryTransport([503, 204]);
        $worker = $this->worker($store, $transport);

        $first = $worker->run();
        $this->assertSame(['processed' => 1, 'delivered' => 0, 'retrying' => 1, 'failed' => 0], $first);
        $this->assertSame('pending', $store->delivery['delivery_status']);
        $store->delivery['next_retry_at'] = gmdate('Y-m-d H:i:s', time() - 1);

        $second = $worker->run();
        $this->assertSame(['processed' => 1, 'delivered' => 1, 'retrying' => 0, 'failed' => 0], $second);
        $this->assertSame('delivered', $store->delivery['delivery_status']);
        $this->assertSame($transport->bodies[0], $transport->bodies[1]);
        $this->assertSame(self::EVENT_ID, $transport->headers[0]['X-Payment-Event-Id']);
        foreach (array_keys($transport->headers) as $index) {
            $timestamp = $transport->headers[$index]['X-Payment-Timestamp'];
            $expected = 'v1=' . hash_hmac(
                'sha256',
                $timestamp . "\n" . self::EVENT_ID . "\n" . $transport->bodies[$index],
                self::SECRET
            );
            $this->assertSame($expected, $transport->headers[$index]['X-Payment-Signature']);
        }
    }

    public function testPermanentValidationFailureIsNotRetried(): void
    {
        $store = new FakeWebhookDeliveryStore($this->delivery());
        $transport = new FakeWebhookDeliveryTransport([422]);
        $report = $this->worker($store, $transport)->run();

        $this->assertSame(['processed' => 1, 'delivered' => 0, 'retrying' => 0, 'failed' => 1], $report);
        $this->assertSame('failed', $store->delivery['delivery_status']);
        $this->assertSame('http_422', $store->delivery['last_error']);
    }

    public function testNetworkTimeoutIsRetried(): void
    {
        $store = new FakeWebhookDeliveryStore($this->delivery());
        $transport = new FakeWebhookDeliveryTransport([new RuntimeException('timeout')]);
        $report = $this->worker($store, $transport)->run();

        $this->assertSame(['processed' => 1, 'delivered' => 0, 'retrying' => 1, 'failed' => 0], $report);
        $this->assertSame('network_error', $store->delivery['last_error']);
    }

    public function testExhaustedTransientRetriesBecomePermanentFailures(): void
    {
        $delivery = array_replace($this->delivery(), ['attempts' => 2]);
        $store = new FakeWebhookDeliveryStore($delivery);
        $report = $this->worker($store, new FakeWebhookDeliveryTransport([503]))->run();

        $this->assertSame(['processed' => 1, 'delivered' => 0, 'retrying' => 0, 'failed' => 1], $report);
        $this->assertSame('failed', $store->delivery['delivery_status']);
        $this->assertSame(503, $store->delivery['response_code']);
    }

    public function testConcurrentWorkerCannotClaimAnAlreadyLeasedDelivery(): void
    {
        $store = new FakeWebhookDeliveryStore($this->delivery());
        $competingTransport = new FakeWebhookDeliveryTransport([204]);
        $competingWorker = $this->worker($store, $competingTransport);
        $competingReport = [];
        $transport = new FakeWebhookDeliveryTransport([204], static function () use ($competingWorker, &$competingReport): void {
            $competingReport = $competingWorker->run();
        });
        $worker = $this->worker($store, $transport);

        $report = $worker->run();

        $this->assertSame(1, $report['delivered']);
        $this->assertSame(0, $competingReport['processed']);
        $this->assertSame(0, $competingTransport->calls);
    }

    private function worker(FakeWebhookDeliveryStore $store, FakeWebhookDeliveryTransport $transport): WebhookDeliveryWorker
    {
        $settings = new PaymentGateway();
        $settings->webhookSecret = self::SECRET;
        $settings->techReserveWebhookUrl = 'http://127.0.0.1:8083/api/v1/webhooks/payments';
        $settings->webhookRetryBaseDelaySeconds = 1;
        $settings->webhookRetryMaxDelaySeconds = 10;

        return new WebhookDeliveryWorker($store, $transport, null, $settings);
    }

    private function delivery(): array
    {
        $body = json_encode([
            'event_id' => self::EVENT_ID,
            'event_type' => 'payment.status.changed',
            'event_version' => 1,
        ], JSON_THROW_ON_ERROR);

        return [
            'id' => 1,
            'event_id' => self::EVENT_ID,
            'payment_transaction_id' => 7,
            'callback_url' => 'http://127.0.0.1:8083/api/v1/webhooks/payments',
            'event_type' => 'payment.status.changed',
            'payload' => $body,
            'delivery_status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'next_retry_at' => gmdate('Y-m-d H:i:s', time() - 1),
        ];
    }
}

final class FakeWebhookDeliveryStore implements WebhookDeliveryStoreInterface
{
    public array $delivery;
    private bool $locked = false;
    private ?string $leaseToken = null;

    public function __construct(array $delivery)
    {
        $this->delivery = $delivery;
    }

    public function claimNext(string $now, int $leaseSeconds): ?array
    {
        if ($this->locked || $this->delivery['delivery_status'] !== 'pending'
            || $this->delivery['next_retry_at'] > $now) {
            return null;
        }

        $this->locked = true;
        $this->leaseToken = bin2hex(random_bytes(16));
        $this->delivery['attempts']++;
        $this->delivery['lease_token'] = $this->leaseToken;

        return $this->delivery;
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
        if (!$this->locked || $claim['lease_token'] !== $this->leaseToken) {
            throw new RuntimeException('Lease lost.');
        }

        $this->delivery = array_merge($this->delivery, [
            'delivery_status' => $status,
            'response_code' => $responseCode,
            'last_error' => $errorCode,
            'next_retry_at' => $nextRetryAt,
            'delivered_at' => $deliveredAt,
            'updated_at' => $updatedAt,
            'lease_token' => null,
        ]);
        $this->locked = false;
    }
}

final class FakeWebhookDeliveryTransport implements WebhookDeliveryTransportInterface
{
    private array $outcomes;
    private $onPost;
    public array $bodies = [];
    public array $headers = [];
    public int $calls = 0;

    public function __construct(array $outcomes, ?callable $onPost = null)
    {
        $this->outcomes = $outcomes;
        $this->onPost = $onPost;
    }

    public function post(string $url, string $body, array $headers): int
    {
        $this->calls++;
        $this->bodies[] = $body;
        $this->headers[] = $headers;
        if ($this->onPost !== null) {
            ($this->onPost)();
        }

        $outcome = array_shift($this->outcomes);
        if ($outcome instanceof Throwable) {
            throw new \App\Services\WebhookDeliveryTransportException('network_error', $outcome);
        }

        return $outcome;
    }
}
