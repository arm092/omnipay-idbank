# Bindings, recurring payments and Card-on-File

These operations follow EPG Merchant Integration Guide sections 4.6 and 4.14 – 4.16. Pass API fields in `parameters` using the case, format and units required by the bank. All operations in this document use form encoding. Authenticated operations add `userName` and `password`; `createBindingNoPaymentAnonymous`, `isBindingEnabled` and `matchSum` use `merchantLogin` instead.

Paths below are relative to the configured REST endpoint unless prefixed with `EPG:`. An EPG path requires the separately configured EPG root, for example `https://bank.example/epg/`. Do not derive that root from the REST endpoint.

## Binding operations

| Gateway method | Path | Required API parameters | Manual page |
| --- | --- | --- | --- |
| `createBindingNoPayment` | `createBindingNoPayment.do` | `pan`, `expiryDate`, `ClientId` | 167 |
| `createBindingNoPaymentAnonymous` | `createBindingNoPaymentAnonymous.do` | `merchantLogin`, `pan`, `expiryDate`, `ClientId` | 172 |
| `paymentOrderBinding` | `paymentOrderBinding.do` | `mdOrder`, `bindingId` | 159, 177 |
| `registerModifyExpirationOrder` | EPG: `rest/public/registerModifyExpirationOrder.do` | `sessionExpiredDate`, `bindingId`, at least one of `email` / `phone` | 181 |
| `extendBinding` | `extendBinding.do` | `newExpiry`, `bindingId` | 188 |
| `processModifyExpiration` | EPG: `rest/internal/processModifyExpiration.do` | `expiryDate`, `bindingId`, `mdOrder` | 191 |
| `unBindCard` | `unBindCard.do` | `bindingId` | 198 |
| `bindCard` | `bindCard.do` | `bindingId` | 201 |
| `isBindingEnabled` | `isBindingEnabled.do` | `merchantLogin`, `bindingId` | 204 |
| `matchSum` | `matchSum.do` | `merchantLogin`, `bindingId`, `mdOrder`, `amount` | 206 |
| `getBindings` | `getBindings.do` | `clientId` | 208 |
| `getBindingsByCardOrId` | `getBindingsByCardOrId.do` | at least one of `pan` / `bindingId` | 212 |
| `registerBindingOrder` | EPG: `rest/public/registerBindingOrder.do` | `sessionExpiredDate`, `clientId`, `features` | 217 |
| `processBindingOrder` | EPG: `rest/public/processBindingOrder.do` | `pan`, `expiryDate`, `mdOrder` | 226 |

`expiryDate` and `newExpiry` use `YYYYMM`. `sessionExpiredDate` uses `YYYY-MM-DDTHH:MM:ss`. `features` uses `FORCE_SSL` or `FORCE_TDS`, as permitted by the merchant configuration. `pan`, expiry and cardholder fields can be supplied directly in `parameters`.

```php
$response = $gateway->paymentOrderBinding([
    'transactionReference' => $orderId, // Sent as mdOrder.
    'parameters' => [
        'bindingId' => $bindingId,
        'cvc' => '123',
        'clientBrowserInfo' => $browserInfo,
    ],
])->send();

$response = $gateway->getBindings([
    'parameters' => ['clientId' => 'customer-42', 'showExpired' => false],
])->send();

$response = $gateway->extendBinding([
    'parameters' => ['bindingId' => $bindingId, 'newExpiry' => '202912'],
])->send();

$response = $gateway->matchSum([
    'transactionReference' => $orderId,
    'amount' => '10.00', // Normalized Omnipay amount; converted to minor units.
    'currency' => 'AMD',
    'parameters' => ['merchantLogin' => $merchantLogin, 'bindingId' => $bindingId],
])->send();
```

Every listed method uses the same `$gateway->method(['parameters' => [...]])->send()` interface. When supplying raw `parameters.amount`, use the API's minor units directly. `transactionReference` maps to `mdOrder` on binding payment, amount check and processing operations. Binding identifiers and customer identifiers remain separate raw parameters.

Optional fields are forwarded through `parameters`: creation accepts `cardholderName`; payment accepts `cvc`, `language`, `clientBrowserInfo`, `noShowPayment`; expiration registration accepts `orderNumber`, `email`, `phone`, `merchantId`, `language`, `clientBrowserInfo`, `returnUrl`, `features`; expiration extension accepts `language`; expiration processing accepts `cvc`, `language`, `threeDs2ReturnUrl`, `threeDSCompInd`, `checkCardValidity`; activation/deactivation accepts `language`; list operations accept `showExpired`, and `getBindings` additionally accepts `bindingId`. Binding registration accepts `orderNumber`, `name`, `email`, `phone`, `merchantId`, `description`, `language`, `returnUrl` and the CoF fields below. Binding processing accepts `cvc`, `cardholderName`, `language`, `clientBrowserInfo`, `threeDs2ReturnUrl`, `threeDSCompInd`, `checkCardValidity`.

The merchant's bank configuration determines whether CVC is required on binding payments and zero-amount binding operations. Supply `cvc` unless the corresponding permission allows omission. For expiration-registration links, an email-only merchant requires `email`, an SMS-only merchant requires `phone`, and a merchant with both permissions requires either. Local validation checks that at least one contact is present; the caller must satisfy its configured delivery channel.

`isBindingEnabled` returns a payload containing `result`. A valid `result: false` means the binding is inactive; inspect that value separately from request success. `matchSum` returns a raw boolean, where false means the amounts differ. Registration returns `orderId` and `formUrl`. Processing can return 3DS challenge data (`acsUrl`, `cReq`, `handle3ds2MethodInEpg`); completing the authentication flow remains the caller's responsibility.

## Recurring operations

| Gateway method | Path | Required API parameters | Manual page |
| --- | --- | --- | --- |
| `recurringPayment` | `recurringPayment.do` | `recurrenceId` | 62 |
| `updateRecurrent` | `updateRecurrent.do` | `recurrenceId`, `recurrenceType` | 67 |
| `getRecurrentDetails` | `getRecurrentDetails.do` | `recurrenceId` | 70 |
| `getRecurrentReport` | `getRecurrentReport.do` | `recurrenceId` | 77 |
| `registerRecurringTemplateNoPayment` | EPG: `rest/public/registerRecurringTemplateNoPayment.do` | none beyond authentication | 80 |
| `cancelRecurring` | EPG: `rest/public/cancelRecurring.do` | `recurrenceId` | 83 |
| `createRecurringTemplateNoPayment` | requires bank-supplied endpoint override | `mdOrder`, further fields per bank contract | 81, 247 |

Create the initial recurring payment with the regular purchase/authorization registration operation and its `parameters` bag. Send `recurrenceType` (`AUTO` or `MANUAL`), `recurrenceFrequency` for automatic schedules, and optional `recurrenceStartDate` / `recurrenceEndDate` in `YYYYMMDD`. Preserve the bank's returned `recurrenceId`; it identifies the recurring series and is not the order's transaction reference.

```php
$response = $gateway->recurringPayment([
    'parameters' => [
        'recurrenceId' => $recurrenceId,
        'updateAccount' => 'NO',
        'amount' => 1500, // Raw API minor units; optional amount override.
    ],
])->send();

$response = $gateway->updateRecurrent([
    'parameters' => [
        'recurrenceId' => $recurrenceId,
        'recurrenceType' => 'AUTO',
        'recurrenceFrequency' => '0 0 * * * ?',
        'recurrenceStartDate' => '20261101',
        'recurrenceEndDate' => '20271231',
    ],
])->send();

$response = $gateway->getRecurrentReport([
    'parameters' => ['recurrenceId' => $recurrenceId],
])->send();
```

`recurringPayment` is for `MANUAL` series. Its optional `updateAccount` is `YES`, `NO` or `FORCE`; optional `amount` changes only the current instance when the merchant has the relevant fluctuating-amount permission. `updateRecurrent` requires `recurrenceFrequency` for `AUTO`; the bank ignores frequency and start/end dates for `MANUAL`. For automatic schedules the earliest start date is tomorrow. `recurrenceEndDate=Now` requests immediate cancellation. `cancelRecurring` only cancels `AUTO` series. `registerRecurringTemplateNoPayment` accepts optional `orderNumber` and `language`; order-number omission depends on the merchant's automatic-numbering permission.

## Merchant-managed CoF and recurring data

Section 4.16 adds parameters to existing operations, rather than separate payment endpoints:

- `bindingManagedByMerchant`: true requests merchant-managed bindings.
- `bindingUsage`: `saveCard` or `paymentBySavedCard`; use only with `bindingManagedByMerchant=true`.
- `transactionInitiator`: `CIT` or `MIT`.
- `originalTransactionData`: the opaque signed transaction data previously returned by EPG.
- `recurringPaymentManagedByMerchant`: true requests merchant-managed recurring payments.
- `recurringPaymentForm`: `recurring` or `installment`; required when `recurringPaymentManagedByMerchant=true`.
- `recurringExpiry`: `YYYYMMDD`; an omitted value uses the bank's documented default.
- `originalDsTransId`, `originalAcsTransId`, `originalInitTransactionDate`: original 3DS2 transaction information.
- `originalInstallmentNumber`: required by the documented subsequent-installment flow.

For a subsequent MIT recurring/installment payment, register with `recurringPaymentManagedByMerchant=true`, `transactionInitiator=MIT`, `recurringPaymentForm` and the previously returned `originalTransactionData`, then perform the card payment. For a no-show payment, register with `bindingManagedByMerchant=true`, `transactionInitiator=MIT`, `bindingUsage=paymentBySavedCard` and `originalTransactionData`, then perform the payment with `noShowPayment=true`.

Save `transactionData` from the bank's response. It is a signed JWS block: forward it unchanged as `originalTransactionData`. The driver does not create keys, reconstruct its payload, sign it or invent missing bank transaction data. The manual specifies that MIT managed-binding/recurring flows fail validation without valid original data. Apply these fields to the endpoints listed in section 4.16; they are not interchangeable across every operation.

## Manual inconsistencies and limits

- The `createBindingNoPayment` and anonymous-creation request tables use `Clientid`, while request examples use `ClientId`. These catalog entries follow the executable examples (`ClientId`). Confirm this casing with the bank if the deployment rejects it.
- `processBindingOrder` labels `pan` optional in the table and then states it is mandatory. The driver requires `pan`. Its table labels the security field `CVC` while prose and the request example use lowercase `cvc`; use the example spelling.
- `processModifyExpiration` lists credentials as mandatory but omits them from the example. The driver follows the parameter table and sends authentication. It preserves the example's explicit `rest/internal/` route.
- `getBindings` describes lookup via customer or binding, but its parameter table and missing-client error make `clientId` mandatory. Use `getBindingsByCardOrId` when only a binding ID is available.
- `getRecurrentDetails` response tables use `recurrenceType`, `recurrenceFrequency` and date names that differ from the sample's `type`, `frequency`, `startDate` and `endDate`. Inspect the returned payload rather than relying on a rewritten response schema. `getRecurrentReport` also has sample spelling differences (`tranasctionId` versus `transactionId`).
- The manual references `createRecurringTemplateNoPayment.do` on pages 62, 81, 240 and 247 but supplies no dedicated request/response schema or explicit endpoint URL. Its named gateway method requires an explicit `endpointOverrides['live']['createRecurringTemplateNoPayment']` configuration before sending. Pass `transactionReference` (mapped to `mdOrder`) and the bank-confirmed card/recurrence fields through `parameters`. The catalog does not invent its URL or unlisted card requirements. A completed template uses order status 10.

These methods describe the documented API surface. Merchant permissions, actual endpoint availability and bank processing must be verified with the deployment concerned.
