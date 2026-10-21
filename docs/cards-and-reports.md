# Card payments, status, reports, and fees

These operations follow the EPG Merchant Integration Guide, sections 4.3 – 4.5 and 4.9 – 4.13. Page numbers below refer to PDF pages. Each method returns the gateway request object; call `send()` to execute it. Pass bank-specific fields in the `parameters` array, preserving their spelling and case. Values in that array use the bank's units: monetary values are minor units, dates and comma-separated lists retain their documented wire format.

`transactionReference` maps to the order identifier specified for each operation below. `transactionId` maps to `orderNumber`. These aliases do not change an explicitly supplied wire field. EPG API paths outside `rest/` require the corresponding EPG base endpoint, for example `https://bank.example/epg/`; a REST base endpoint is a separate configuration value. A bank-specific endpoint override can select another confirmed route.

## Direct card payment

`paymentOrder()` sends a POST form to `paymentorder.do` under the REST base (pages 39 – 56).

| Required wire fields | Conditional fields | Optional fields |
| --- | --- | --- |
| `MDORDER`, `$PAN`, `YYYY`, `MM`, `language` | `$CVC`, `threeDS2Params` | `TEXT`, `ip`, `email`, `bindingNotNeeded`, `jsonParams`, `clientBrowserInfo`, `threeDSCompInd`, `clientId`, `bindingId`, `dccState`, `dccUuid`, `threeDs2ReturnUrl`, `aReqFieldsOverride`, `originalDsTransId`, `originalAcsTransId`, `originalInitTransactionDate`, `originalInstallmentNumber` |

Credentials are sent as `userName` and `password`. `transactionReference` maps to uppercase `MDORDER`. A supplied Omnipay card maps its number to `$PAN`, CVV to `$CVC`, four-digit expiry year to `YYYY`, two-digit expiry month to `MM`, and cardholder name to `TEXT`.

`$CVC` is mandatory unless the merchant's applicable **Can pay by binding without CVV2/CVC2** or **Can pay by card without CVV2/CVC2** option permits omission. `threeDS2Params` is mandatory for application-based authentication and optional for browser-based authentication. The `jsonParams`, `threeDS2Params`, `clientBrowserInfo`, and `aReqFieldsOverride` fields accept JSON structures and are serialized into the form field. The manual's `[]` notation describes structured values; it is not part of these wire field names.

`clientId` is required when creating bindings. Use of `bindingId` requires the merchant's binding permission. The original transaction fields and signed transaction data are subject to the recurring and merchant-managed binding rules in the guide. Do not replace the signed data with unsigned fields. If AVS is enabled, the guide also requires the applicable `postalCode` and `streetAddress` values in `jsonParams` (pages 334 – 335).

A zero `errorCode` means that no system error occurred. It does not establish that funds were deposited. The response can contain `acsUrl` with `paReq` or `cReq` for a customer authentication challenge, `termUrl` for the return destination, or a final redirect. The customer must submit the ACS challenge using POST. Check the order's bank status after authentication; redirect URLs alone do not prove payment.

## 3DS2 eligibility

| Method | EPG path | Required wire fields | Optional fields | Response |
| --- | --- | --- | --- | --- |
| `check3ds2Eligibility()` | `api/v1/3ds2/check` | `mdOrder` | None documented | `completed`; eligibility and method fields when completed |
| `check3ds2EligibilityPreliminary()` | `api/v1/3ds2/checkPreliminary` | `mdOrder`, `pan` | `threeDSMethodNotificationURL` | `threeDSProtocolVersion`; method fields for 3DS2 |

Both methods use `transactionReference` as lowercase-leading `mdOrder`. Their PAN field differs from `paymentOrder()`: the preliminary check uses `pan`, without a dollar sign. The check method is called in parallel with `paymentorder.do`. `completed=false` is a valid pending check result: repeat the check after an interval. It is unrelated to payment completion.

The preliminary method runs before `paymentorder.do` or the P2P perform request. Its protocol values are `3DSv1`, `3DSv2`, and `None`. The response can include `threeDSServerTransID`, `threeDSMethodURL`, `threeDSMethodDataPacked`, and `threeDSMethodURLServer`. ACS method fields can be absent when unsupported. The caller handles the browser method notification and supplies the resulting `threeDSCompInd` to the payment request.

The manual requires credentials for the parallel check, and marks them conditional for the preliminary check without defining the condition (pages 56 – 61). The supplied preliminary descriptor uses authenticated requests.

## Finish 3DS

`finish3dsPayment()` has no default route. Configure `endpointOverrides['live']['finish3dsPayment']` with the complete HTTPS URL confirmed for the merchant's return-to-EPG flow. Forward only the ACS callback fields required by that bank contract in `parameters`, preserving case. If that contract uses `MD` and `PaRes`, preserve those names; the supplied guide does not define them as a Finish 3DS request schema.

Page 56 names `finish3dsPayment.do` for a merchant receiving PARes and forwarding it to EPG. The example on page 55 instead returns `finish3ds.do` as `termUrl`. The guide provides no complete request or response schema for either endpoint. The descriptor therefore requires a confirmed override and exposes a raw response, including body, HTTP status, and headers. A successful HTTP response is not a successful payment. Fetch the order status to establish the outcome.

## Order and refund status

| Method | REST path | Required wire fields | Optional fields | Reference alias |
| --- | --- | --- | --- | --- |
| `getOrderStatus()` | `getOrderStatus.do` | `orderId` | `language` | `orderId` |
| `getOrderStatusExtended()` | `getOrderStatusExtended.do` | At least one of `orderId`, `orderNumber` | `language`, `fraudLevel` | `orderId` |
| `getRefundStatus()` | `EPG: rest/public/getRefundStatus.do` | Neither selector is marked required | `mdOrder`, `externalRefundId`, `language` | `mdOrder` |

All three methods send credentials in the request body. For the extended order status method, `orderId` takes priority when both selectors are supplied. Its response version is chosen in the merchant's **Version of getOrderStatusExtended** setting, not by an invented request field (pages 106 – 107).

The basic method returns case-sensitive names including `ErrorCode`, `ErrorMessage`, `OrderStatus`, `OrderNumber`, `Pan`, `Amount`, `Ip`, and `Description`. The extended method uses `errorCode`, `errorMessage`, `orderStatus`, and nested blocks. The driver recognizes both error-code spellings while preserving response data. A successful status query can describe a declined order.

| Numeric order status | Bank meaning |
| --- | --- |
| `-1` | Decline fallback |
| `0` | Registered, unpaid; another attempt can be possible |
| `1` | Approved one-phase transaction or held preauthorization in two-phase processing |
| `2` | Deposited |
| `3` | Reversed |
| `4` | Refunded |
| `6` | Declined |
| `7` | Card added |
| `8` | Card updated |
| `9` | Card verified |
| `10` | Recurring template added |

For a two-phase transaction, status `1` is an authorization hold; settlement requires capture and status `2`. Statuses `7` – `10` describe their corresponding service operations rather than a purchase settlement.

`getRefundStatus()` returns an object containing a `refunds` array, not a bare array and not one numeric order status. Each refund has `actionCode` and can include `refNum`, `authorizationResponseId`, `refundDate`, `amount`, `reversed`, and `externalRefundId` (pages 101 – 106). Inspect each refund's processing result. The manual's response table uses uppercase error fields, while its examples use lowercase fields and an `error` flag. Its only request example includes the `public/` path component, which this descriptor follows.

The extended status response can include card, binding, payment amount, issuer, fraud, merchant parameter, and signed transaction data blocks according to the configured version and merchant permissions. **Receive 3DS requisites of transactions** controls inclusion of `secureAuthInfo`. Binding data requires the appropriate binding permissions (pages 117 – 119).

## Payments report

`getLastOrdersForMerchants()` calls `getLastOrdersForMerchants.do` under the REST base (pages 137 – 154).

| Required wire fields | Optional fields |
| --- | --- |
| `size`, `from`, `to`, `transactionStates`, `merchants` | `language`, `page`, `searchByCreatedDate` |

`size` is at most 200. `page` is zero-based and defaults to zero. `transactionStates` is a comma-separated list of `CREATED`, `APPROVED`, `DEPOSITED`, `DECLINED`, `REVERSED`, or `REFUNDED`. `merchants` is a comma-separated list of merchant logins; an explicitly empty value requests all merchants visible to the user. Visibility depends on the user's configured permissions.

The `from` field table specifies `YYYYMMDDHHmm`, while `to` specifies `YYYYMMDDHHmmss`; the example supplies seconds for both. The driver preserves the supplied strings. Use the format confirmed by the bank for the target EPG deployment.

`searchByCreatedDate=true` filters by creation time. The default `false` filters by payment time, so `CREATED` and `DECLINED` orders cannot appear with that default. Results contain `orderStatuses`, `totalCount`, `page`, and `pageSize`; each order is represented by extended status data. Paginate explicitly to retrieve the full report.

## Fee calculation

| Method | EPG path | Authentication |
| --- | --- | --- |
| `calculateFee()` | `fee/calculate.do` | No API credentials in the documented schema |
| `calculateFeePublic()` | `fee/public/calculate.do` | `userName`, `password` in the body |

Both descriptors send JSON bodies, following the fee request examples on page 158. The request table lists `$PAN` and `expiry` as required, with `mdOrder` and `bindingId` optional, while the expiry description qualifies its requirement for requests without a binding. The driver requires either `bindingId` or both `$PAN` and `expiry`; the caller must confirm which form the bank enables. `expiry` uses `YYYYMM`. A supplied Omnipay card maps its number to `$PAN` and expiry to `expiry`. Only the public variant documents `language`. `transactionReference` maps to `mdOrder` according to the field table.

There are two inconsistencies on pages 155 – 158: examples spell the order field `MDORDER`, while the parameter table spells it `mdOrder`; and the binding descriptions do not unambiguously specify the alternative card/binding requirements. The driver uses the table's `mdOrder` mapping and permits the binding alternative without requiring unrelated card data. When a bank confirms another field spelling, supply that exact case-sensitive wire field explicitly. Permitting the binding alternative locally does not establish that it is enabled by the bank. The non-public example additionally includes `$CVC` without a matching request-table entry. Endpoint overrides change routing; they do not change JSON encoding.

The system setting `fee.service.enabled` and merchant permission **Fee calculation allowed** must be enabled. Use of a binding also requires binding permissions. A returned `status` of `SUCCESS` provides `feeAmountFormatted` and `totalAmountFormatted`. `ERROR` and `UNAVAILABLE` are unsuccessful fee calculations and omit the amount fields. `UNAVAILABLE` can indicate disabled configuration or a supplied external fee with the applicable permission. A fee quote does not charge the card.
