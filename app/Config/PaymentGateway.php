<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class PaymentGateway extends BaseConfig
{
    public string $apiKey = '';
    public string $techReserveReturnUrl = 'http://127.0.0.1:8083/borrower/payments/return';
    public int $expirationMinutes = 30;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = (string) env('paymentGateway.apiKey', '');
        $this->techReserveReturnUrl = (string) env(
            'paymentGateway.techReserveReturnUrl',
            $this->techReserveReturnUrl
        );
    }
}
