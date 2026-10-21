# Omnipay IDBank

[![Tests](https://github.com/arm092/omnipay-idbank/actions/workflows/tests.yml/badge.svg)](https://github.com/arm092/omnipay-idbank/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/arm092/omnipay-idbank.svg)](https://packagist.org/packages/arm092/omnipay-idbank)

IDBank EPG payment gateway driver for [Omnipay](https://omnipay.thephpleague.com/).
Framework independent. Requires PHP 8.2 or newer.

## Installation

```bash
composer require arm092/omnipay-idbank:^1.0 php-http/guzzle7-adapter:^1.1
```

The Guzzle adapter supplies the HTTP client used by Omnipay. An existing compatible
HTTP client can be injected instead. This package depends on `omnipay/common` and
does not include or replace its classes.

## Configuration

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('IDBank');
$gateway->initialize([
    'userName' => getenv('IDBANK_USERNAME'),
    'password' => getenv('IDBANK_PASSWORD'),
    'currency' => 'AMD',
    'language' => 'en',
    'testMode' => false,
]);
```

The default live API endpoint is `https://ipayproxy.idp.am/payment/rest/`.
Override it with `$gateway->setLiveEndpoint($url)` when instructed by the bank.

For test mode, configure the test REST endpoint supplied for your merchant:

```php
$gateway->setTestEndpoint(getenv('IDBANK_TEST_ENDPOINT'));
$gateway->setTestMode(true);
```

Test mode requires an explicit HTTPS test endpoint and never falls back to live.
Keep API credentials in environment variables or a secret store, outside Git.

## Purchase

```php
$response = $gateway->purchase([
    'transactionId' => 'ORDER-42',
    'amount' => '1500.00',
    'currency' => 'AMD',
    'returnUrl' => 'https://shop.example/payments/idbank/return',
    'description' => 'Order 42',
])->send();

if ($response->isRedirect()) {
    // Persist this reference against your local payment attempt BEFORE redirecting.
    $reference = $response->getTransactionReference();
    // $payment->saveGatewayReference($reference);
    $response->redirect();
} else {
    // Handle $response->getCode() and $response->getMessage().
}
```

`transactionId` is your unique merchant order number (1 to 32 letters, digits,
hyphens or underscores). Use a new identifier for each new payment attempt.
`transactionReference` is the `orderId` returned by EPG. Store both identifiers,
the amount and currency in your application.

Amounts are decimal strings in **major currency units**, never minor units:
`'1500.00'` AMD is sent as `150000` with currency `051`. The driver uses the
currency's ISO 4217 exponent, rejects excess decimal places and does not round.
The API amount must be between 1 and 999999999999 minor units.

Registration creates an order and returns a hosted payment page; it does not
confirm payment. Card details and 3-D Secure are handled on the hosted page.

## Complete a purchase

On return, load the expected payment from your database using your own trusted
application context. Use the gateway reference and payment values you previously
stored, rather than accepting them from the return query parameters.

```php
$response = $gateway->completePurchase([
    'transactionReference' => $storedGatewayReference,
    'transactionId' => $storedOrderNumber,
    'amount' => $storedAmount,
    'currency' => $storedCurrency,
])->send();

if ($response->isSuccessful()) {
    // Atomically mark this local payment paid, once only.
} elseif ($response->isPending()) {
    // Await payment or reconcile the status later.
} elseif ($response->isCancelled()) {
    // The bank reports that the transaction was reversed.
} else {
    // Inspect the status and handle a failed or other non-paid transaction.
}
```

Completion queries `getOrderStatusExtended.do` on the server and checks the
merchant order number, amount and currency against your stored values.
A mismatch throws `Omnipay\Common\Exception\InvalidResponseException`.
Only a deposited transaction (`orderStatus = 2`) confirms a purchase. A hold
(`orderStatus = 1`) is not a captured purchase. An API `errorCode = 0` alone
does not establish that an order was paid.

When the response includes `paymentAmountInfo`, completion also checks that the
captured amount minus refunds covers the expected payment. A partial capture is
not considered a fully paid order. Older response versions without that block
are checked using the order status and registered amount.

Return visits can repeat. Your application must implement idempotent fulfilment,
payment ownership checks and a durable mapping from each local payment attempt
to its gateway reference. A browser return is not a server notification.

## Two-stage payments

Use `authorize()` with the same parameters as `purchase()`, persist the reference
and redirect the customer. On return, call `completeAuthorize()` with the same
stored values used by `completePurchase()`. It succeeds only for a confirmed hold.

Capture all or part of the held amount by supplying an explicit positive amount:

```php
$response = $gateway->capture([
    'transactionReference' => $storedGatewayReference,
    'amount' => '1000.00',
    'currency' => 'AMD',
])->send();
```

Use the original order's currency for captures and refunds. The API interprets
these amounts in the original order currency. Omitting the amount or passing zero
is rejected; the driver never implicitly captures the entire hold.

## Refund and reversal

```php
$refund = $gateway->refund([
    'transactionReference' => $storedGatewayReference,
    'amount' => '500.00',
    'currency' => 'AMD',
])->send();

$reversal = $gateway->void([
    'transactionReference' => $storedGatewayReference,
])->send();
```

Refunds require an explicit positive amount and support partial amounts. A
reversal cancels the payment within the bank's permitted period. Supported
operations, timing and limits depend on the permissions of your merchant account.
Check `isSuccessful()` on the response to each operation.

## Status and error handling

```php
$response = $gateway->fetchTransaction([
    'transactionReference' => $storedGatewayReference,
])->send();

$status = $response->getOrderStatus();
$raw = $response->getData();
```

To reconcile registration when the gateway reference was not received, use
`fetchTransaction(['transactionId' => $storedOrderNumber])`. If both identifiers
are supplied, the gateway reference takes precedence.

| Order status | Meaning |
| --- | --- |
| `0` | Registered, not yet paid |
| `1` | Approved or held; not proof of capture |
| `2` | Deposited |
| `3` | Reversed |
| `4` | Refunded |
| `6`, `-1` | Declined |

Other statuses are available through `getOrderStatus()` and are never treated as
successful payments. `fetchTransaction()->send()->isSuccessful()` indicates a
deposited payment. Read the explicit status when checking for a hold or refund.

API errors return an unsuccessful response with `getCode()` and `getMessage()`.
Invalid configuration or payment values throw `InvalidRequestException`.
Transport failures, HTTP errors and malformed responses throw
`InvalidResponseException`. The driver does not automatically retry requests.

A timeout or broken response can leave a payment operation's outcome unknown.
Reconcile the order before deciding whether to retry. In particular, do not blindly
repeat captures, refunds or reversals. Raw response data can contain customer
information; avoid logging it indiscriminately.

## Supported operations

`purchase`, `completePurchase`, `authorize`, `completeAuthorize`, `capture`,
`refund`, `void` and `fetchTransaction`.

Bindings, recurring payments, direct card processing, P2P and wallet-specific
integrations are outside this driver's current scope.

## Development

```bash
composer install
composer validate --strict
composer lint
composer test
composer audit
```

The test suite uses an injected HTTP client and synthetic EPG responses to check
request encoding, amount conversion, transaction state handling, response
validation and failure handling. CI runs on PHP 8.2, 8.3, 8.4 and 8.5.

## License

MIT. See [LICENSE](LICENSE).
