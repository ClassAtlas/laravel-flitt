<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Enums\CaptureStatus;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\TranType;
use ClassAtlas\Flitt\Tests\Support\ApprovedOrders;
use ClassAtlas\Flitt\Tests\Support\Live;

it('gets an approved, signed order from the test merchant without a browser', function () {
    $order = ApprovedOrders::create('approved', amount: 1000, requiredRectoken: true);

    expect($order)
        ->orderId->toBe(Live::orderId('approved'))
        ->merchantId->toBe(Live::MERCHANT_ID)
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->tranType->toBe(TranType::PURCHASE)
        ->amount->toBe(1000)
        ->currency->toBe(Currency::GEL)
        ->maskedCard->toBe('444455XXXXXX6666')
        ->rectoken->not->toBeNull()
        ->and($order->additionalInfo->transactionId)->toBeInt()
        ->and($order->additionalInfo->isTest)->toBeTrue();
})->group('live');

it('gets a held order, to capture or reverse later', function () {
    $order = ApprovedOrders::create('preauth', amount: 1000, preauth: true);

    expect($order)
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->and($order->additionalInfo->captureStatus)->toBe(CaptureStatus::HOLD);
})->group('live');

it('reads a new order\'s time as Europe/Kyiv time, so a time-zone shift at Flitt fails loudly', function () {
    $order = ApprovedOrders::create('order-time', amount: 125);

    expect($order->orderTime)->toBeInstanceOf(CarbonImmutable::class)
        ->and(abs(CarbonImmutable::now()->diffInSeconds($order->orderTime)))->toBeLessThan(300);
})->group('live')->skip(fn (): bool => ! Live::reachesFlitt(), 'Only meaningful against Flitt itself.');
