<?php

use App\Libraries\PaymentException;
use App\Services\SimulatedPaymentInput;
use PHPUnit\Framework\TestCase;

final class SimulatedPaymentInputTest extends TestCase
{
    public function testAcceptsCardInputsAndNormalizesValidPhilippineMobileFormats(): void
    {
        $this->assertSame('paid', SimulatedPaymentInput::card([
            'cardholder_name' => 'Test Student',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/99',
            'card_cvv' => '123',
            'simulated_outcome' => 'paid',
        ]));
        $this->assertSame('failed', SimulatedPaymentInput::card([
            'cardholder_name' => 'Test Student',
            'card_number' => '4000000000000002',
            'card_expiry' => '12/99',
            'card_cvv' => '999',
            'simulated_outcome' => 'paid',
        ]));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '09171234567']));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '+639171234567']));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '639171234567']));
    }

    public function testRejectsNonTestSensitiveInputsWithoutRetainingThem(): void
    {
        foreach ([
            static fn() => SimulatedPaymentInput::card(['cardholder_name' => 'Test', 'card_number' => '4111111111111111', 'card_expiry' => '12/99', 'card_cvv' => '123']),
            static fn() => SimulatedPaymentInput::card(['cardholder_name' => 'Test', 'card_number' => '4242424242424242', 'card_expiry' => '01/20', 'card_cvv' => '123']),
            static fn() => SimulatedPaymentInput::gcashMobile(['mobile_number' => '08171234567']),
            static fn() => SimulatedPaymentInput::gcashMobile(['mobile_number' => '+63917123456A']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('A non-test sensitive input was accepted.');
            } catch (PaymentException $exception) {
                $this->assertSame(422, $exception->httpStatus);
                $this->assertStringNotContainsString('4111111111111111', $exception->getMessage());
            }
        }
    }
}
