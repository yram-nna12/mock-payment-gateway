<?php

namespace App\Services;

use Config\PaymentGateway;
use Throwable;

final class CodeIgniterWebhookDeliveryTransport implements WebhookDeliveryTransportInterface
{
    private PaymentGateway $settings;

    public function __construct(?PaymentGateway $settings = null)
    {
        $this->settings = $settings ?? config('PaymentGateway');
    }

    public function post(string $url, string $body, array $headers): int
    {
        WebhookUrlPolicy::assertAllowed($url);

        try {
            $response = single_service('curlrequest', [
                'timeout' => $this->settings->webhookTimeout,
                'connect_timeout' => $this->settings->webhookConnectTimeout,
                'http_errors' => false,
                'allow_redirects' => false,
                'verify' => true,
            ])->request('POST', $url, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (Throwable $exception) {
            throw new WebhookDeliveryTransportException('network_error', $exception);
        }

        return $response->getStatusCode();
    }
}
