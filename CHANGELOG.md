# Changelog

All notable changes to `class-atlas/laravel-flitt` are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `config/flitt.php` with the merchant's id and keys, timeouts and logging, published with the `flitt-config` tag.
- `Credentials` for one merchant, kept out of dumps, JSON, logs, stack traces and serialized payloads.
- `Flitt::withCredentials()` for apps that hold several merchants.
- The `CredentialsResolver` contract, and a default that knows the merchant in `config/flitt.php`.
- Every request signed as Flitt's protocol 1.0.1 expects, and every signed answer checked strictly, with the credit key
  for payout orders. A `|` in a value is refused before anything is sent.
- `FlittConnector` on `pay.flitt.com`, with the configured timeouts and no retries.
- The exceptions: `ApiErrorException`, `ConnectionFailedException`, `InvalidSignatureException`,
  `UnknownMerchantException`, `MissingCredentialsException` and `InvalidParameterException`, all extending
  `FlittException`.
- One log line per call when `FLITT_LOG_REQUESTS` is on, without bodies, keys, signatures or personal data.
- `Responses\Order` and `Responses\AdditionalInfo`: Flitt's order fields in camelCase, amounts as integers in minor
  units (major-unit decimals converted exactly), dates as `CarbonImmutable` in Europe/Kyiv, and `raw` with everything
  Flitt sent.
- `Flitt::checkout(orderId:, amount:, currency:, orderDesc:)`, an immutable builder with Flitt's checkout options,
  finished with `url()` (`CheckoutUrl`) or `token()` (`CheckoutToken`). Open banking and installments are options.
  `ReservationData` for the payer details Flitt passes to the bank.
- `Flitt::status($orderId)`, returning `Order`.
- `Flitt::recurring(orderId:, amount:, currency:, orderDesc:, rectoken:)`, an immutable builder finished with
  `charge()`, to charge a card saved with `requiredRectoken()`. No CVV is ever sent.
- `Http\CallbackRequest` and `Http\BrowserReturnRequest`: form requests that check a callback or browser return
  before the controller runs (403 for a bad signature or an unknown merchant, 400 for an unreadable body), with
  `->order()`.
- `Callbacks\CallbackVerifier::verify($body, $contentType)` for bodies processed later. Payout callbacks are checked
  with the credit key; protocol 2.0 bodies are read too.
- The Saloon requests `CheckoutUrlRequest`, `CheckoutTokenRequest`, `OrderStatusRequest` and `RecurringRequest`.
- The enums `OrderStatus`, `TranType`, `CaptureStatus`, `ReverseStatus`, `Currency`, `Lang`, `PaymentSystem` and
  `PaymentMethod`. A value this version does not know reads as `null` and never breaks parsing.
