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
            || !is_string($number) || !in_array($number, self::supportedCardNumbers(), true)
            || !self::luhnValid($number) || !self::futureTestExpiry($month, $year)
            || preg_match('/\A\d{3,4}\z/', $cvv) !== 1) {
            throw new PaymentException(422, 'invalid_test_card', 'Enter the published TEST MODE card details.');
        }

        return ['masked' => '•••• ' . substr($number, -4)];
    }

    public static function bankTransfer(array $input): array
    {
        $bank = (string) ($input['bank'] ?? '');
        $account = preg_replace('/[ -]/', '', (string) ($input['account_number'] ?? ''));
        $holder = trim((string) ($input['account_holder_name'] ?? ''));
        $accounts = self::supportedBankAccounts();
        if (!isset($accounts[$bank]) || !is_string($account) || !in_array($account, $accounts[$bank], true)
            || $holder === '' || mb_strlen($holder) > 100) {
            throw new PaymentException(422, 'invalid_demo_bank_transfer', 'Enter published TEST MODE bank-transfer details.');
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

    public static function supportedBankAccounts(): array
    {
        return ['Demo National Bank' => ['10000001', '10000002'], 'Campus Test Bank' => ['20000001', '20000002']];
    }

    public static function supportedCardNumbers(): array
    {
        return ['4242424242424242', '5555555555554444'];
    }

    private static function futureTestExpiry(string $month, string $year): bool
    {
        if (preg_match('/\A(0[1-9]|1[0-2])\z/', $month) !== 1 || preg_match('/\A\d{2}\z/', $year) !== 1) {
            return false;
        }
        return [2000 + (int) $year, (int) $month] >= [(int) gmdate('Y'), (int) gmdate('n')];
    }

    private static function luhnValid(string $number): bool
    {
        $sum = 0;
        foreach (array_reverse(str_split($number)) as $index => $digit) {
            $value = (int) $digit;
            if ($index % 2 === 1) $value = $value > 4 ? $value * 2 - 9 : $value * 2;
            $sum += $value;
        }
        return $sum % 10 === 0;
    }
}
