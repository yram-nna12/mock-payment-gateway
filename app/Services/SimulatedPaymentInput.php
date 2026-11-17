<?php

namespace App\Services;

use App\Libraries\PaymentException;

/**
 * Validates TEST MODE checkout inputs without retaining them.
 */
final class SimulatedPaymentInput
{
    public static function card(array $input): string
    {
        $name = trim((string) ($input['cardholder_name'] ?? ''));
        $number = preg_replace('/[ -]/', '', (string) ($input['card_number'] ?? ''));
        $expiry = (string) ($input['card_expiry'] ?? '');
        $cvv = (string) ($input['card_cvv'] ?? '');

        if ($name === '' || mb_strlen($name) > 100
            || !is_string($number) || !in_array($number, ['4242424242424242', '4000000000000002'], true)
            || !self::futureTestExpiry($expiry) || !in_array($cvv, ['123', '999'], true)) {
            throw new PaymentException(422, 'invalid_test_card', 'Enter the published TEST MODE card details.');
        }

        return $number === '4000000000000002' ? 'failed' : self::outcome($input);
    }

    public static function gcashMobile(array $input): void
    {
        if (!preg_match('/\A0999000000[1-2]\z/', (string) ($input['mobile_number'] ?? ''))) {
            throw new PaymentException(422, 'invalid_test_mobile', 'Enter a published TEST MODE mobile number.');
        }
    }

    public static function gcashVerification(array $input): string
    {
        if ((string) ($input['verification_code'] ?? '') !== '000000') {
            throw new PaymentException(422, 'invalid_test_verification', 'Enter the published TEST MODE verification code.');
        }

        return self::outcome($input);
    }

    private static function outcome(array $input): string
    {
        $outcome = $input['simulated_outcome'] ?? 'paid';
        if (!is_string($outcome) || !in_array($outcome, ['paid', 'failed'], true)) {
            throw new PaymentException(422, 'invalid_simulation_outcome', 'Choose a supported simulated result.');
        }

        return $outcome;
    }

    private static function futureTestExpiry(string $expiry): bool
    {
        if (preg_match('/\A(0[1-9]|1[0-2])\/([0-9]{2})\z/', $expiry, $matches) !== 1) {
            return false;
        }

        $year = 2000 + (int) $matches[2];
        $month = (int) $matches[1];
        return [$year, $month] >= [(int) gmdate('Y'), (int) gmdate('n')];
    }
}
