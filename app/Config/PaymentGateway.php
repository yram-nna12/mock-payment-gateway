<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class PaymentGateway extends BaseConfig
{
    public string $apiKey = '';
    public string $techReserveReturnUrl = 'http://127.0.0.1:8083/borrower/payments/return';
    public string $techReserveWebhookUrl = 'http://127.0.0.1:8083/api/v1/webhooks/payments';
    public string $webhookSecret = '';
    public int $expirationMinutes = 30;
    public float $webhookTimeout = 5.0;
    public float $webhookConnectTimeout = 2.0;
    public int $webhookLeaseSeconds = 30;
    public int $webhookRetryBaseDelaySeconds = 5;
    public int $webhookRetryMaxDelaySeconds = 900;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = (string) env('paymentGateway.apiKey', '');
        $this->webhookSecret = (string) env('paymentGateway.webhookSecret', '');
        $this->techReserveReturnUrl = (string) env(
            'paymentGateway.techReserveReturnUrl',
            $this->techReserveReturnUrl
        );
        $this->techReserveWebhookUrl = (string) env(
            'paymentGateway.techReserveWebhookUrl',
            $this->techReserveWebhookUrl
        );
    }
}
