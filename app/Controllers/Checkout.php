<?php

namespace App\Controllers;

use App\Libraries\PaymentException;
use App\Services\PaymentService;
use App\Services\SimulatedPaymentInput;
use Throwable;

class Checkout extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function show(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            return view('checkout', [
                'payment' => $payment,
                'token' => $token,
                'returnUrl' => config('PaymentGateway')->techReserveReturnUrl,
                'error' => null,
            ]);
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Checkout display failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'Checkout is temporarily unavailable.');
        }
    }

    public function settle(string $reference, string $token, string $outcome)
    {
        try {
            (new PaymentService())->settle($reference, $token, $outcome);
            return redirect()->to(site_url('checkout/' . rawurlencode($reference) . '/' . rawurlencode($token)));
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Checkout result update failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated result could not be saved. Check the transaction status before retrying.');
        }
    }

    public function completeCard(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'card') {
                throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            }
            $outcome = SimulatedPaymentInput::card($this->request->getPost() ?? []);
            (new PaymentService())->settle($reference, $token, $outcome);
            return redirect()->to(site_url('checkout/' . rawurlencode($reference) . '/' . rawurlencode($token)));
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Simulated card processing failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated payment could not be saved.');
        }
    }

    public function beginGcash(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'gcash') {
                throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            }
            SimulatedPaymentInput::gcashMobile($this->request->getPost() ?? []);
            return view('checkout', compact('payment', 'token') + [
                'returnUrl' => config('PaymentGateway')->techReserveReturnUrl,
                'error' => null,
                'gcashVerification' => true,
            ]);
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Simulated GCash verification could not start ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated verification is temporarily unavailable.');
        }
    }

    public function completeGcash(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'gcash') {
                throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            }
            $outcome = SimulatedPaymentInput::gcashVerification($this->request->getPost() ?? []);
            (new PaymentService())->settle($reference, $token, $outcome);
            return redirect()->to(site_url('checkout/' . rawurlencode($reference) . '/' . rawurlencode($token)));
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Simulated GCash processing failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated payment could not be saved.');
        }
    }

    private function checkoutError(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setBody(view('checkout_error', ['message' => $message]));
    }
}
