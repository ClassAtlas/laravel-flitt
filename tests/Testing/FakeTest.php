<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Facades\Flitt;
use ClassAtlas\Flitt\Http\BrowserReturnRequest;
use ClassAtlas\Flitt\Http\CallbackRequest;
use ClassAtlas\Flitt\Requests\CheckoutTokenRequest;
use ClassAtlas\Flitt\Requests\CheckoutUrlRequest;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\Requests\RecurringRequest;
use ClassAtlas\Flitt\Testing\FakeCallback;
use ClassAtlas\Flitt\Testing\FakeOrder;
use ClassAtlas\Flitt\Tests\Support\ProbeRequest;
use ClassAtlas\Flitt\Tests\Support\Samples;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use Saloon\Exceptions\NoMockResponseFoundException;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test', 'flitt.credit_key' => 'testcredit']);
});

it('answers every operation with a sensible default, built from what was sent', function () {
    Flitt::fake();

    $checkout = Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->url();
    $token = Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->token();
    $status = Flitt::status('inv-1042');
    $charge = Flitt::recurring('inv-1043', 9_900, Currency::USD, 'Tuition', 'saved-card-token')->charge();

    expect($checkout->checkoutUrl)->toStartWith('https://pay.flitt.com/merchants/')
        ->and($checkout->paymentId)->toBeInt()
        ->and($token->token)->toMatch('/^[0-9a-f]{40}$/')
        ->and($status)->orderId->toBe('inv-1042')->orderStatus->toBe(OrderStatus::APPROVED)
        ->and($charge)
        ->orderId->toBe('inv-1043')
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->amount->toBe(9_900)
        ->currency->toBe(Currency::USD)
        ->rectoken->toBe('saved-card-token');
});

it('answers with the order a test prepares', function () {
    Flitt::fake([
        OrderStatusRequest::class => FakeOrder::declined(1097)->amount(12_500)->transactionId(2298105279),
    ]);

    expect(Flitt::status('inv-1042'))
        ->orderId->toBe('inv-1042')
        ->orderStatus->toBe(OrderStatus::DECLINED)
        ->responseCode->toBe(1097)
        ->amount->toBe(12_500)
        ->and(Flitt::status('inv-1042')->additionalInfo->transactionId)->toBe(2298105279);
});

it('accepts a plain Saloon mock response too', function () {
    Flitt::fake([CheckoutTokenRequest::class => MockResponse::make(['response' => ['token' => 'prepared-token']])]);

    expect(Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->token()->token)->toBe('prepared-token');
});

it('signs its answers with the key the call used, so per-call credentials work too', function () {
    Flitt::fake();

    $school = Flitt::withCredentials(new Credentials(1396424, 'school-payment-key'));

    expect($school->status('inv-1042'))->merchantId->toBe(1396424);
    Flitt::assertSent(OrderStatusRequest::class, fn (array $params): bool => $params['merchant_id'] === 1396424);
});

it('asserts what was sent, under Flitt\'s parameter names', function () {
    Flitt::fake();

    Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->preauth()->url();

    Flitt::assertSent(CheckoutUrlRequest::class);
    Flitt::assertSent(CheckoutUrlRequest::class, fn (array $params): bool => $params['order_id'] === 'inv-1042'
        && $params['amount'] === 12_500
        && $params['preauth'] === 'Y'
        && ! array_key_exists('signature', $params));
    Flitt::assertNotSent(RecurringRequest::class);
    Flitt::assertNotSent(CheckoutUrlRequest::class, fn (array $params): bool => $params['order_id'] === 'inv-9999');

    expect(fn () => Flitt::assertSent(OrderStatusRequest::class))->toThrow(AssertionFailedError::class)
        ->and(fn () => Flitt::assertNothingSent())->toThrow(AssertionFailedError::class);
});

it('asserts that nothing was sent', function () {
    Flitt::fake();

    Flitt::assertNothingSent();
});

it('throws for a request with no prepared answer and no default, so nothing reaches Flitt', function () {
    Flitt::fake();

    app(ClassAtlas\Flitt\Flitt::class)->send(new ProbeRequest);
})->throws(NoMockResponseFoundException::class);

it('asks for Flitt::fake() before asserting', function () {
    Flitt::assertNothingSent();
})->throws(LogicException::class, 'Flitt::fake()');

it('builds correctly signed callbacks and returns for an app\'s own routes', function () {
    Route::post('flitt/callback', static fn (CallbackRequest $request): array => [
        'order_id' => $request->order()->orderId,
        'transaction_id' => $request->order()->additionalInfo->transactionId,
    ]);
    Route::post('flitt/return', static fn (BrowserReturnRequest $request) => redirect('/invoices/'.$request->order()->orderId, 303));

    $this->postJson('/flitt/callback', FakeCallback::for(
        FakeOrder::approved()->orderId('inv-1042')->transactionId(2298105279),
    )->signedWith('test'))->assertOk()->assertExactJson(['order_id' => 'inv-1042', 'transaction_id' => 2298105279]);

    $this->post('/flitt/return', FakeCallback::for(FakeOrder::declined(1097)->orderId('inv-1042'))->signedWith('test'))
        ->assertRedirect('/invoices/inv-1042');

    $this->postJson('/flitt/callback', FakeCallback::for(FakeOrder::approved()->payout()->orderId('payout-88'))->signedWith('testcredit'))
        ->assertOk();

    $this->postJson('/flitt/callback', FakeCallback::for(FakeOrder::approved())->signedWith('wrong-key'))
        ->assertForbidden();
});

it('fills a fake callback with every field a real one has', function () {
    $real = Samples::fields(
        Samples::first(static fn (array $s): bool => $s['name'] === 'cb/c-approve-n'),
    );
    $fake = FakeCallback::for(FakeOrder::approved())->signedWith('test');

    expect(array_diff(array_keys($real), array_keys($fake), ['response_signature_string', 'fee_oplata']))->toBe([]);
});

it('keeps the fake after scoped instances are reset, as the queue worker does between jobs', function () {
    Flitt::fake();

    app()->forgetScopedInstances();

    app(ClassAtlas\Flitt\Flitt::class)->status('inv-1042');
    Flitt::assertSent(OrderStatusRequest::class);
});

it('dates a fake order in Flitt\'s time zone, on the test clock', function () {
    CarbonImmutable::setTestNow('2026-11-08 10:00:00 UTC');

    try {
        expect(FakeCallback::for(FakeOrder::approved())->signedWith('test')['order_time'])->toBe('08.11.2026 12:00:00');
    } finally {
        CarbonImmutable::setTestNow();
    }
});
