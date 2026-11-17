<?php

use App\Database\Migrations\AddGatewayTransactionFields;
use App\Database\Migrations\CreatePaymentTransactionsTable;
use App\Services\PaymentService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

final class PaymentGatewayTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private static bool $enabled = false;
    private static ?BaseConnection $testDb = null;
    private static string $apiKey = '';

    public static function setUpBeforeClass(): void
    {
        self::$enabled = getenv('MOCK_PAYMENT_GATEWAY_INTEGRATION') === '1';
        if (!self::$enabled) {
            return;
        }

        $database = config('Database');
        Tests\Support\IsolatedMySqlDatabase::verify($database->tests, $database->default, 'mock_payment_gateway_test_db');
        self::$apiKey = bin2hex(random_bytes(32));
        config('PaymentGateway')->apiKey = self::$apiKey;

        $config = $database->tests;
        $config['DBPrefix'] = 'mpg_test_';
        self::$testDb = Database::connect($config, false);
        $forge = Database::forge(self::$testDb);
        require_once APPPATH . 'Database/Migrations/2026-11-16-170150_CreatePaymentTransactionsTable.php';
        require_once APPPATH . 'Database/Migrations/2026-11-17-000001_AddGatewayTransactionFields.php';
        (new CreatePaymentTransactionsTable($forge))->up();
        (new AddGatewayTransactionFields($forge))->up();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$enabled) {
            $this->markTestSkipped('Set MOCK_PAYMENT_GATEWAY_INTEGRATION=1 with the isolated MySQL test database configured.');
        }
        config('PaymentGateway')->apiKey = self::$apiKey;
        self::$testDb->table('payment_transactions')->emptyTable();
    }

    protected function tearDown(): void
    {
        if (self::$enabled) {
            self::$testDb->table('payment_transactions')->emptyTable();
        }
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testDb instanceof BaseConnection && self::$testDb->tableExists('payment_transactions')) {
            Database::forge(self::$testDb)->dropTable('payment_transactions', true);
        }
    }

    public function testSuccessfulAndFailedSupportedPaymentSimulations(): void
    {
        foreach ([['gcash', 'paid'], ['gcash', 'failed'], ['card', 'paid'], ['card', 'failed'], ['bank_transfer', 'paid'], ['bank_transfer', 'failed']] as [$method, $outcome]) {
            $response = $this->initiate($this->payload(['payment_method' => $method]));
            $response->assertStatusCode(201);
            $url = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['checkout_url'];
            $parts = parse_url($url);
            $path = $parts['path'];
            $segments = explode('/', trim($path, '/'));
            $reference = $segments[1];
            $token = $segments[2];
            $record = (new PaymentService(self::$testDb))->settle($reference, $token, $outcome);
            $this->assertSame($outcome, $record['payment_status']);
        }
    }

    public function testApiRejectsMissingAndInvalidCredentials(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])->withBodyFormat('json')
            ->post('/api/v1/payments', $this->payload())->assertStatusCode(401);
        $this->withHeaders(['X-API-Key' => 'wrong', 'Accept' => 'application/json'])->withBodyFormat('json')
            ->post('/api/v1/payments', $this->payload())->assertStatusCode(401);
    }

    public function testRejectsMalformedPayloadAndUnsupportedMethodsOrCurrency(): void
    {
        $this->assertSame(400, $this->initiateRaw('{')->response()->getStatusCode());
        foreach ([['amount' => '12.001'], ['payment_method' => 'real_card'], ['currency' => 'USD']] as $override) {
            $this->initiate($this->payload($override))->assertStatusCode(422);
        }
    }

    public function testDuplicateIdenticalInitiationReturnsOriginalAndConflictingPayloadIsRejected(): void
    {
        $payload = $this->payload();
        $first = $this->initiate($payload);
        $first->assertStatusCode(201);
        $transactionId = json_decode($first->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['gateway_transaction_id'];

        $retry = $this->initiate($payload);
        $retry->assertStatusCode(200);
        $this->assertSame($transactionId, json_decode($retry->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['gateway_transaction_id']);
        $this->initiate($this->payload(['amount' => '1501.00']))->assertStatusCode(409);
        $this->assertSame(1, self::$testDb->table('payment_transactions')->countAllResults());
    }

    public function testStatusLookupIsAuthenticatedAndReturnsPersistedAmountAndCurrency(): void
    {
        $response = $this->initiate($this->payload());
        $response->assertStatusCode(201);
        $status = $this->withHeaders(['X-API-Key' => self::$apiKey])->get('/api/v1/payments/TRX-123');
        $status->assertStatusCode(200)->assertJSONFragment([
            'transaction_reference' => 'TRX-123',
            'amount' => '1500.00',
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
        $this->withHeaders(['X-API-Key' => self::$apiKey])->get('/api/v1/payments/unknown')->assertStatusCode(404);
    }

    public function testLookupExpiresPendingAttemptWithoutChangingOriginalExpiry(): void
    {
        $response = $this->initiate($this->payload());
        $data = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $originalExpiry = $data['expires_at'];
        self::$testDb->table('payment_transactions')->where('transaction_reference', 'TRX-123')
            ->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $status = $this->withHeaders(['X-API-Key' => self::$apiKey])->get('/api/v1/payments/TRX-123');
        $status->assertStatusCode(200)->assertJSONFragment(['status' => 'expired']);
        $retry = $this->initiate($this->payload());
        $retry->assertStatusCode(200);
        $retryData = json_decode($retry->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $this->assertSame($originalExpiry, $retryData['expires_at']);
        $this->assertSame('expired', $retryData['status']);
    }

    public function testCheckoutUsesOpaqueTokenAndKeepsTerminalStateImmutable(): void
    {
        $response = $this->initiate($this->payload());
        $data = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $url = parse_url($data['checkout_url'], PHP_URL_PATH);
        $this->get($url)->assertStatusCode(200);
        $segments = explode('/', trim($url, '/'));
        $reference = $segments[1];
        $token = $segments[2];
        $this->get('/checkout/' . $reference . '/invalid-token')->assertStatusCode(404);

        $service = new PaymentService(self::$testDb);
        $service->settle($reference, $token, 'paid');
        $service->settle($reference, $token, 'failed');
        $this->assertSame('paid', $service->status($reference)['status']);
    }

    public function testCheckoutPostRequiresCsrfToken(): void
    {
        $response = $this->initiate($this->payload());
        $data = json_decode($response->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $url = parse_url($data['checkout_url'], PHP_URL_PATH);
        $this->get($url);
        $segments = explode('/', trim($url, '/'));
        $result = $this->post('/checkout/' . $segments[1] . '/' . $segments[2] . '/success');
        $this->assertContains($result->response()->getStatusCode(), [400, 403]);
        $this->assertSame('pending', (new PaymentService(self::$testDb))->status('TRX-123')['status']);
    }

    public function testConcurrentIdenticalInitiationCreatesOneTransaction(): void
    {
        $payload = $this->payload();
        $worker = TESTPATH . '_support/payment-worker.php';
        $env = array_merge(getenv(), $_ENV);
        $env['paymentGateway.apiKey'] = self::$apiKey;
        $env['MOCK_GATEWAY_INTEGRATION'] = '1';
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, $worker], [
                    0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
                ], $pipes, ROOTPATH, $env, ['bypass_shell' => true]);
                $this->assertIsResource($process);
                fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR) . PHP_EOL);
                $this->assertSame('READY', trim(fgets($pipes[1])));
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fflush($pipes[0]);
            }
            $ids = [];
            foreach ($workers as [$process, $pipes]) {
                $result = json_decode(trim(stream_get_contents($pipes[1])), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('ok', $result['result']);
                $ids[] = $result['gateway_transaction_id'];
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process));
            }
            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, self::$testDb->table('payment_transactions')->countAllResults());
        } finally {
            foreach ($workers as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }

    private function initiate(array $payload)
    {
        return $this->withHeaders(['X-API-Key' => self::$apiKey, 'Accept' => 'application/json'])
            ->withBodyFormat('json')->post('/api/v1/payments', $payload);
    }

    private function initiateRaw(string $body)
    {
        return $this->withHeaders(['X-API-Key' => self::$apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'])
            ->withBody($body)->post('/api/v1/payments');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'transaction_reference' => 'TRX-123',
            'reservation_reference' => 'RES-42',
            'student_id' => 'SYNTHETIC-01',
            'student_name' => 'Synthetic Borrower',
            'rented_items' => [['name' => 'Room A', 'type' => 'facility', 'quantity' => 1]],
            'amount' => '1500.00',
            'currency' => 'PHP',
            'payment_method' => 'gcash',
        ], $overrides);
    }
}
