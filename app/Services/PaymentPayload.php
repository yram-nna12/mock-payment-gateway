<?php

namespace App\Services;

use App\Libraries\PaymentException;

class PaymentPayload
{
    // bank_transfer remains accepted so existing gateway transactions stay compatible.
    private const METHODS = ['card', 'gcash', 'bank_transfer'];

    public static function normalize(array $input): array
    {
        $fields = [
            'transaction_reference', 'reservation_reference', 'student_id',
            'student_name', 'rented_items', 'amount', 'currency', 'payment_method',
        ];
        if (array_diff(array_keys($input), $fields) !== [] || array_diff($fields, array_keys($input)) !== []) {
            throw new PaymentException(422, 'invalid_request', 'The payment request has missing or unsupported fields.');
        }

        foreach (['transaction_reference', 'reservation_reference', 'student_id', 'student_name', 'amount', 'currency', 'payment_method'] as $field) {
            if (!is_string($input[$field])) {
                throw new PaymentException(422, 'invalid_request', 'The payment request contains invalid field types.');
            }
        }

        if (!preg_match('/\A[A-Za-z0-9._:-]{1,100}\z/', $input['transaction_reference'])
            || trim($input['reservation_reference']) === '' || mb_strlen($input['reservation_reference']) > 100
            || trim($input['student_id']) === '' || mb_strlen($input['student_id']) > 30
            || trim($input['student_name']) === '' || mb_strlen($input['student_name']) > 150) {
            throw new PaymentException(422, 'invalid_request', 'A payment reference or student field is invalid.');
        }

        if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})\.[0-9]{2}\z/', $input['amount'])
            || ((int) explode('.', $input['amount'])[0] * 100 + (int) substr($input['amount'], -2)) < 1) {
            throw new PaymentException(422, 'invalid_amount', 'Amount must be a positive PHP amount with two decimal places.');
        }
        if ($input['currency'] !== 'PHP') {
            throw new PaymentException(422, 'unsupported_currency', 'Only PHP is supported.');
        }
        if (!in_array($input['payment_method'], self::METHODS, true)) {
            throw new PaymentException(422, 'unsupported_payment_method', 'Choose gcash or bank_transfer.');
        }
        if (!is_array($input['rented_items']) || count($input['rented_items']) < 1 || count($input['rented_items']) > 20) {
            throw new PaymentException(422, 'invalid_items', 'Provide between 1 and 20 rented items.');
        }

        $items = [];
        foreach ($input['rented_items'] as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['name', 'type', 'quantity']) !== []
                || !is_string($item['name'] ?? null) || !is_string($item['type'] ?? null)
                || !is_int($item['quantity'] ?? null) && !(is_string($item['quantity'] ?? null) && ctype_digit($item['quantity']))) {
                throw new PaymentException(422, 'invalid_items', 'Each rented item needs a name, type, and positive quantity.');
            }
            $name = trim($item['name']);
            $type = $item['type'];
            $quantity = (int) $item['quantity'];
            if ($name === '' || mb_strlen($name) > 150 || !in_array($type, ['facility', 'equipment'], true)
                || $quantity < 1 || $quantity > 4294967295) {
                throw new PaymentException(422, 'invalid_items', 'Each rented item needs a name, supported type, and positive quantity.');
            }
            $items[] = ['name' => $name, 'type' => $type, 'quantity' => $quantity];
        }
        usort($items, static fn(array $left, array $right): int => [$left['name'], $left['type'], $left['quantity']]
            <=> [$right['name'], $right['type'], $right['quantity']]);

        return [
            'transaction_reference' => $input['transaction_reference'],
            'reservation_reference' => trim($input['reservation_reference']),
            'student_id' => trim($input['student_id']),
            'student_name' => trim($input['student_name']),
            'rented_items' => $items,
            'amount' => $input['amount'],
            'currency' => $input['currency'],
            'payment_method' => $input['payment_method'],
        ];
    }
}
