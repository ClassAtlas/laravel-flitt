<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\TranType;
use ClassAtlas\Flitt\Exceptions\ApiErrorException;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Facades\Flitt;
use ClassAtlas\Flitt\Http\CallbackRequest;
use ClassAtlas\Flitt\Requests\RecurringRequest;
use ClassAtlas\Flitt\ReservationData;
use ClassAtlas\Flitt\Responses\Order;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Tests\Support\Live;
use ClassAtlas\Flitt\Tests\Support\ProbeCalls;
use ClassAtlas\Flitt\Tests\Support\Samples;
use Illuminate\Support\Facades\Route;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

beforeEach(function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test']);
});

it('sends every option under Flitt\'s own name, and never a CVV', function () {
    $recurring = Flitt::recurring(orderId: 'inv-1043', amount: 12_500, currency: Currency::GEL, orderDesc: 'Tuition, December', rectoken: Live::TEST_RECTOKEN)
        ->serverCallbackUrl('https://school.example.com/flitt/callback')
        ->lifetime(600)
        ->merchantData('{"invoice":43}')
        ->preauth()
        ->senderEmail('payer@example.com')
        ->productId('tuition')
        ->clientIp('203.0.113.10')
        ->reservationData(new ReservationData(account: 'acct-42'));

    expect($recurring->parameters())->toBe([
        'order_id' => 'inv-1043',
        'order_desc' => 'Tuition, December',
        'amount' => 12_500,
        'currency' => 'GEL',
        'rectoken' => Live::TEST_RECTOKEN,
        'server_callback_url' => 'https://school.example.com/flitt/callback',
        'lifetime' => 600,
        'merchant_data' => '{"invoice":43}',
        'preauth' => true,
        'sender_email' => 'payer@example.com',
        'product_id' => 'tuition',
        'client_ip' => '203.0.113.10',
        'reservation_data' => base64_encode('{"account":"acct-42"}'),
    ])->and(method_exists($recurring, 'cvv2'))->toBeFalse();
});

it('charges a saved card and checks Flitt\'s signed answer', function () {
    $answer = ProbeCalls::signedWith('test', [
        'order_id' => 'inv-1043', 'merchant_id' => 1549901, 'order_status' => 'approved', 'tran_type' => 'purchase',
        'amount' => '12500', 'currency' => 'GEL', 'rectoken' => Live::TEST_RECTOKEN, 'response_status' => 'success',
    ]);
    $mockClient = MockClient::global([RecurringRequest::class => MockResponse::make(['response' => $answer])]);

    $order = Flitt::recurring('inv-1043', 12_500, Currency::GEL, 'Tuition, December', Live::TEST_RECTOKEN)->charge();
    $sent = $mockClient->getLastPendingRequest()?->body()?->all()['request'];

    expect($order)
        ->toBeInstanceOf(Order::class)
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->amount->toBe(12_500)
        ->and($mockClient->getLastPendingRequest()?->getUrl())->toBe('https://pay.flitt.com/api/recurring')
        ->and($sent['rectoken'])->toBe(Live::TEST_RECTOKEN)
        ->and($sent['signature'])->toBe(Signer::sign(array_diff_key($sent, ['signature' => true]), 'test'));
});

it('passes Flitt\'s "token not found" on as ApiErrorException', function () {
    MockClient::global([RecurringRequest::class => MockResponse::make(['response' => [
        'error_code' => 1035, 'error_message' => 'Token not found', 'request_id' => 'r1', 'response_status' => 'failure',
    ]])]);

    Flitt::recurring('inv-1043', 12_500, Currency::GEL, 'Tuition', 'unknown-token')->charge();
})->throws(ApiErrorException::class, 'error 1035');

it('refuses an empty card token or a "|" in it before anything is sent', function (string $rectoken) {
    $mockClient = MockClient::global([RecurringRequest::class => MockResponse::make(['response' => []])]);

    expect(fn () => Flitt::recurring('inv-1043', 12_500, Currency::GEL, 'Tuition', $rectoken)->charge())
        ->toThrow(InvalidParameterException::class, 'rectoken');

    $mockClient->assertNothingSent();
})->with(['', 'abc|def']);

it('keeps the card token out of dumps', function () {
    $recurring = Flitt::recurring('inv-1043', 12_500, Currency::GEL, 'Tuition', Live::TEST_RECTOKEN);

    expect(new CliDumper()->dump(new VarCloner()->cloneVar($recurring), true))
        ->not->toContain(Live::TEST_RECTOKEN)
        ->and(print_r($recurring, true))->not->toContain(Live::TEST_RECTOKEN)->toContain('[rectoken] => ********');
});

it('keeps the card token out of stack traces', function () {
    $parameter = new ReflectionMethod(ClassAtlas\Flitt\Flitt::class, 'recurring')->getParameters()[4];

    expect($parameter->getName())->toBe('rectoken')
        ->and($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
});

it('reads the callback of a recurring charge', function () {
    config(['flitt.credit_key' => 'testcredit']);
    Route::post('flitt/callback', static fn (CallbackRequest $request): array => [
        'tran_type' => $request->order()->tranType?->value,
        'rectoken' => $request->order()->rectoken,
    ]);
    $sample = Samples::first(static fn (array $s): bool => $s['name'] === 'cb/a-recurring');

    Samples::post($this, $sample, '/flitt/callback')
        ->assertOk()
        ->assertJson(['tran_type' => TranType::PURCHASE->value, 'rectoken' => Live::TEST_RECTOKEN]);
});
