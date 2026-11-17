<?php

namespace App\Services;

use RuntimeException;

final class WebhookDeliveryTransportException extends RuntimeException
{
    public function __construct(string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($errorCode, 0, $previous);
    }
}
