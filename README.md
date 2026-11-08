# Flitt for Laravel

[![Latest version](https://img.shields.io/packagist/v/class-atlas/laravel-flitt.svg)](https://packagist.org/packages/class-atlas/laravel-flitt)
[![Tests](https://github.com/ClassAtlas/laravel-flitt/actions/workflows/tests.yml/badge.svg)](https://github.com/ClassAtlas/laravel-flitt/actions/workflows/tests.yml)
[![Downloads](https://img.shields.io/packagist/dt/class-atlas/laravel-flitt.svg)](https://packagist.org/packages/class-atlas/laravel-flitt)
[![License](https://img.shields.io/packagist/l/class-atlas/laravel-flitt.svg)](LICENSE)

A Laravel client for the [Flitt](https://flitt.com) payment gateway (formerly Fondy): hosted checkout, order status,
capture, reverse, recurring charges, card and IBAN payouts, and signed callbacks. It speaks Flitt's own words, so every
name here can be looked up in [Flitt's documentation](https://docs.flitt.com).

- Every request is signed and every signed answer is checked.
- Credentials can change per call, for apps that hold several merchants.

> **Not released yet.** The first beta is `1.0.0-beta.1`. This README covers what is built so far.

## Supported versions

| Package | Laravel | PHP      |
|---------|---------|----------|
| 1.x     | 13      | 8.4, 8.5 |

Every combination is tested in CI with the lowest and the latest allowed dependencies. [SUPPORT.md](SUPPORT.md) says
when versions are added and dropped.

## Installation

```bash
composer require class-atlas/laravel-flitt
```

Publish the config file if you want to change its defaults:

```bash
php artisan vendor:publish --tag=flitt-config
```

## Configuration

An app with one Flitt merchant only needs its `.env`:

```dotenv
FLITT_MERCHANT_ID=1549901
FLITT_PAYMENT_KEY=test
FLITT_CREDIT_KEY=testcredit   # only needed for payouts
```

These are Flitt's public test credentials: they work against the real host, `pay.flitt.com`, and never move money.

| Key (`config/flitt.php`) | Environment variable    | Default | Meaning                                                  |
|--------------------------|-------------------------|---------|----------------------------------------------------------|
| `merchant_id`            | `FLITT_MERCHANT_ID`     | none    | Your merchant id                                         |
| `payment_key`            | `FLITT_PAYMENT_KEY`     | none    | Signs payments, and checks their answers and callbacks   |
| `credit_key`             | `FLITT_CREDIT_KEY`      | none    | Signs payouts, and checks payout answers and callbacks   |
| `timeout`                | `FLITT_TIMEOUT`         | `10`    | Seconds to wait for Flitt's answer                       |
| `connect_timeout`        | `FLITT_CONNECT_TIMEOUT` | `3`     | Seconds to wait for the connection                       |
| `log_requests`           | `FLITT_LOG_REQUESTS`    | `false` | Log each call (see [Logging](#logging))                  |
| `log_channel`            | `FLITT_LOG_CHANNEL`     | default | Log channel for those lines                              |

An app with several merchants passes credentials per call instead:

```php
use ClassAtlas\Flitt\Credentials;

$flitt = Flitt::withCredentials(new Credentials(
    merchantId: $school->flitt_merchant_id,
    paymentKey: $school->flitt_payment_key,
    creditKey: $school->flitt_credit_key,   // optional, for payouts
));

$checkout = $flitt->checkout(...)->url();
```

A call without credentials falls back to `config/flitt.php`; with neither, it throws `MissingCredentialsException`
rather than guessing a merchant.

## Amounts, currencies and dates

- **Amounts are integers in minor units**, everywhere, both ways: `12_500` is 125.00 GEL. The package never hands you
  a float. Where Flitt answers in major units (`capture_amount`, the transaction list), it converts exactly.
- **Currencies** are `ClassAtlas\Flitt\Enums\Currency`: `GEL`, `USD`, `EUR`, `AMD`, `AZN`, `KZT`, `MDL`, `UZS`, `GBP`,
  `CZK`. All have two decimals. Which ones your merchant may use is Flitt's decision. Only GEL payments have been tried
  on the test merchant.
- **Dates** are `CarbonImmutable`. Flitt sends local time without an offset (`08.11.2026 14:08:28`); the package reads
  it as `Europe/Kyiv` time, which is what Flitt's servers have used so far. The raw string is kept beside it.

## Hosted checkout

Create an order and send the payer to Flitt's payment page:

```php
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Facades\Flitt;

$checkout = Flitt::checkout(
    orderId: 'inv-1042',
    amount: 12_500,
    currency: Currency::GEL,
    orderDesc: 'Tuition, November',
)
    ->serverCallbackUrl(route('flitt.callback'))
    ->responseUrl(route('flitt.return'))
    ->url();

return redirect()->away($checkout->checkoutUrl);
```

`orderId` is yours: unique per merchant. Sending the same order again with the same parameters is safe, Flitt returns the
same page; with different parameters it answers `1013 Duplicate order`.

For Flitt's embedded checkout or mobile SDKs, ask for a token instead of a URL:

```php
$token = Flitt::checkout(...)->token();   // $token->token
```

What the payer did arrives twice: as a callback to your server, and as the payer's browser coming back to
`responseUrl`. **The callback is the source of truth.**

### Checkout options

Every method sets the Flitt parameter of the same name. Each returns a new builder, so keep the result:
`$checkout = $checkout->lang(Lang::KA);`.

| Method                                  | Flitt parameter             | Notes                                                                 |
|-----------------------------------------|-----------------------------|-----------------------------------------------------------------------|
| `serverCallbackUrl(string)`             | `server_callback_url`       | Where Flitt posts the callback                                       |
| `responseUrl(string)`                   | `response_url`              | Where the payer's browser returns                                    |
| `cancelUrl(string)`                     | `cancel_url`                | Where the payer lands after "Cancel"                                 |
| `lifetime(int $seconds)`                | `lifetime`                  | Default 36000 (10 hours); then the order expires                     |
| `merchantData(string)`                  | `merchant_data`             | Echoed back in every answer and callback                             |
| `preauth(bool = true)`                  | `preauth`                   | Hold the money; capture it later                                     |
| `delayed(bool)`                         | `delayed`                   | Default on: a declined payer may retry on the same page              |
| `requiredRectoken(bool = true)`         | `required_rectoken`         | Ask for a card token for recurring charges                           |
| `verification(bool = true)`             | `verification`              | Approve the card only to keep its token; Flitt says it reverses the amount itself |
| `senderEmail(string)`                   | `sender_email`              |                                                                       |
| `lang(Lang)`                            | `lang`                      | Page language, e.g. `Lang::KA`                                        |
| `productId(string)`                     | `product_id`                |                                                                       |
| `designId(string)`                      | `design_id`                 | A checkout design from Flitt's merchant portal                        |
| `reservationData(ReservationData)`      | `reservation_data`          | Payer details Flitt passes to the bank                                |
| `paymentSystems(PaymentSystem ...)`     | `payment_systems`           | Open banking or installments, below (several are joined with commas)  |
| `paymentMethod(PaymentMethod)`          | `payment_method`            | Preselect a bank for those                                            |

A `|` in any value throws `InvalidParameterException` before anything is sent: Flitt cannot sign it.

**Open banking and installments** (Georgia) are checkout options, not separate operations:

```php
use ClassAtlas\Flitt\Enums\PaymentMethod;
use ClassAtlas\Flitt\Enums\PaymentSystem;

Flitt::checkout(...)->paymentSystems(PaymentSystem::OPB)->paymentMethod(PaymentMethod::TBC)->url();
Flitt::checkout(...)->paymentSystems(PaymentSystem::INSTALLMENTS)->paymentMethod(PaymentMethod::TBC)->url();
```

> **These payments cannot be reversed through the API.** Flitt refuses a reverse of an open-banking or installment
> order. Their callbacks also carry the payer's IBAN and name in `additionalInfo`: treat them as personal data.

To charge a saved card, use a recurring charge: Flitt's page cannot pay with a saved card.

## Order status

```php
$order = Flitt::status('inv-1042');

$order->orderStatus;       // OrderStatus::APPROVED
$order->amount;            // 12500
$order->reversalAmount;    // 0
$order->maskedCard;        // '444455XXXXXX6666'
$order->additionalInfo->captureStatus;   // CaptureStatus::CAPTURED
```

The same `ClassAtlas\Flitt\Responses\Order` comes back from status, recurring charges, payouts, callbacks and browser
returns. Its properties are Flitt's order fields in camelCase; `$order->raw` keeps everything Flitt sent.

`orderStatus` alone does not say everything: a partial reverse keeps `APPROVED`, and a hold is `APPROVED` too. Read
`reversalAmount`, `settlementAmount` and `additionalInfo->captureStatus` beside it.

## Errors

Every exception extends `ClassAtlas\Flitt\Exceptions\FlittException`.

| Exception                     | When                                                                                         |
|-------------------------------|----------------------------------------------------------------------------------------------|
| `ApiErrorException`           | Flitt answered with an error: `$e->errorCode` (e.g. `1018`), `$e->errorMessage`, `$e->requestId` |
| `ConnectionFailedException`   | No answer: a timeout or a network failure                                                    |
| `InvalidSignatureException`   | An answer or callback whose signature does not match, or is missing                          |
| `MissingCredentialsException` | No credentials for a call, or a payout without a credit key                                  |
| `InvalidParameterException`   | A value Flitt cannot accept, such as one containing `|`                                     |

**The package never retries a call.** After `ConnectionFailedException` you cannot tell whether Flitt acted, so check
the order's status before sending anything again. A blind retry of a recurring charge or a payout could move money
twice.

Flitt answers errors with HTTP 200 and an `error_code`; the package turns every one into `ApiErrorException`, so a
`try`/`catch` is all you need.

## Logging

Off by default. With `FLITT_LOG_REQUESTS=true`, each call logs one line: the operation, merchant id, order id, HTTP
status, Flitt's error code and the time taken. It never logs bodies, keys, signatures, card tokens, card numbers, emails
or IP addresses.

## Enums

All in `ClassAtlas\Flitt\Enums`, backed by Flitt's own values, cases in `SCREAMING_SNAKE_CASE`:

| Enum            | Cases                                                                                   |
|-----------------|-----------------------------------------------------------------------------------------|
| `OrderStatus`   | `CREATED`, `PROCESSING`, `DECLINED`, `APPROVED`, `EXPIRED`, `REVERSED`                  |
| `TranType`      | `PURCHASE`, `VERIFICATION`, `CAPTURE`, `REVERSE`, `P2P_CREDIT`, `P2P_TRANSFER`, `SETTLEMENT` |
| `CaptureStatus` | `HOLD`, `CAPTURED`                                                                      |
| `ReverseStatus` | `CREATED`, `PROCESSING`, `APPROVED`, `DECLINED`                                         |
| `Currency`      | `GEL`, `USD`, `EUR`, `AMD`, `AZN`, `KZT`, `MDL`, `UZS`, `GBP`, `CZK`                    |
| `Lang`          | `KA`, `EN`, `RU`, `UK`, and the other 16 Flitt lists                                    |
| `PaymentSystem` | `CARD`, `OPB`, `INSTALLMENTS`, `P2P_IBAN_GE`                                            |
| `PaymentMethod` | `TBC`, `BOG`, `LIBERTY`, `CREDO`, `DEMO` (Flitt's test bank, `x`)                        |

**Flitt adds values from time to time.** A value this version doesn't know never breaks parsing: the enum property is
`null` and the original string stays in `$order->raw`. When you `match` on an enum, always keep a `default` arm.

## Versioning

The package follows [Semantic Versioning](https://semver.org):

- **Minor** releases add operations, optional parameters, response properties and enum cases, and support for a new
  Laravel or PHP version.
- **Patch** releases fix bugs.
- **Major** releases remove or rename something public, or drop a Laravel or PHP version (on the day its security
  support ends, see [SUPPORT.md](SUPPORT.md)).

Everything this README shows is public. Classes marked `@internal` are not, and may change in any release. Public
classes are `final`; only the package creates response objects.

## Security

Report vulnerabilities privately, as [SECURITY.md](SECURITY.md) describes, never in a public issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md): running the tests and the live suite against Flitt's test merchant, and how
releases are made.

## License

MIT. See [LICENSE](LICENSE).
