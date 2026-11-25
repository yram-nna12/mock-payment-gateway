<?php

use CodeIgniter\Test\CIUnitTestCase;

final class CheckoutPresentationTest extends CIUnitTestCase
{
    public function testPaymentStagesKeepDisclosureAndConfirmationSeparate(): void
    {
        foreach (['gcash', 'card', 'bank_transfer'] as $method) {
            $payment = [
                'payment_method' => $method, 'payment_status' => 'pending',
                'transaction_reference' => 'TRX-synthetic', 'reservation_reference' => 'RES-999',
                'currency' => 'PHP', 'amount' => '100.00',
                'payment_identifier_masked' => '**** 0001', 'gcash_mobile_masked' => '**** 0001',
            ];
            $common = ['payment' => $payment, 'token' => 'synthetic', 'returnUrl' => 'http://127.0.0.1:8083/return'];
            $stageKey = $method === 'gcash' ? 'gcashStage' : 'demoStage';
            $details = view('checkout', $common, ['saveData' => false]);
            $verify = view('checkout', $common + [$stageKey => 'verify', 'demoOtp' => '123456'], ['saveData' => false]);
            $review = view('checkout', $common + [$stageKey => 'confirm'], ['saveData' => false]);
            $result = view('checkout', array_replace($common, ['payment' => array_replace($payment, [
                'payment_status' => 'paid', 'paid_at' => '2026-11-26 01:00:00',
            ])]), ['saveData' => false]);

            $this->assertStringContainsString('no real funds are transferred', $details);
            $this->assertStringContainsString('Use fictional details only', $details);
            $this->assertStringContainsString('verification_code', $verify);
            $this->assertStringNotContainsString('/confirm"', $verify);
            $this->assertStringContainsString('/confirm"', $review);
            $this->assertStringContainsString('Confirm payment', $review);
            $this->assertStringContainsString('Payment Successful', $result);
            $this->assertStringContainsString('View Receipt', $result);
            $this->assertStringContainsString('Return to TechReserve', $result);
            $this->assertStringNotContainsString('/confirm"', $result);
            if ($method === 'bank_transfer') {
                $this->assertStringContainsString('Transfer details', $details);
                $this->assertStringContainsString('Reservation reference', $review);
                $this->assertStringContainsString('Transaction reference', $review);
            }
        }
    }
}
