<?php

use App\Libraries\PaymentException;
use App\Services\PaymentPayload;
use PHPUnit\Framework\TestCase;

final class PaymentPayloadTest extends TestCase
{
    public function testNormalizesSupportedSimulationMethodsAndItems(): void
    {
        $payload = PaymentPayload::normalize($this->payload([
            'payment_method' => 'bank_transfer',
            'rented_items' => [
                ['name' => 'Room B', 'type' => 'facility', 'quantity' => 1],
                ['name' => 'Projector', 'type' => 'equipment', 'quantity' => '2'],
            ],
        ]));

        $this->assertSame('bank_transfer', $payload['payment_method']);
        $this->assertSame([
            ['name' => 'Projector', 'type' => 'equipment', 'quantity' => 2],
            ['name' => 'Room B', 'type' => 'facility', 'quantity' => 1],
        ], $payload['rented_items']);
        $this->assertSame('1500.00', $payload['amount']);
        $this->assertSame('PHP', $payload['currency']);
    }

    public function testRejectsInvalidOrRealPaymentInputs(): void
    {
        foreach ([
            ['amount' => '0.00'],
            ['amount' => '1.001'],
            ['amount' => '100000000.00'],
            ['currency' => 'USD'],
            ['payment_method' => 'real_gcash'],
            ['account_number' => 'must-not-be-accepted'],
            ['rented_items' => []],
            ['rented_items' => [['name' => 'Room', 'type' => 'facility', 'quantity' => 0]]],
        ] as $overrides) {
            try {
                PaymentPayload::normalize($this->payload($overrides));
                $this->fail('Invalid payment input was accepted.');
            } catch (PaymentException $exception) {
                $this->assertSame(422, $exception->httpStatus);
            }
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'transaction_reference' => 'TRX-123',
            'reservation_reference' => 'RES-42',
            'student_id' => 'SYNTHETIC-01',
            'student_name' => 'Synthetic Borrower',
            'rented_items' => [['name' => 'Room A', 'type' => 'facility', 'quantity' => 1]],
            'amount' => '1500.00',
            'currency' => 'PHP',
            'payment_method' => 'gcash',
        ], $overrides);
    }
}
