<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\Lang;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\PaymentMethod;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Exceptions\ApiErrorException;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Facades\Flitt;
use ClassAtlas\Flitt\Requests\CheckoutTokenRequest;
use ClassAtlas\Flitt\Requests\CheckoutUrlRequest;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\ReservationData;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Tests\Support\Vectors;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test']);
});

it('sends every option under Flitt\'s own name', function () {
    $checkout = Flitt::checkout(orderId: 'inv-1042', amount: 12_500, currency: Currency::GEL, orderDesc: 'Tuition, November')
        ->serverCallbackUrl('https://school.example.com/flitt/callback')
        ->responseUrl('https://school.example.com/flitt/return')
        ->cancelUrl('https://school.example.com/flitt/cancel')
        ->lifetime(3600)
        ->merchantData('{"invoice":42}')
        ->preauth()
        ->delayed(false)
        ->requiredRectoken()
        ->verification()
        ->senderEmail('payer@example.com')
        ->lang(Lang::KA)
        ->productId('tuition')
        ->designId('123456')
        ->reservationData(new ReservationData(customerCountry: 'GE', account: 'acct-42'))
        ->paymentSystems(PaymentSystem::OPB)
        ->paymentMethod(PaymentMethod::DEMO);

    expect($checkout->parameters())->toBe([
        'order_id' => 'inv-1042',
        'order_desc' => 'Tuition, November',
        'amount' => 12_500,
        'currency' => 'GEL',
        'server_callback_url' => 'https://school.example.com/flitt/callback',
        'response_url' => 'https://school.example.com/flitt/return',
        'cancel_url' => 'https://school.example.com/flitt/cancel',
        'lifetime' => 3600,
        'merchant_data' => '{"invoice":42}',
        'preauth' => true,
        'delayed' => false,
        'required_rectoken' => true,
        'verification' => true,
        'sender_email' => 'payer@example.com',
        'lang' => 'ka',
        'product_id' => 'tuition',
        'design_id' => '123456',
        'reservation_data' => 'eyJjdXN0b21lcl9jb3VudHJ5IjoiR0UiLCJhY2NvdW50IjoiYWNjdC00MiJ9',
        'payment_systems' => 'opb',
        'payment_method' => 'x',
    ]);
});

it('writes reservation data as Flitt accepted it on the test merchant', function () {
    $vector = Vectors::dataset('request_accepted')['reservation_data base64 signed as plain string'][0];

    expect(new ReservationData(customerCountry: 'GE', account: 'acct-42')->toParameter())
        ->toBe($vector['params']['reservation_data']);
});

it('returns a new builder from every option and leaves the first one as it was', function () {
    $plain = Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition');
    $withPreauth = $plain->preauth();

    expect($plain)->not->toBe($withPreauth)
        ->and($plain->parameters())->not->toHaveKey('preauth')
        ->and($withPreauth->parameters())->toHaveKey('preauth', true);
});

it('creates a checkout URL with a signed request, and reads Flitt\'s unsigned answer', function () {
    $mockClient = MockClient::global([CheckoutUrlRequest::class => MockResponse::make(['response' => [
        'checkout_url' => 'https://pay.flitt.com/merchants/6aa7/default/index.html?token=2b6a',
        'payment_id' => '1029157899',
        'response_status' => 'success',
    ]])]);

    $checkout = Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->preauth()->url();
    $sent = $mockClient->getLastPendingRequest();

    expect($checkout)
        ->checkoutUrl->toBe('https://pay.flitt.com/merchants/6aa7/default/index.html?token=2b6a')
        ->paymentId->toBe(1_029_157_899)
        ->and($sent?->getUrl())->toBe('https://pay.flitt.com/api/checkout/url')
        ->and($sent?->body()?->all()['request'])->toMatchArray(['order_id' => 'inv-1042', 'preauth' => 'Y', 'merchant_id' => 1549901, 'version' => '1.0.1']);

    $body = $sent?->body()?->all()['request'];
    expect($body['signature'])->toBe(Signer::sign(array_diff_key($body, ['signature' => true]), 'test'));
});

it('creates a checkout token', function () {
    MockClient::global([CheckoutTokenRequest::class => MockResponse::make(['response' => [
        'token' => 'e50d21d0a8fa1c12c6bd1bd4ee9b0c1e3c1c5f0d',
        'response_status' => 'success',
    ]])]);

    expect(Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition')->token()->token)
        ->toBe('e50d21d0a8fa1c12c6bd1bd4ee9b0c1e3c1c5f0d');
});

it('treats a checkout answer without a URL or token as unreadable', function (string $call) {
    MockClient::global([
        CheckoutUrlRequest::class => MockResponse::make(['response' => ['response_status' => 'success']]),
        CheckoutTokenRequest::class => MockResponse::make(['response' => ['response_status' => 'success']]),
    ]);

    $checkout = Flitt::checkout('inv-1042', 12_500, Currency::GEL, 'Tuition');

    $call === 'url' ? $checkout->url() : $checkout->token();
})->with(['url', 'token'])->throws(ConnectionFailedException::class, 'could not be read');

it('refuses a "|" in any checkout value before anything is sent', function (string $option) {
    $mockClient = MockClient::global([CheckoutUrlRequest::class => MockResponse::make(['response' => []])]);
    $checkout = Flitt::checkout('inv-1042', 12_500, Currency::GEL, $option === 'orderDesc' ? 'Tuition | November' : 'Tuition');

    $checkout = match ($option) {
        'orderDesc' => $checkout,
        'merchantData' => $checkout->merchantData('a|b'),
        'responseUrl' => $checkout->responseUrl('https://school.example.com/return?x=a|b'),
    };

    expect(fn () => $checkout->url())->toThrow(InvalidParameterException::class);
    $mockClient->assertNothingSent();
})->with(['orderDesc', 'merchantData', 'responseUrl']);

it('refuses values Flitt would refuse', function (string $parameter) {
    $build = match ($parameter) {
        'orderId' => fn () => Flitt::checkout('', 100, Currency::GEL, 'x'),
        'orderDesc' => fn () => Flitt::checkout('inv-1', 100, Currency::GEL, ''),
        'amount' => fn () => Flitt::checkout('inv-1', -1, Currency::GEL, 'x'),
        'lifetime' => fn () => Flitt::checkout('inv-1', 100, Currency::GEL, 'x')->lifetime(0),
    };

    expect($build)->toThrow(InvalidParameterException::class, $parameter);
})->with(['orderId', 'orderDesc', 'amount', 'lifetime']);

it('reads an order\'s status and checks its signature', function () {
    $answer = Vectors::dataset('response')['status of created order'][0]['response'];
    MockClient::global([OrderStatusRequest::class => MockResponse::make(['response' => $answer])]);

    $order = Flitt::status('lf-research-1794132508116-url1');

    expect($order)
        ->orderId->toBe('lf-research-1794132508116-url1')
        ->orderStatus->toBe(OrderStatus::CREATED)
        ->amount->toBe(125)
        ->paymentId->toBe(1_029_157_899);
});

it('passes Flitt\'s "order not found" on as ApiErrorException', function () {
    MockClient::global([OrderStatusRequest::class => MockResponse::make(['response' => [
        'error_code' => 1018, 'error_message' => 'Order not found', 'request_id' => 'x', 'response_status' => 'failure',
    ]])]);

    Flitt::status('inv-unknown');
})->throws(ApiErrorException::class, 'error 1018');

it('uses per-call credentials for checkout and status', function () {
    $mockClient = MockClient::global([OrderStatusRequest::class => MockResponse::make(['response' => [
        'error_code' => 1018, 'error_message' => 'Order not found',
    ]])]);

    try {
        Flitt::withCredentials(new Credentials(1396424, 'school-key'))->status('inv-1042');
    } catch (ApiErrorException) {
    }

    expect($mockClient->getLastPendingRequest()?->body()?->all()['request']['merchant_id'])->toBe(1396424);
});
