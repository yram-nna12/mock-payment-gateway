<?php

namespace App\Libraries;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message
    ) {
        parent::__construct($message);
    }
}
