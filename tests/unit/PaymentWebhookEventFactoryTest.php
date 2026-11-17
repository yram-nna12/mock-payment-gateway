<?php

use App\Services\PaymentWebhookEventFactory;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookEventFactoryTest extends TestCase
{
    public function testCreatesTheVersionedTechReserveContractWithoutStudentFields(): void
    {
        $eventId = PaymentWebhookEventFactory::newEventId();
        $body = PaymentWebhookEventFactory::encode($this->payment(), $eventId, '2026-11-17 14:30:00');
        $event = json_decode($body, true, 16, JSON_THROW_ON_ERROR);

        $this->assertSame($eventId, $event['event_id']);
        $this->assertSame('payment.status.changed', $event['event_type']);
        $this->assertSame(1, $event['event_version']);
        $this->assertSame('expired', $event['status']);
        $this->assertNull($event['paid_at']);
        $this->assertArrayNotHasKey('student_id', $event);
        $this->assertArrayNotHasKey('student_name', $event);
    }

    public function testEncodesCancelledAndRejectsUnsupportedPaymentState(): void
    {
        $event = json_decode(PaymentWebhookEventFactory::encode(
            array_replace($this->payment(), ['payment_status' => 'cancelled']),
            PaymentWebhookEventFactory::newEventId(),
            '2026-11-17 14:30:00'
        ), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('cancelled', $event['status']);
        try {
            PaymentWebhookEventFactory::encode(
                array_replace($this->payment(), ['payment_status' => 'refunded']),
                PaymentWebhookEventFactory::newEventId(),
                '2026-11-17 14:30:00'
            );
            $this->fail('An unsupported payment state was encoded.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    private function payment(): array
    {
        return [
            'payment_status' => 'expired',
            'transaction_reference' => 'TRX-0123456789abcdef0123456789abcdef',
            'reservation_reference' => 'RES-42',
            'gateway_transaction_id' => 'gw_0123456789abcdef0123456789abcdef',
            'amount' => '1500.00',
            'currency' => 'PHP',
            'payment_method' => 'gcash',
            'paid_at' => null,
            'expires_at' => '2026-11-17 14:00:00',
        ];
    }
}
