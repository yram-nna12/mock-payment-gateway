<?php

foreach (['hostname', 'username', 'password', 'database', 'DBDriver', 'DBPrefix', 'port'] as $field) {
    $key = 'database.tests.' . $field;
    $value = getenv($key);
    if ($value !== false) {
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

if (getenv('MOCK_GATEWAY_INTEGRATION') !== '1' || config('Database')->tests['database'] !== 'mock_payment_gateway_test_db') {
    exit(1);
}
$dbConfig = config('Database')->tests;
$dbConfig['DBPrefix'] = 'mpg_test_';
$db = \Config\Database::connect($dbConfig, false);
$job = json_decode(trim(fgets(STDIN)), true, 512, JSON_THROW_ON_ERROR);
echo "READY\n";
fflush(STDOUT);
if (trim(fgets(STDIN)) !== 'GO') {
    exit(1);
}

try {
    $result = (new \App\Services\PaymentService($db))->initiate($job);
    echo json_encode(['result' => 'ok', 'gateway_transaction_id' => $result['gateway_transaction_id']], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'class' => $exception::class], JSON_THROW_ON_ERROR) . PHP_EOL;
}
