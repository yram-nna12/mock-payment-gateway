<?php

use App\Libraries\PaymentException;
use App\Services\PaymentEligibilityClient;
use PHPUnit\Framework\TestCase;

final class PaymentEligibilityClientTest extends TestCase
{
    public function testSignedResponseBindsReferenceAmountNonceAndDeadline(): void
    {
        $now = 1800000000; $nonce = str_repeat('a', 64); $secret = 'synthetic-secret';
        $record = ['transaction_reference'=>'TRX-synthetic', 'gateway_transaction_id'=>'gw_synthetic', 'reservation_reference'=>'RES-1', 'amount'=>'100.00', 'currency'=>'PHP'];
        $data = $record + ['eligible'=>true, 'reason'=>'eligible', 'issued_at'=>$now, 'deadline_utc'=>gmdate('Y-m-d H:i:s', $now + 1)];
        $body = json_encode(['data'=>$data], JSON_THROW_ON_ERROR);
        $signature = 'v1=' . hash_hmac('sha256', "eligibility-response-v1\n".$nonce."\n".$body, $secret);
        $this->assertSame($data['deadline_utc'], PaymentEligibilityClient::validateResponse($body, $signature, $nonce, $secret, $record, $now));
        foreach ([[$body.' ', $signature, $nonce, $record, $now], [$body, $signature, str_repeat('b', 64), $record, $now],
            [$body, $signature, $nonce, array_replace($record, ['amount'=>'101.00']), $now],
            [$body, $signature, $nonce, array_replace($record, ['transaction_reference'=>'TRX-other']), $now],
            [$body, $signature, $nonce, $record, $now + 31]] as [$b,$s,$n,$r,$t]) {
            try { PaymentEligibilityClient::validateResponse($b,$s,$n,$secret,$r,$t); $this->fail('Invalid response accepted.'); }
            catch (RuntimeException $e) { $this->assertTrue(true); }
        }
        try { PaymentEligibilityClient::validateResponse($body,$signature,$nonce,$secret,$record,$now + 1); $this->fail('Deadline accepted.'); }
        catch (PaymentException $e) { $this->assertSame('deadline_passed', $e->errorCode); }
        foreach (['deadline_passed', 'ineligible'] as $reason) {
            $denied = json_encode(['data'=>array_replace($data, ['eligible'=>false, 'reason'=>$reason])], JSON_THROW_ON_ERROR);
            $signed = 'v1=' . hash_hmac('sha256', "eligibility-response-v1\n".$nonce."\n".$denied, $secret);
            try { PaymentEligibilityClient::validateResponse($denied,$signed,$nonce,$secret,$record,$now); $this->fail('Denied transaction accepted.'); }
            catch (PaymentException $e) { $this->assertSame($reason === 'deadline_passed' ? 'deadline_passed' : 'reservation_ineligible', $e->errorCode); }
        }
    }
}
