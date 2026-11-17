<?php

namespace App\Controllers;

use App\Libraries\PaymentException;
use App\Services\PaymentService;
use Throwable;

class Payments extends BaseController
{
    public function create()
    {
        try {
            $input = json_decode($this->request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error(400, 'invalid_json', 'A JSON object is required.');
        }

        if (!is_array($input)) {
            return $this->error(400, 'invalid_json', 'A JSON object is required.');
        }

        try {
            $created = (new PaymentService())->initiate($input);
            $existing = !($created['created'] ?? false);
            unset($created['created']);
            return $this->response->setStatusCode($existing ? 200 : 201)->setJSON(['data' => $created]);
        } catch (PaymentException $exception) {
            return $this->error($exception->httpStatus, $exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Payment initiation failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->error(503, 'payment_service_unavailable', 'The payment service is temporarily unavailable.');
        }
    }

    public function show(string $reference)
    {
        try {
            return $this->response->setJSON(['data' => (new PaymentService())->status($reference)]);
        } catch (PaymentException $exception) {
            return $this->error($exception->httpStatus, $exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Payment status lookup failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->error(503, 'payment_service_unavailable', 'The payment service is temporarily unavailable.');
        }
    }

    private function error(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }
}
