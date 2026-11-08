<?php

declare(strict_types=1);

use ClassAtlas\Flitt\ConfigCredentialsResolver;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Facades\Flitt;
use ClassAtlas\Flitt\Flitt as FlittClient;

function configureMerchant(mixed $merchantId = '1549901', mixed $paymentKey = 'test', mixed $creditKey = 'testcredit'): void
{
    config([
        'flitt.merchant_id' => $merchantId,
        'flitt.payment_key' => $paymentKey,
        'flitt.credit_key' => $creditKey,
    ]);
}

it('knows the configured merchant', function () {
    configureMerchant();

    expect(app(ConfigCredentialsResolver::class)->forMerchant(1549901))
        ->merchantId->toBe(1549901)
        ->paymentKey()->toBe('test')
        ->creditKey()->toBe('testcredit');
});

it('knows no other merchant', function () {
    configureMerchant();

    expect(app(ConfigCredentialsResolver::class)->forMerchant(1396424))->toBeNull();
});

it('treats an incomplete or invalid config as no merchant', function (mixed $merchantId, mixed $paymentKey) {
    configureMerchant($merchantId, $paymentKey);

    expect(app(ConfigCredentialsResolver::class)->configured())->toBeNull();
})->with([
    'no merchant id' => [null, 'test'],
    'no payment key' => ['1549901', null],
    'an empty payment key' => ['1549901', ''],
    'a merchant id that is not a number' => ['merchant', 'test'],
    'a merchant id below 1' => ['0', 'test'],
]);

it('leaves the credit key out when it is not set', function (mixed $creditKey) {
    configureMerchant(creditKey: $creditKey);

    expect(app(ConfigCredentialsResolver::class)->configured()?->creditKey())->toBeNull();
})->with([null, '']);

it('uses the configured merchant for calls without credentials of their own', function () {
    configureMerchant();

    expect(app(FlittClient::class)->credentials())
        ->merchantId->toBe(1549901)
        ->paymentKey()->toBe('test');
});

it('uses per-call credentials on the client they were given to, and only there', function () {
    configureMerchant();
    $school = new Credentials(1396424, 'school-payment-key');

    $client = Flitt::withCredentials($school);

    expect($client->credentials())->toBe($school)
        ->and(app(FlittClient::class)->credentials()?->merchantId)->toBe(1549901);
});
