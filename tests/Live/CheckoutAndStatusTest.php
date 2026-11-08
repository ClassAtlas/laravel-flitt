<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\Lang;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\PaymentMethod;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Exceptions\ApiErrorException;
use ClassAtlas\Flitt\Requests\CheckoutTokenRequest;
use ClassAtlas\Flitt\Requests\CheckoutUrlRequest;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\ReservationData;
use ClassAtlas\Flitt\Tests\Support\ApprovedOrders;
use ClassAtlas\Flitt\Tests\Support\Live;

it('creates a checkout URL on the test merchant, and reads the new order\'s status', function () {
    Live::answer([CheckoutUrlRequest::class => 'checkout/url']);
    $orderId = Live::orderId('checkout-url');

    $checkout = Live::client()->checkout($orderId, 125, Currency::GEL, 'Live run')
        ->serverCallbackUrl('https://example.com/flitt/callback')
        ->responseUrl('https://example.com/flitt/return')
        ->url();

    expect($checkout->checkoutUrl)->toStartWith('https://pay.flitt.com/merchants/')
        ->and($checkout->paymentId)->toBeInt();

    Live::answer([OrderStatusRequest::class => 'status/created']);

    expect(Live::client()->status($orderId))
        ->orderId->toBe($orderId)
        ->orderStatus->toBe(OrderStatus::CREATED)
        ->amount->toBe(125)
        ->currency->toBe(Currency::GEL)
        ->paymentId->toBe($checkout->paymentId);
})->group('live');

it('creates a checkout token on the test merchant', function () {
    Live::answer([CheckoutTokenRequest::class => 'checkout/token']);

    $token = Live::client()->checkout(Live::orderId('checkout-token'), 125, Currency::GEL, 'Live run')->token();

    expect($token->token)->toMatch('/^[0-9a-f]{40}$/');
})->group('live');

it('creates a checkout with every option, and Flitt keeps them on the order', function () {
    Live::answer([CheckoutUrlRequest::class => 'checkout/url-options']);
    $orderId = Live::orderId('checkout-options');

    Live::client()->checkout($orderId, 1000, Currency::GEL, 'Live run, every option')
        ->serverCallbackUrl('https://example.com/flitt/callback')
        ->responseUrl('https://example.com/flitt/return')
        ->cancelUrl('https://example.com/flitt/cancel')
        ->lifetime(600)
        ->merchantData('{"invoice":"live-42"}')
        ->preauth()
        ->delayed(false)
        ->requiredRectoken()
        ->senderEmail('payer@example.com')
        ->lang(Lang::KA)
        ->productId('tuition')
        ->reservationData(new ReservationData(customerCountry: 'GE', account: 'acct-42'))
        ->url();

    Live::answer([OrderStatusRequest::class => 'status/created-with-options']);

    expect(Live::client()->status($orderId))
        ->orderStatus->toBe(OrderStatus::CREATED)
        ->merchantData->toBe('{"invoice":"live-42"}')
        ->productId->toBe('tuition');
})->group('live');

it('creates open-banking and installment checkouts with Flitt\'s demo bank', function (PaymentSystem $paymentSystem, string $name) {
    Live::answer([CheckoutUrlRequest::class => "checkout/url-{$name}"]);

    $checkout = Live::client()->checkout(Live::orderId("checkout-{$name}"), 10_000, Currency::GEL, 'Live run')
        ->paymentSystems($paymentSystem)
        ->paymentMethod(PaymentMethod::DEMO)
        ->url();

    expect($checkout->checkoutUrl)->toStartWith('https://pay.flitt.com/');
})->with([
    [PaymentSystem::OPB, 'opb'],
    [PaymentSystem::INSTALLMENTS, 'installments'],
])->group('live');

it('reads an approved order\'s status, signed by Flitt', function () {
    $approved = ApprovedOrders::create('status-approved', amount: 1000);
    Live::answer([OrderStatusRequest::class => 'status/approved']);

    expect(Live::client()->status($approved->orderId))
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->actualAmount->toBe(1000)
        ->maskedCard->toBe('444455XXXXXX6666')
        ->and($approved->paymentId)->not->toBeNull();
})->group('live');

it('answers 1018 for an order Flitt does not know', function () {
    Live::answer([OrderStatusRequest::class => 'status/unknown']);

    Live::client()->status(Live::orderId('status-unknown'));
})->group('live')->throws(ApiErrorException::class, 'error 1018');
