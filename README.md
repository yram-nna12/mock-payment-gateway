# Mock Payment Gateway

This local CodeIgniter 4 application provides a simulated payment API and
hosted checkout for TechReserve. It supports **Mock GCash** and **Mock Bank
Transfer** only. It never connects to a real wallet or bank and never moves
real money.

## Local configuration

Copy `env` to `.env` if needed. Keep the API key and database credentials only
in the untracked local `.env`; do not commit them. Configure
`paymentGateway.apiKey` to a long random mock secret and configure the local
application URL for port 8082. The TechReserve return link is a fixed
configuration value; the API does not accept a browser-supplied return URL.
Set `paymentGateway.techReserveWebhookUrl` to the fixed TechReserve webhook
endpoint and configure the same private, random `paymentGateway.webhookSecret`
in both applications. Do not place the signing secret in source control or
send it to a browser.

Normal application database settings remain separate from the PHPUnit
connection. See [ISOLATED-TESTING.md](../ISOLATED-TESTING.md) to create and
verify `mock_payment_gateway_test_db` and configure restricted test-only
credentials. Never point tests at `payment_gateway_db`.

## API contract

All API paths are under `/api/v1`. Requests and responses use JSON. Every API
request requires `X-API-Key` with the local server-to-server key.

### Initiate or replay a payment

`POST /api/v1/payments`

Request:

```json
{
  "transaction_reference": "TRX-unique-reference",
  "reservation_reference": "RES-42",
  "student_id": "SYNTHETIC-STUDENT",
  "student_name": "Synthetic Borrower",
  "rented_items": [
    {"name": "Seminar Room", "type": "facility", "quantity": 1},
    {"name": "Projector", "type": "equipment", "quantity": 2}
  ],
  "amount": "1500.00",
  "currency": "PHP",
  "payment_method": "gcash"
}
```

`payment_method` is `gcash` or `bank_transfer`. Amount must be a positive
decimal string with exactly two fractional digits and fit `DECIMAL(10,2)`.
`rented_items` contains 1–20 item objects; `type` is `facility` or `equipment`.
Account number is not required, stored, or transmitted. Unknown request fields
are rejected.

New payment: HTTP `201 Created`.
Identical retry with the same transaction reference: HTTP `200 OK`, returning
the original gateway transaction and expiry.
Conflicting reuse of a reference: HTTP `409 Conflict`.

```json
{
  "data": {
    "transaction_reference": "TRX-unique-reference",
    "gateway_transaction_id": "gw-...",
    "reservation_reference": "RES-42",
    "amount": "1500.00",
    "currency": "PHP",
    "payment_method": "gcash",
    "status": "pending",
    "created_at": "2026-11-17 06:00:00",
    "expires_at": "2026-11-17 06:30:00",
    "checkout_url": "http://127.0.0.1:8082/checkout/TRX-unique-reference/<opaque-token>"
  }
}
```

The checkout token is derived from the server secret and transaction reference;
only the hosted-checkout URL contains it. The API key is never returned.

### Retrieve authoritative status

`GET /api/v1/payments/{transaction_reference}`

Requires the same `X-API-Key` header. HTTP `200 OK` returns:

```json
{
  "data": {
    "transaction_reference": "TRX-unique-reference",
    "gateway_transaction_id": "gw-...",
    "amount": "1500.00",
    "currency": "PHP",
    "payment_method": "gcash",
    "status": "pending",
    "created_at": "2026-11-17 06:00:00",
    "paid_at": null,
    "expires_at": "2026-11-17 06:30:00",
    "updated_at": "2026-11-17 06:00:00"
  }
}
```

Unknown references return `404`. Invalid/missing credentials return `401`;
an unset server API key returns `503`. Invalid JSON returns `400`. Invalid
fields, amount, currency, or method return `422`. Temporary database failures
return a generic `503`. Errors use
`{"error":{"code":"stable_code","message":"Safe description"}}`.

## Hosted checkout and state

The hosted page displays the reservation reference, amount in PHP, payment
method, transaction reference, and a clear simulation notice. It offers only
the method-appropriate success and failure simulation buttons. It never asks
for a real wallet PIN, OTP, password, bank credential, or account number.
State-changing checkout forms use CSRF protection. The checkout return link is
fixed configuration and there is no user-controlled redirect target. The
gateway adds the transaction reference to that configured return URL so
TechReserve can authenticate the borrower and perform a server-side status
lookup on return. Configure it as
`http://127.0.0.1:8083/borrower/payments/return`.

Initial state is `pending`. Simulated success changes it to `paid`; simulated
decline/failure changes it to `failed`. A pending transaction expires after
30 minutes. Status lookup and checkout access enforce expiry in the database.
Replayed initiation does not extend the expiry. `paid`, `failed`, and `expired`
are terminal and cannot be overwritten. Cancellation and refunds are not
implemented.

## Payment webhook delivery

When a payment transitions to `paid`, `failed`, or `expired`, the gateway
stores a version-1 `payment.status.changed` event in `webhook_deliveries` in
the same database transaction. Its fixed callback URL cannot be supplied in a
payment request. Each event has a stable UUID and an immutable JSON body.

Each delivery attempt signs the exact raw body using
`timestamp + "\n" + event-id + "\n" + body` and the shared webhook secret.
Headers are `X-Payment-Event-Id`, `X-Payment-Timestamp`, and
`X-Payment-Signature: v1=<HMAC-SHA256 hex>`. The timestamp is refreshed for
each attempt; the event ID and body remain unchanged. Redirects are disabled.

Run `php spark payments:deliver-webhooks [limit]` periodically. Network errors,
HTTP 408, 429, and 5xx responses are retried with bounded exponential backoff;
other non-2xx responses are recorded as permanent failures. Delivery attempts,
response codes, sanitized error codes, and leases are stored in the outbox.
The additive `AddWebhookDeliveryLeaseFields` migration has been created but
not executed. Delivery cannot run until both the gateway and TechReserve
receipt migrations are applied and the shared secret is configured.

## Tests

Pure request-validation tests:

```powershell
php vendor\bin\phpunit tests\unit\PaymentPayloadTest.php --no-coverage
```

Integration tests are opt-in and fail closed unless the dedicated MySQL test
schema and restricted account pass the isolation checks:

```powershell
$env:MOCK_PAYMENT_GATEWAY_INTEGRATION = '1'
php vendor\bin\phpunit tests\integration\PaymentGatewayTest.php --no-coverage --do-not-cache-result --no-logging --testdox
Remove-Item Env:\MOCK_PAYMENT_GATEWAY_INTEGRATION
```

They create only their prefixed transaction table in the isolated gateway
schema and drop that table after the suite. If MySQL is unavailable, the schema
is missing/nonempty, the test account has access beyond the test schema, or
development and test credentials are not distinct, setup stops before DDL.
See [ISOLATED-TESTING.md](../ISOLATED-TESTING.md) for database provisioning
and TechReserve Phase 2–4 test commands.
