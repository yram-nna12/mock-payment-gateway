<?php

namespace App\Services;

use App\Libraries\PaymentException;

/**
 * Validates TEST MODE checkout inputs without retaining them.
 */
final class SimulatedPaymentInput
{
    public static function card(array $input): array
    {
        $name = trim((string) ($input['cardholder_name'] ?? ''));
        $number = preg_replace('/[ -]/', '', (string) ($input['card_number'] ?? ''));
        $month = (string) ($input['card_expiry_month'] ?? '');
        $year = (string) ($input['card_expiry_year'] ?? '');
        $cvv = (string) ($input['card_cvv'] ?? '');

        if ($name === '' || mb_strlen($name) > 100
            || !is_string($number) || !preg_match('/\A0000[0-9]{12}\z/', $number)
            || !self::futureTestExpiry($month, $year)
            || preg_match('/\A\d{3,4}\z/', $cvv) !== 1) {
            throw new PaymentException(422, 'invalid_test_card', 'Use a 16-digit fictional card number starting with 0000, a future expiry, a fictional name, and a 3- or 4-digit security code.');
        }

        return ['masked' => '•••• ' . substr($number, -4)];
    }

    public static function bankTransfer(array $input): array
    {
        $bank = (string) ($input['bank'] ?? '');
        $account = preg_replace('/[ -]/', '', (string) ($input['account_number'] ?? ''));
        $holder = trim((string) ($input['account_holder_name'] ?? ''));
        $formats = ['Demo National Bank' => '/\A0000[0-9]{4}\z/', 'Campus Test Bank' => '/\A0000[0-9]{6}\z/'];
        if (!isset($formats[$bank]) || !is_string($account) || !preg_match($formats[$bank], $account)
            || $holder === '' || mb_strlen($holder) > 100) {
            throw new PaymentException(422, 'invalid_demo_bank_transfer', 'Enter a fictional account starting with 0000: 8 digits for National Bank or 10 for Campus Bank, and provide an account holder name.');
        }
        return ['bank' => $bank, 'masked' => '•••• ' . substr($account, -4)];
    }

    public static function gcashMobile(array $input): string
    {
        $number = preg_replace('/[\s-]/', '', (string) ($input['mobile_number'] ?? ''));
        if (preg_match('/\A09\d{9}\z/', $number)) {
            return '+63' . substr($number, 1);
        }
        if (preg_match('/\A\+?639\d{9}\z/', $number)) {
            return '+' . ltrim($number, '+');
        }
        throw new PaymentException(422, 'invalid_mobile', 'Enter a valid Philippine mobile number.');
    }

    private static function futureTestExpiry(string $month, string $year): bool
    {
        if (preg_match('/\A(0[1-9]|1[0-2])\z/', $month) !== 1 || preg_match('/\A\d{2}\z/', $year) !== 1) {
            return false;
        }
        return [2000 + (int) $year, (int) $month] >= [(int) gmdate('Y'), (int) gmdate('n')];
    }

}
