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
            $view = [
                'payment' => $payment,
                'token' => $token,
                'returnUrl' => config('PaymentGateway')->techReserveReturnUrl,
                'error' => null,
            ];
            if (in_array($payment['payment_method'], ['card', 'bank_transfer'], true) && $payment['payment_status'] === 'pending') {
                if (!empty($payment['demo_otp_verified_at'])) {
                    $view['demoStage'] = 'confirm';
                } elseif ($otp = session()->get('demo_otp_' . $reference)) {
                    $view['demoStage'] = 'verify';
                    $view['demoOtp'] = $otp;
                }
                $view['demoMethod'] = $payment['payment_method'];
                $view['bank'] = session()->get('demo_bank_' . $reference);
            }
            return view('checkout', $view);
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

    public function receipt(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_status'] === 'pending') {
                throw new PaymentException(404, 'receipt_not_ready', 'A receipt is available after this payment is completed.');
            }
            return view('receipt', compact('payment'));
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        }
    }

    public function downloadReceipt(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_status'] === 'pending') {
                throw new PaymentException(404, 'receipt_not_ready', 'A receipt is available after this payment is completed.');
            }
            return $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="payment-receipt-' . rawurlencode($reference) . '.html"')
                ->setBody(view('receipt', compact('payment')));
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        }
    }

    public function completeCard(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'card') {
                throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            }
            $details = SimulatedPaymentInput::card($this->request->getPost() ?? []);
            return $this->startDemoOtp($reference, $token, 'card', $details['masked']);
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Simulated card processing failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated payment could not be saved.');
        }
    }

    public function beginBankTransfer(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'bank_transfer') throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            $details = SimulatedPaymentInput::bankTransfer($this->request->getPost() ?? []);
            session()->set('demo_bank_' . $reference, $details['bank']);
            return $this->startDemoOtp($reference, $token, 'bank_transfer', $details['masked']);
        } catch (PaymentException $exception) { return $this->checkoutError($exception->httpStatus, $exception->getMessage()); }
        catch (Throwable $exception) { log_message('error', 'Simulated bank transfer could not start ({exceptionClass}).', ['exceptionClass' => $exception::class]); return $this->checkoutError(503, 'The simulated verification is temporarily unavailable.'); }
    }

    public function verifyDemoPayment(string $reference, string $token, string $method)
    {
        try {
            $payment = (new PaymentService())->verifyDemoOtp($reference, $token, $method, (string) $this->request->getPost('verification_code'));
            session()->remove('demo_otp_' . $reference);
            return view('checkout', compact('payment', 'token') + ['returnUrl' => config('PaymentGateway')->techReserveReturnUrl, 'error' => null, 'demoStage' => 'confirm', 'demoMethod' => $method, 'bank' => session()->get('demo_bank_' . $reference)]);
        } catch (PaymentException $exception) { return $this->checkoutError($exception->httpStatus, $exception->getMessage()); }
        catch (Throwable $exception) { log_message('error', 'Demo payment verification failed ({exceptionClass}).', ['exceptionClass' => $exception::class]); return $this->checkoutError(503, 'The simulated verification is temporarily unavailable.'); }
    }

    public function confirmDemoPayment(string $reference, string $token, string $method)
    {
        try {
            (new PaymentService())->confirmDemoPayment($reference, $token, $method);
            session()->remove('demo_otp_' . $reference); session()->remove('demo_bank_' . $reference);
            return redirect()->to(site_url('checkout/' . rawurlencode($reference) . '/' . rawurlencode($token)));
        } catch (PaymentException $exception) { return $this->checkoutError($exception->httpStatus, $exception->getMessage()); }
    }

    private function startDemoOtp(string $reference, string $token, string $method, string $masked): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $challenge = (new PaymentService())->startDemoOtp($reference, $token, $method, $masked);
        session()->set('demo_otp_' . $reference, $challenge['otp']);
        return view('checkout', ['payment' => $challenge['payment'], 'token' => $token, 'returnUrl' => config('PaymentGateway')->techReserveReturnUrl, 'error' => null, 'demoStage' => 'verify', 'demoMethod' => $method, 'demoOtp' => $challenge['otp'], 'bank' => session()->get('demo_bank_' . $reference)]);
    }

    public function beginGcash(string $reference, string $token)
    {
        try {
            $payment = (new PaymentService())->checkout($reference, $token);
            if ($payment['payment_method'] !== 'gcash') {
                throw new PaymentException(404, 'checkout_not_found', 'Checkout session not found.');
            }
            $mobile = SimulatedPaymentInput::gcashMobile($this->request->getPost() ?? []);
            $challenge = (new PaymentService())->startGcashOtp($reference, $token, $mobile);
            session()->set('gcash_mobile_' . $reference, $mobile);
            session()->set('gcash_demo_otp_' . $reference, $challenge['otp']);
            return view('checkout', ['payment' => $challenge['payment'], 'token' => $token,
                'returnUrl' => config('PaymentGateway')->techReserveReturnUrl,
                'error' => null,
                'gcashStage' => 'verify', 'demoOtp' => $challenge['otp']]);
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
            $payment = (new PaymentService())->verifyGcashOtp($reference, $token, (string) $this->request->getPost('verification_code'));
            session()->remove('gcash_demo_otp_' . $reference);
            return view('checkout', compact('payment', 'token') + ['returnUrl' => config('PaymentGateway')->techReserveReturnUrl, 'error' => null, 'gcashStage' => 'confirm']);
        } catch (PaymentException $exception) {
            return $this->checkoutError($exception->httpStatus, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Simulated GCash processing failed ({exceptionClass}).', ['exceptionClass' => $exception::class]);
            return $this->checkoutError(503, 'The simulated payment could not be saved.');
        }
    }

    public function resendGcash(string $reference, string $token)
    {
        try {
            $mobile = session()->get('gcash_mobile_' . $reference);
            if (!is_string($mobile)) throw new PaymentException(422, 'mobile_required', 'Enter your mobile number again.');
            $challenge = (new PaymentService())->startGcashOtp($reference, $token, $mobile);
            session()->set('gcash_demo_otp_' . $reference, $challenge['otp']);
            return view('checkout', ['payment' => $challenge['payment'], 'token' => $token, 'returnUrl' => config('PaymentGateway')->techReserveReturnUrl, 'error' => null, 'gcashStage' => 'verify', 'demoOtp' => $challenge['otp']]);
        } catch (PaymentException $exception) { return $this->checkoutError($exception->httpStatus, $exception->getMessage()); }
    }

    public function confirmGcash(string $reference, string $token)
    {
        try {
            (new PaymentService())->confirmGcash($reference, $token);
            session()->remove('gcash_mobile_' . $reference); session()->remove('gcash_demo_otp_' . $reference);
            return redirect()->to(site_url('checkout/' . rawurlencode($reference) . '/' . rawurlencode($token)));
        } catch (PaymentException $exception) { return $this->checkoutError($exception->httpStatus, $exception->getMessage()); }
    }

    private function checkoutError(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setBody(view('checkout_error', ['message' => $message]));
    }
}
