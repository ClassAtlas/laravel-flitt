<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Requests\RecurringRequest;
use ClassAtlas\Flitt\Tests\Support\ApprovedOrders;
use ClassAtlas\Flitt\Tests\Support\Live;

it('saves a card token on a first payment, then charges the saved card', function () {
    $first = ApprovedOrders::create('recurring-first', amount: 1000, requiredRectoken: true);

    expect($first->rectoken)->not->toBeNull();

    Live::answer([RecurringRequest::class => 'recurring/approved']);
    $orderId = Live::orderId('recurring-charge');

    $charge = Live::client()->recurring($orderId, 300, Currency::GEL, 'Live run, recurring', (string) $first->rectoken)
        ->serverCallbackUrl('https://example.com/flitt/callback')
        ->charge();

    expect($charge)
        ->orderId->toBe($orderId)
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->amount->toBe(300)
        ->rectoken->toBe($first->rectoken)
        ->maskedCard->toBe($first->maskedCard);
})->group('live');
