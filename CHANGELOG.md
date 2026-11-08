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
