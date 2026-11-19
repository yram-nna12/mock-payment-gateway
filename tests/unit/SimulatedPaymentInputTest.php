<?php

use App\Libraries\PaymentException;
use App\Services\SimulatedPaymentInput;
use PHPUnit\Framework\TestCase;

final class SimulatedPaymentInputTest extends TestCase
{
    public function testAcceptsCardInputsAndNormalizesValidPhilippineMobileFormats(): void
    {
        $this->assertSame(['masked' => '•••• 4242'], SimulatedPaymentInput::card([
            'cardholder_name' => 'Test Student',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry_month' => '12', 'card_expiry_year' => '99',
            'card_cvv' => '123',
        ]));
        $this->assertSame(['masked' => '•••• 4444'], SimulatedPaymentInput::card([
            'cardholder_name' => 'Test Student',
            'card_number' => '5555 5555 5555 4444',
            'card_expiry_month' => '12', 'card_expiry_year' => '99',
            'card_cvv' => '999',
        ]));
        $this->assertSame(['bank' => 'Demo National Bank', 'masked' => '•••• 0001'], SimulatedPaymentInput::bankTransfer(['bank' => 'Demo National Bank', 'account_number' => '10000001', 'account_holder_name' => 'Test Student']));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '09171234567']));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '+639171234567']));
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '639171234567']));
    }

    public function testRejectsNonTestSensitiveInputsWithoutRetainingThem(): void
    {
        foreach ([
            static fn() => SimulatedPaymentInput::card(['cardholder_name' => 'Test', 'card_number' => '4111111111111111', 'card_expiry_month' => '12', 'card_expiry_year' => '99', 'card_cvv' => '123']),
            static fn() => SimulatedPaymentInput::card(['cardholder_name' => 'Test', 'card_number' => '4242424242424242', 'card_expiry_month' => '01', 'card_expiry_year' => '20', 'card_cvv' => '123']),
            static fn() => SimulatedPaymentInput::card(['cardholder_name' => 'Test', 'card_number' => '4242424242424242', 'card_expiry_month' => '12', 'card_expiry_year' => '99', 'card_cvv' => '12']),
            static fn() => SimulatedPaymentInput::bankTransfer(['bank' => 'Demo National Bank', 'account_number' => '99999999', 'account_holder_name' => 'Test']),
            static fn() => SimulatedPaymentInput::bankTransfer(['bank' => 'Demo National Bank', 'account_number' => '10000001', 'account_holder_name' => '']),
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
