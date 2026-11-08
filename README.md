# Flitt for Laravel

[![Latest version](https://img.shields.io/packagist/v/class-atlas/laravel-flitt.svg)](https://packagist.org/packages/class-atlas/laravel-flitt)
[![Tests](https://github.com/ClassAtlas/laravel-flitt/actions/workflows/tests.yml/badge.svg)](https://github.com/ClassAtlas/laravel-flitt/actions/workflows/tests.yml)
[![Downloads](https://img.shields.io/packagist/dt/class-atlas/laravel-flitt.svg)](https://packagist.org/packages/class-atlas/laravel-flitt)
[![License](https://img.shields.io/packagist/l/class-atlas/laravel-flitt.svg)](LICENSE)

A Laravel client for the [Flitt](https://flitt.com) payment gateway (formerly Fondy): hosted checkout, order status,
recurring charges, and signed callbacks and browser returns. It speaks Flitt's own words, so every
name here can be looked up in [Flitt's documentation](https://docs.flitt.com).

- Every request is signed and every signed answer is checked.
- Credentials can change per call, for apps that hold several merchants.
- `Flitt::fake()` lets your tests run without the network, including correctly signed callbacks.

> **Beta.** `1.0.0-beta.1` holds hosted checkout, order status, recurring charges, callbacks and browser returns, and
> the fake. Capture, reverse, the transaction list and payouts follow in later betas. Until 1.0.0, public names may
> still change between betas; the changelog says when they do.

## Supported versions

| Package | Laravel | PHP      |
|---------|---------|----------|
| 1.x     | 13      | 8.4, 8.5 |

Every combination is tested in CI with the lowest and the latest allowed dependencies. [SUPPORT.md](SUPPORT.md) says
when versions are added and dropped.

## Installation

```bash
composer require class-atlas/laravel-flitt:^1.0@beta
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

An app with several merchants passes credentials per call instead: see [Several merchants](#several-merchants).

## Amounts, currencies and dates

- **Amounts are integers in minor units**, everywhere, both ways: `12_500` is 125.00 GEL. The package never hands you
  a float. Where Flitt answers in major units (`additionalInfo`'s `capture_amount` and fees), it converts exactly.
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

What the payer did arrives twice: as a [callback](#callbacks-and-browser-returns) to your server, and as the payer's
browser coming back to `responseUrl`. **The callback is the source of truth.**

### Checkout options

Every method sets the Flitt parameter of the same name. Each returns a new builder, so keep the result:
`$checkout = $checkout->lang(Lang::KA);`.

| Method                                  | Flitt parameter             | Notes                                                                 |
|-----------------------------------------|-----------------------------|-----------------------------------------------------------------------|
| `serverCallbackUrl(string)`             | `server_callback_url`       | Where Flitt posts the [callback](#callbacks-and-browser-returns)     |
| `responseUrl(string)`                   | `response_url`              | Where the payer's browser returns                                    |
| `cancelUrl(string)`                     | `cancel_url`                | Where the payer lands after "Cancel"                                 |
| `lifetime(int $seconds)`                | `lifetime`                  | Default 36000 (10 hours); then the order expires                     |
| `merchantData(string)`                  | `merchant_data`             | Echoed back in every answer and callback                             |
| `preauth(bool = true)`                  | `preauth`                   | Hold the money; capture it later                                     |
| `delayed(bool)`                         | `delayed`                   | Default on: a declined payer may retry on the same page              |
| `requiredRectoken(bool = true)`         | `required_rectoken`         | Ask for a card token for [recurring charges](#recurring-charges)     |
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

To charge a saved card, use a [recurring charge](#recurring-charges): Flitt's page cannot pay with a saved card.

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

## Recurring charges

Save the card's token on a first checkout with `requiredRectoken()` (or with `verification()` too, to approve the card
without keeping the money); it arrives as `$order->rectoken` in the callback. Then charge it without the payer:

```php
$order = Flitt::recurring(
    orderId: 'inv-1043',
    amount: 12_500,
    currency: Currency::GEL,
    orderDesc: 'Tuition, December',
    rectoken: $savedCard->rectoken,
)
    ->serverCallbackUrl(route('flitt.callback'))
    ->charge();
```

Options: `serverCallbackUrl`, `lifetime`, `merchantData`, `preauth`, `senderEmail`, `productId`, `clientIp` and
`reservationData`, as for a checkout. The card's CVV is never sent: it is card data.

There is no 3-D Secure on a recurring charge, and no page: the answer is final. Flitt has no call to delete a token; it
lapses with the card (`$order->rectokenLifetime`).

## Callbacks and browser returns

Flitt posts each event (a payment attempt, a capture, each reverse, a recurring charge, a payout's result) to
`serverCallbackUrl`, and sends the payer's browser back to `responseUrl` or `cancelUrl`. Both carry the same signed
order. The package checks the signature before your controller runs.

```php
// routes/web.php
Route::post('flitt/callback', FlittCallbackController::class)->name('flitt.callback');
Route::post('flitt/return', FlittReturnController::class)->name('flitt.return');
```

Both arrive as cross-site `POST`s, so exclude them from CSRF, and don't rely on the session in them:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['flitt/callback', 'flitt/return']);
})
```

```php
use ClassAtlas\Flitt\Http\CallbackRequest;

final class FlittCallbackController
{
    public function __invoke(CallbackRequest $request): Response
    {
        HandleFlittOrder::dispatch($request->order());

        return response('', 200);
    }
}
```

```php
use ClassAtlas\Flitt\Http\BrowserReturnRequest;

final class FlittReturnController
{
    public function __invoke(BrowserReturnRequest $request): RedirectResponse
    {
        $order = $request->order();

        // Show the result page with a GET. The callback, not this return, decides whether the order is paid.
        return redirect()->route('invoices.show', $order->orderId, status: 303);
    }
}
```

**When a check fails**, the request answers `403` (bad or missing signature, unknown merchant) or `400` (a body it cannot
read), never `200`. Flitt keeps retrying a callback for about a day, so callbacks missed while a key was wrong arrive
again once it is fixed.

**Handle each event once, for the right merchant:**

- Look the order up by `$order->merchantId` **and** `$order->orderId`, never by `orderId` alone. An order id is unique
  only within one merchant, and anyone holding a merchant's key can sign a callback for that merchant.
- Retries repeat the event exactly. `$order->additionalInfo->transactionId` is new for every event and the same on a
  retry: store it and skip ones you have seen.
- A payer's browser may come back before the callback arrives, or twice.

**Processing a stored body later**, outside a request:

```php
use ClassAtlas\Flitt\Callbacks\CallbackVerifier;

$order = app(CallbackVerifier::class)->verify($body, $contentType);   // throws on a bad signature
```

Flitt sends callbacks from the addresses listed in its documentation (`3.75.125.89` so far). The package does not check
them: the signature proves the sender. Add a firewall rule if you want one.

## Several merchants

Pass credentials per call. The client you get back uses them for every call made through it:

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

Callbacks need the right key too. Bind a resolver that finds the credentials for the `merchant_id` in the callback, or
`null` for a merchant you don't know:

```php
use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use ClassAtlas\Flitt\Credentials;

final class SchoolCredentialsResolver implements CredentialsResolver
{
    public function forMerchant(int $merchantId): ?Credentials
    {
        $school = School::firstWhere('flitt_merchant_id', $merchantId);

        return $school?->flittCredentials();
    }
}

// AppServiceProvider::register()
$this->app->bind(CredentialsResolver::class, SchoolCredentialsResolver::class);
```

The default resolver reads `config/flitt.php` and knows only that merchant.

## Errors

Every exception extends `ClassAtlas\Flitt\Exceptions\FlittException`.

| Exception                     | When                                                                                         |
|-------------------------------|----------------------------------------------------------------------------------------------|
| `ApiErrorException`           | Flitt answered with an error: `$e->errorCode` (e.g. `1018`), `$e->errorMessage`, `$e->requestId` |
| `ConnectionFailedException`   | No answer: a timeout or a network failure                                                    |
| `InvalidSignatureException`   | An answer or callback whose signature does not match, or is missing                          |
| `UnknownMerchantException`    | A callback for a merchant your resolver doesn't know                                         |
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

A rejected callback or browser return is always logged, as a warning with the reason, the merchant id and the order id
only, to `FLITT_LOG_CHANNEL` when it is set.

## Testing your app

`Flitt::fake()` stops every call from leaving your app and answers with a sensible default (an approved order, a
checkout URL and so on). Prepare an answer per request when you need a particular one:

```php
use ClassAtlas\Flitt\Facades\Flitt;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\Testing\FakeOrder;

Flitt::fake([
    OrderStatusRequest::class => FakeOrder::approved()->orderId('inv-1042')->amount(12_500),
]);

// ... run the code under test ...

Flitt::assertSent(OrderStatusRequest::class, fn (array $params) => $params['order_id'] === 'inv-1042');
Flitt::assertNotSent(RecurringRequest::class);
Flitt::assertNothingSent();   // or: nothing at all left the app
```

`$params` holds what was sent, under Flitt's names (`order_id`, `amount`, `preauth` as `Y`/`N`, `merchant_id`), without
the signature. A request with no prepared answer and no default throws, so nothing reaches Flitt by accident. The fake
still uses your credentials, from `config/flitt.php` or `withCredentials()`, and signs its answers with them, so the
package's signature checks run in your tests too.

`FakeOrder` starts from `approved()`, `declined($code)`, `processing()`, `created()`, `expired()` or `reversed()`, and
takes `orderId()`, `merchantId()`, `amount()`, `currency()`, `transactionId()`, `rectoken()`, `merchantData()` and
`payout()`. Whatever you leave out is taken from the request it answers. A prepared answer can also be a Saloon
`MockResponse`.

**Testing your callback controller** needs a correctly signed body. `FakeCallback` signs one with the key you give it:

```php
use ClassAtlas\Flitt\Testing\FakeCallback;
use ClassAtlas\Flitt\Testing\FakeOrder;

$this->postJson(route('flitt.callback'), FakeCallback::for(
    FakeOrder::approved()->orderId('inv-1042')->transactionId(2298105279),
)->signedWith('test'))->assertOk();

// A browser return is a form post
$this->post(route('flitt.return'), FakeCallback::for(FakeOrder::declined(1097))->signedWith('test'))
    ->assertRedirect();
```

**Saloon directly.** The package is built on [Saloon](https://docs.saloon.dev). If you already fake Saloon requests,
the connector `ClassAtlas\Flitt\FlittConnector` and the request classes below work with `MockClient` as usual.

| Operation          | Request class                                  |
|--------------------|-------------------------------------------------|
| Checkout URL       | `ClassAtlas\Flitt\Requests\CheckoutUrlRequest`     |
| Checkout token     | `ClassAtlas\Flitt\Requests\CheckoutTokenRequest`   |
| Order status       | `ClassAtlas\Flitt\Requests\OrderStatusRequest`     |
| Recurring charge   | `ClassAtlas\Flitt\Requests\RecurringRequest`       |

Flitt signs most answers, so a `MockResponse` for a signed operation needs a correct signature; `Flitt::fake()` makes
one for you.

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

## Not covered

| What                                                 | Status      | Why                                                                                    |
|------------------------------------------------------|-------------|----------------------------------------------------------------------------------------|
| Capture and reverse                                  | Before 1.0.0 | Built and tested on the test merchant next; in a later beta                            |
| Transaction list                                     | Before 1.0.0 | In a later beta                                                                        |
| Card and IBAN payouts                                | Before 1.0.0 | In a later beta                                                                        |
| Subscriptions (scheduled payments)                   | Planned     | Flitt's scheduled-payment callback has not been seen on the test merchant yet          |
| Reports                                              | Planned     | The test merchant's reports are empty, so a report row has never been seen             |
| Split settlement                                     | Planned     | A settlement never completed on the test merchant                                      |
| Chargeback callback                                  | Planned     | A chargeback cannot be raised on the test merchant                                     |
| Apple Pay and Google Pay tokens (encrypted)          | Planned     | Needs Apple or Google certificates registered with Flitt                               |
| A saved card on the hosted page (`rectoken` on a checkout) | Not offered | Flitt's page ignores the token, always asks 3-D Secure, and echoes the token even when another card paid. Use a [recurring charge](#recurring-charges) |
| Checkout redirect (`/api/checkout/redirect`)         | Not planned | It opens the same page as a checkout URL                                               |
| Fiscal data                                          | Not planned | For merchants in Uzbekistan only                                                       |
| Payments with a card number, a card's CVV (`cvv2` on a recurring charge), or a decrypted wallet card | Never | They put your servers in PCI DSS scope |

Open an issue if you need a planned item sooner.

## Security

Report vulnerabilities privately, as [SECURITY.md](SECURITY.md) describes, never in a public issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md): running the tests and the live suite against Flitt's test merchant, and how
releases are made.

## License

MIT. See [LICENSE](LICENSE).
