<?php

namespace App\Services;

use InvalidArgumentException;

final class WebhookUrlPolicy
{
    public static function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower(trim((string) ($parts['host'] ?? ''), '[]')) : '';
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

        if (strlen($url) > 500
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || ($parts['path'] ?? '') !== '/api/v1/webhooks/payments'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535))
            || ($scheme === 'http' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true))) {
            throw new InvalidArgumentException('The configured TechReserve webhook URL is not allowed.');
        }
    }
}
