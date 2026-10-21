# P2P transfers

The operation catalogue follows section 4.17 of the EPG Merchant Integration Guide, PDF pages 249-330. P2P operations use a separately configured EPG root, for example `https://bank.example/epg/`. They are not appended to the IDBank `/payment/rest/` endpoint. Availability depends on the bank and merchant permissions for standard P2P, P2P credit or P2P debit operations (page 249).

| Gateway method | Path relative to EPG root | Body | Authentication | Required wire fields | Pages |
| --- | --- | --- | --- | --- | --- |
| `p2pRegister` | `rest/p2p/register.do` | JSON | Body | `amount`, `currency` | 249-258 |
| `p2pPerform` | `rest/p2p/perform` | JSON | None; internal variant | `orderId` | 258-284 |
| `p2pPublicPerform` | `rest/p2p/public/perform` | JSON | Body | `orderId` | 258-284 |
| `p2pGetFinishedPaymentInfo` | `rest/p2p/getFinishedPaymentInfo.do` | Form | Body | `orderId` | 284-290 |
| `p2pVerify` | `rest/p2p/verify` | JSON | None; internal variant | `orderId` | 290-300 |
| `p2pPublicVerify` | `rest/p2p/public/verify` | JSON | Body | `orderId` | 290-300 |
| `p2pResolveAlias` | `rest/p2p/alias/resolve` | JSON | None documented | `toAlias` containing `alias` | 300-302 |
| `p2pVerifyUnregistered` | `rest/p2p/unregistered/verify` | JSON | URL query | `amount`, `currency` | 302-309 |
| `p2pInfo` | `rest/p2p/info` | Form | None documented | `orderId` | 309-319 |
| `p2pStatus` | `rest/p2p/status` | JSON | URL query | At least one of `orderId`, `orderNumber` | 320-330 |

Authentication means `userName` and `password`. Public perform and verify accept them only in the JSON body (pages 264 and 294). The internal variants omit them and are distinct operations; third-party integrations should use the public variants. Unregistered verification explicitly requires credentials in the URL (pages 302-303). The status example also places credentials in the URL with order identifiers in a JSON body (page 330).

## Amounts, identifiers and optional fields

The normal Omnipay `amount` is a decimal amount in major currency units; `currency` is an ISO alphabetic code. Registration and unregistered verification convert these into minor-unit `amount` and numeric ISO 4217 `currency` fields. `transactionId` maps to `orderNumber`; `transactionReference` maps to `orderId`.

Pass other wire fields through the request's `parameters` array. These values retain their original units and structure. For example, `parameters.amountInput` is a minor-unit amount overriding the registered amount for a perform request; `parameters.amount` is a minor-unit amount for verification; and `parameters.externalFee` is a minor-unit fee. Do not put a major-unit decimal in those fields. The engine does not normalize nested card details, fee currency fields or entries in `params`.

Registration accepts `returnUrl`, `failUrl`, `clientId`, `email`, `externalFee`, `language`, `orderDescription`, `params`, `threeDS2Params`, `sessionTimeoutSecs`, `sessionExpiredDate`, `businessApplicationId`, `sourceBindingId` and `destinationBindingId`. The manual marks `orderNumber` optional but explains that automatic generation depends on the merchant permission; supply `transactionId` when automatic generation is disabled. `clientId` is required when creating bindings. Page 254 labels an additional field “P2P transfer by the alias” without giving an unambiguous wire name; confirm its actual name with the bank rather than inventing one.

For JSON endpoints, pass `fromCard`, `toCard`, `threeDS2Params`, `toAlias` and `params` as PHP arrays. By contrast, perform's `clientBrowserInfo` and `aReqFieldsOverride` must be JSON-encoded strings inside the outer JSON body (pages 262-263). Alias resolution describes `clientBrowserInfo` as a JSON structure (page 301). Preserve that distinction.

## Transfer conditions

The descriptor checks unconditional top-level fields. Callers must also satisfy the manual's conditional requirements:

- For a standard card-to-card perform, supply both `fromCard` and `toCard`. For `CREDIT`, the source card can be omitted; for `DEBIT`, the destination card can be omitted. Account details belong in `params`; page 261 specifically refers to the `toCard` block's `params` for debit transfers. If `type` is absent, EPG derives it from the supplied card data (pages 259-261).
- Perform's source card requires `pan`, `expirationYear` and `expirationMonth`. Its `cvc` depends on the merchant's permissions to pay by card or binding without CVC. The recipient card requires `pan`; its expiry fields are optional. The recipient CVC is marked optional in the table, although its description repeats the merchant-dependent requirement, so confirm the bank's policy (pages 264-269).
- `sourceBindingId` and `destinationBindingId` identify debit and credit bindings. Their interaction with card-block validation is determined by EPG; the manual does not supply a complete substitution matrix (pages 263-264 and 294).
- Verification's `fromCard` and `toCard` blocks are optional; their individual fields are optional too (pages 292-296). Unregistered verification also makes the blocks optional, but requires `pan` inside a supplied block (pages 303-305).
- Verification requires an overriding minor-unit `amount` when `p2p.anonymous.amount.change.allowed` is enabled. If `p2p.verify.mandatory` is enabled, verify before perform. Once verification has run, the amount cannot be changed regardless of that setting (pages 290-292).
- `threeDS2Params` is required for application-based authentication and optional for browser authentication. External MPI results require `EXTERNAL_MPI_RES=true` in `params` and the corresponding `threeDsAuthResult` fields (pages 260 and 266-267).
- Sender and recipient identification details can be supplied through `params` or their card blocks. Providing an identity type conditionally requires the identity number, expiry and issuing country (pages 270-277). EPG owns the field limits and processing-specific validation.

For alias transfers, `toAlias` contains `alias` and optionally `aliasType` (`EMAIL_ID`, `PHONE_NUMBER`, `NATIONAL_ID`, `IBAN`). The example also supplies `orderId`, `email` and `fromCard`. Resolve the alias with `p2pResolveAlias`, then initiate the transfer through the perform operation (pages 300-302).

Registration and verification have inconsistent fee rules in the manual. Page 251 permits both internal and external fees with the relevant merchant permission, while pages 293 and 304 prohibit combining them for verification. Follow the bank's deployed policy; the client passes the provided fee fields unchanged.

## Responses and routing ambiguities

Registration returns `orderId` and `formUrl`. Perform may return a processed result or a 3DS challenge with `acsUrl` and `paReq`/`cReq`; receiving a challenge does not mean funds have moved. Confirm completion with status. Its `orderStatus` values are `0` registered, `2` authorized, `5` authentication initiated and `6` declined (pages 321 and 324-325).

`p2pInfo` and `p2pGetFinishedPaymentInfo` return information payloads with textual `status`, and their examples omit `errorCode`. A successful information request is not proof of a successful transfer: the finished-info example has `status: DECLINED` (page 290). Alias resolution returns `bankName` and `recipientName` without an `errorCode` in the example (page 302). Verification responses use the ordinary `errorCode` contract and can include `feeDescriptionList` and formatted fee totals. Keep all response fields when interpreting bank-specific statuses or fee components.

For `p2pInfo`, the documented textual statuses are `APPROVED`, `CREATED`, `DECLINED`, `DEPOSITED`, `REFUNDED` and `REVERSED` (page 315). The finished-info section does not repeat this enumeration. Its specialized response evaluates the returned payment status; `DECLINED` must not become success merely because the request returned a JSON payload. Perform does not document `orderStatusCode` or `orderStatusName`: its `info` is a human-readable message, and final confirmation should use a status operation rather than guessing a numeric status field.

The manual does not define routing consistently. The following routing choices follow its examples:

- Registration uses `.do`, matching the general request structure on pages 7-8 and the extended example on page 257. The shorter example on page 257 omits `.do`.
- Perform and verify use suffixless paths from the concrete examples on pages 282 and 299. Pages 291 and 300 also mention `.do` variants. The public variants are named without `.do` on pages 258 and 290. The catalogue does not silently retry another path because retrying a money transfer could duplicate it.
- Alias resolution has a method name, nested alias table and JSON example, but no complete URL, authentication contract or top-level mandatory-field table (pages 300-302). Its descriptor follows that named route and example, requires the `toAlias` block, and does not invent credentials. Ensure `toAlias.alias` is present; confirm routing and access policy with the bank before use.
- Finished-info's parameter table requires credentials (pages 284-285), although its URL example omits them (page 290). The descriptor follows the table. Information lookup lists only `orderId` and `language` and its example omits credentials (pages 309 and 318); its descriptor follows that operation-specific contract.
- Form encoding is used for the two information lookups under the general POST recommendation on pages 8-9. JSON encoding is used where the operation's examples explicitly show JSON bodies, irrespective of the `.do` suffix. Status v.1 and v.2 share one documented route; no version selector is defined (pages 320-330).

Use a named operation's endpoint override when the bank confirms a different deployed route. An override changes routing for that documented operation; it does not define an unrestricted raw API operation.

No endpoint is inferred from the existing payment REST host. Confirm that the configured EPG root and selected routes are enabled for your merchant before sending a transfer.
