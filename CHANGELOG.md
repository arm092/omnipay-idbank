# Changelog

## 1.1.2

- Support Guzzle 7 and 8 without requiring consumers to downgrade their HTTP stack.
- Remove the development-only Guzzle 7 adapter and check both major versions in CI.
- Disable Composer discovery's dependency auto-installation for package development;
  runtime PSR-18 and PSR-17 discovery remains available without the plugin.

## 1.1.1

- Require Guzzle 7 directly so installation needs only the gateway package.
- Clarify Laravel and standalone installation instructions.
- Map supported capabilities to sections 4.1 through 4.17 of the EPG guide.
- Format Composer metadata consistently and retain Apricode Team authorship.

## 1.1.0

- Add 41 named EPG operations for direct cards, 3DS eligibility and completion,
  bindings, recurring payments, order/refund reporting, fees and P2P transfers.
- Add Omnipay card-management aliases and card/reference field mapping.
- Add separate live/test EPG roots and environment-specific endpoint overrides.
- Support JSON and form contracts, legacy error/status field casing, raw
  authentication handoff responses and raw boolean results.
- Support hosted registration options and opaque merchant-managed CoF data.
- Preserve the v1.0 payment interface and server-side completion checks.

## 1.0.0

- Initial hosted IDBank EPG purchase/authorization, completion, capture, refund,
  reversal and transaction lookup driver.
