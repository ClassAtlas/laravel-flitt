<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Callbacks\CallbackVerifier;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Exceptions\UnknownMerchantException;
use ClassAtlas\Flitt\Tests\Support\Samples;

beforeEach(function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test', 'flitt.credit_key' => 'testcredit']);
});

it('verifies a stored body and returns its order', function (array $sample) {
    $order = app(CallbackVerifier::class)->verify($sample['body'], $sample['content_type']);

    expect($order->orderId)->toBe(Samples::fields($sample)['order_id'])
        ->and($order->orderStatus?->value)->toBe($sample['order_status']);
})->with(Samples::dataset());

it('throws on a body it cannot trust', function (string $body, string $exception, string $message) {
    expect(fn () => app(CallbackVerifier::class)->verify($body, 'application/json'))->toThrow($exception, $message);
})->with([
    'unreadable' => ['{"broken', InvalidSignatureException::class, 'carries no signature'],
    'no merchant' => ['{"order_id": "x"}', InvalidSignatureException::class, 'carries no signature'],
    'unsigned' => ['{"order_id": "x", "merchant_id": 1549901}', InvalidSignatureException::class, 'carries no signature'],
    'forged' => ['{"order_id": "x", "merchant_id": 1549901, "signature": "0000000000000000000000000000000000000000"}', InvalidSignatureException::class, 'does not match'],
    'another merchant' => ['{"order_id": "x", "merchant_id": 1396424, "signature": "x"}', UnknownMerchantException::class, 'merchant 1396424'],
]);

it('needs the credit key for a payout\'s callback', function () {
    config(['flitt.credit_key' => null]);
    $sample = Samples::first(static fn (array $s): bool => $s['signed_with'] === 'testcredit');

    app(CallbackVerifier::class)->verify($sample['body'], $sample['content_type']);
})->throws(MissingCredentialsException::class);
