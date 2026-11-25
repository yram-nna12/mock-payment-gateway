<?php

use App\Libraries\PaymentException;
use App\Services\SimulatedPaymentInput;
use PHPUnit\Framework\TestCase;

final class SimulatedPaymentInputTest extends TestCase
{
    public function testAcceptsMultipleFictionalValuesAndReturnsOnlyMaskedDetails(): void
    {
        foreach (['0000123456789012', '0000999988887777'] as $number) {
            $result = SimulatedPaymentInput::card(['cardholder_name' => 'Fictional Student', 'card_number' => $number,
                'card_expiry_month' => '12', 'card_expiry_year' => '99', 'card_cvv' => '123']);
            $this->assertSame(substr($number, -4), substr($result['masked'], -4));
            $this->assertStringNotContainsString($number, json_encode($result));
            $this->assertArrayNotHasKey('card_cvv', $result);
        }
        foreach ([['Demo National Bank', '00001234'], ['Demo National Bank', '00009876'],
            ['Campus Test Bank', '0000123456'], ['Campus Test Bank', '0000987654']] as [$bank, $number]) {
            $result = SimulatedPaymentInput::bankTransfer(['bank' => $bank, 'account_number' => $number, 'account_holder_name' => 'Fictional Student']);
            $this->assertSame($bank, $result['bank']);
            $this->assertSame(substr($number, -4), substr($result['masked'], -4));
            $this->assertStringNotContainsString($number, json_encode($result));
        }
        $this->assertSame('+639171234567', SimulatedPaymentInput::gcashMobile(['mobile_number' => '09171234567']));
        $this->assertSame('+639181234568', SimulatedPaymentInput::gcashMobile(['mobile_number' => '+639181234568']));
        $this->assertSame('+639191234569', SimulatedPaymentInput::gcashMobile(['mobile_number' => '639191234569']));
    }

    public function testRejectsInvalidFormatsWithoutEchoingValues(): void
    {
        $card = ['cardholder_name' => 'Fictional Student', 'card_number' => '0000123456789012',
            'card_expiry_month' => '12', 'card_expiry_year' => '99', 'card_cvv' => '123'];
        $bank = ['bank' => 'Demo National Bank', 'account_number' => '00001234', 'account_holder_name' => 'Fictional Student'];
        foreach ([
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['card_number' => '4111111111111111'])),
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['card_number' => '0000123'])),
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['card_number' => '000012345678901x'])),
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['card_expiry_year' => '20'])),
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['card_cvv' => '12'])),
            static fn() => SimulatedPaymentInput::card(array_replace($card, ['cardholder_name' => ''])),
            static fn() => SimulatedPaymentInput::bankTransfer(array_replace($bank, ['account_number' => '0000123456'])),
            static fn() => SimulatedPaymentInput::bankTransfer(array_replace($bank, ['account_number' => '12345678'])),
            static fn() => SimulatedPaymentInput::bankTransfer(array_replace($bank, ['bank' => 'Unknown'])),
            static fn() => SimulatedPaymentInput::bankTransfer(array_replace($bank, ['account_holder_name' => ''])),
            static fn() => SimulatedPaymentInput::gcashMobile(['mobile_number' => '08171234567']),
            static fn() => SimulatedPaymentInput::gcashMobile(['mobile_number' => '+63917123456A']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('An invalid input was accepted.');
            } catch (PaymentException $exception) {
                $this->assertSame(422, $exception->httpStatus);
                $this->assertStringNotContainsString('4111111111111111', $exception->getMessage());
            }
        }
    }
}
