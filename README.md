# Omnipay IDBank

[![Tests](https://github.com/arm092/omnipay-idbank/actions/workflows/tests.yml/badge.svg)](https://github.com/arm092/omnipay-idbank/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/arm092/omnipay-idbank.svg)](https://packagist.org/packages/arm092/omnipay-idbank)
[![Total Downloads](https://img.shields.io/packagist/dt/arm092/omnipay-idbank.svg)](https://packagist.org/packages/arm092/omnipay-idbank/stats)

IDBank EPG payment gateway driver for [Omnipay](https://omnipay.thephpleague.com/).
Framework independent. Requires PHP 8.2 or newer.

## Installation

### Laravel

```bash
composer require arm092/omnipay-idbank:^1.1
```

Guzzle 7 is a required dependency of this package. Composer installs it
automatically or reuses the compatible version already installed by Laravel.
Omnipay discovers the HTTP client automatically; no explicit adapter dependency
is needed in the installation command.

### Other PHP projects

```bash
composer require arm092/omnipay-idbank:^1.1
```

The same command installs the required Guzzle 7 client. This package depends on
`omnipay/common` and does not include or replace its classes.

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

### API coverage

The driver implements request and response handling for the **SmartVista EPG Web
API used by ArCa/IDBank**, following the **EPG Merchant Integration Guide**
(`EPG Merchant Manual.pdf`). Version 1.1 provides the standard Omnipay payment
methods above plus **41 named EPG operations**. The table maps the supported
capabilities to the guide's sections.

| Guide section | Supported capability | Methods and reference |
| --- | --- | --- |
| 4.1 | One-stage and two-stage order registration | `purchase`, `authorize` |
| 4.2 | Capture of an authorized amount, including partial capture | `capture` |
| 4.3 – 4.5 | Direct card payment, 3DS1/3DS2 challenges and eligibility checks | `paymentOrder`, `check3ds2Eligibility`, `check3ds2EligibilityPreliminary`, `finish3dsPayment`; [card API](docs/cards-and-reports.md) |
| 4.6 | Manual recurring charges, recurring settings, details, reports and templates | 7 operations; [recurring API](docs/bindings-recurring.md#recurring-operations) |
| 4.7 | Payment reversal | `void` |
| 4.8 | Refunds, including partial refunds | `refund` |
| 4.9 – 4.12 | Basic/extended order status, refund status and paginated transaction reports | `getOrderStatus`, `getOrderStatusExtended`, `getRefundStatus`, `getLastOrdersForMerchants`; [reporting API](docs/cards-and-reports.md) |
| 4.13 | Fee calculation, including the public authenticated variant | `calculateFee`, `calculateFeePublic`; [fee API](docs/cards-and-reports.md) |
| 4.14 – 4.15 | Binding creation, saved-card payments, expiry updates, activation, deactivation, lookup and amount checks | 14 operations; [bindings](docs/bindings-recurring.md) |
| 4.16 | Merchant-managed Card-on-File, CIT/MIT, recurring/installment and no-show parameters | Parameter forwarding and opaque signed transaction data; [CoF fields](docs/bindings-recurring.md#merchant-managed-cof-and-recurring-data) |
| 4.17 | P2P registration, transfer, verification, alias resolution, fees and status | 10 operations; [P2P API](docs/p2p.md) |

Automatic recurring schedules are configured through EPG; the driver does not run
a local scheduler. Merchant-managed CoF forwards the bank's signed transaction
data unchanged rather than generating or signing it locally. Availability of each
operation depends on the bank and the permissions enabled for the merchant.

Extended EPG routes require the endpoint configuration below. In particular,
`finish3dsPayment` and `createRecurringTemplateNoPayment` require explicit
bank-confirmed URLs and parameters because the guide does not fully specify
their contracts.

`createCard`, `updateCard` and `deleteCard` are Omnipay aliases for
`createBindingNoPayment`, `extendBinding` and `unBindCard`. Deleting a card
deactivates its binding. `cardReference` maps to `bindingId`.

List all named operations with `$gateway->getSupportedApiOperations()`. Each is
available as `$gateway->operationName($options)` or
`$gateway->api('operationName', $options)`; the latter accepts only documented
operation names, not arbitrary paths. Google Pay's separate API is not included.

### Extended endpoint configuration

The bank's REST endpoint and its EPG root are separate settings. Operations whose
manual examples use `/epg/` require an explicit root confirmed by the bank:

```php
$gateway->setLiveEpgEndpoint('https://bank.example/epg/');
$gateway->setTestEpgEndpoint('https://sandbox.example/epg/');
```

These are example addresses. The driver does not infer an EPG root from the
IDBank REST address. Existing v1.0 operations keep their REST endpoint settings.

For deployment-specific routes, configure full HTTPS URLs by environment:

```php
$gateway->setEndpointOverrides([
    'live' => [
        'finish3dsPayment' => $bankConfirmedFinishUrl,
        'createRecurringTemplateNoPayment' => $bankConfirmedTemplateUrl,
    ],
    'test' => [
        'finish3dsPayment' => $bankConfirmedTestFinishUrl,
    ],
]);
```

These two methods have no default route: the guide names them without defining a
complete endpoint and request/response contract. Supply the bank-confirmed URL
and its required callback/template fields. A test request never uses a live
override. Overrides apply to the 41 extended operations; existing payment
operations use `liveEndpoint` and `testEndpoint`.

### Additional API parameters

Use the `parameters` array for optional registration fields and the extended
API's original wire fields. Preserve their case and API units:

```php
$response = $gateway->purchase([
    'transactionId' => 'ORDER-43',
    'amount' => '1500.00',
    'currency' => 'AMD',
    'returnUrl' => 'https://shop.example/payments/idbank/return',
    'parameters' => ['clientId' => 'customer-42', 'bindingId' => $bindingId],
])->send();

$payment = $gateway->paymentOrderBinding([
    'transactionReference' => $storedGatewayReference,
    'cardReference' => $bindingId,
    'parameters' => ['cvc' => $customerSuppliedCvc],
])->send();
```

Top-level Omnipay `amount` remains a major-unit decimal string. Raw
`parameters.amount` and `parameters.amountInput` are integer minor units. Nested
P2P card and fee fields retain the bank's wire structure. Conflicting normalized
and wire fields are rejected. Configure credentials on the gateway; they cannot
be overridden through the bag. Objects/arrays used as form parameters are JSON
encoded; report merchant/state arrays are comma separated. JSON API requests
preserve nested structures and booleans. Follow each method's documented field
types when a nested JSON string is required.

`getCardReference()`, `getRecurrenceId()` and `getTransactionData()` expose binding,
recurring-series and opaque signed CoF data when present. Keep `transactionData`
unchanged when forwarding it to merchant-managed recurring/CoF requests.

### Direct card and authentication flows

`paymentOrder` accepts an Omnipay `card` or the raw card fields described in the
card API reference. Handling raw cards requires the merchant's applicable PCI
DSS setup and bank permissions; hosted checkout does not require the driver to
receive card data.

For an ACS challenge, use the response's redirect helpers: they produce POST
fields for 3DS1 (`PaReq`, `MD`, `TermUrl`) or 3DS2 (`creq`). Preserve the bank's
opaque authentication messages. An accepted request or challenge does not prove
payment: confirm final status with the existing completion methods using your
stored expected order, amount and currency.

Fee responses distinguish `SUCCESS`, `ERROR` and `UNAVAILABLE`. Boolean amount
checks preserve false results. P2P perform requests can return an accepted
operation without final transfer status; query status before treating the
transfer as complete. For information/reporting methods, `isSuccessful()` means
the API request succeeded; inspect the payload's business result separately.

Two P2P methods require credentials in the URL query according to the guide.
Avoid logging their complete outgoing URLs. Never log card data or authentication
messages, and do not retry money-moving requests automatically.

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
