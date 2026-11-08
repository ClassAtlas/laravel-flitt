<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

function credentialsWithKeys(): Credentials
{
    return new Credentials(merchantId: 1549901, paymentKey: 'payment-secret', creditKey: 'credit-secret');
}

it('hands the keys to the code that signs with them', function () {
    expect(credentialsWithKeys())
        ->merchantId->toBe(1549901)
        ->paymentKey()->toBe('payment-secret')
        ->creditKey()->toBe('credit-secret')
        ->and(new Credentials(1549901, 'payment-secret')->creditKey())->toBeNull();
});

it('keeps the keys out of every dump', function (string $dumper) {
    $credentials = credentialsWithKeys();

    $output = match ($dumper) {
        'var_dump' => (static function () use ($credentials): string {
            ob_start();
            var_dump($credentials);

            return (string) ob_get_clean();
        })(),
        'print_r' => print_r($credentials, true),
        'var_export' => var_export($credentials, true),
        'dump() and dd()' => (string) new CliDumper()->dump(new VarCloner()->cloneVar($credentials), true),
    };

    expect($output)
        ->toContain('1549901')
        ->not->toContain('payment-secret')
        ->not->toContain('credit-secret');
})->with(['var_dump', 'print_r', 'var_export', 'dump() and dd()']);

it('keeps the keys out of JSON, and so out of log context', function () {
    expect(json_encode(credentialsWithKeys(), JSON_THROW_ON_ERROR))
        ->toBe('{"merchantId":1549901,"paymentKey":"********","creditKey":"********"}')
        ->and(json_encode(new Credentials(1549901, 'payment-secret'), JSON_THROW_ON_ERROR))
        ->toBe('{"merchantId":1549901,"paymentKey":"********","creditKey":null}');
});

it('refuses to be serialized, so the keys stay out of queues and caches', function () {
    serialize(credentialsWithKeys());
})->throws(LogicException::class, 'Pass the merchant id instead');

it('marks the keys as sensitive, so stack traces leave them out', function () {
    $parameters = new ReflectionMethod(Credentials::class, '__construct')->getParameters();

    expect($parameters[0]->getAttributes(SensitiveParameter::class))->toBeEmpty()
        ->and($parameters[1]->getAttributes(SensitiveParameter::class))->toHaveCount(1)
        ->and($parameters[2]->getAttributes(SensitiveParameter::class))->toHaveCount(1);
});

it('refuses credentials that cannot be right', function (int $merchantId, string $paymentKey, ?string $creditKey, string $parameter) {
    expect(fn () => new Credentials($merchantId, $paymentKey, $creditKey))
        ->toThrow(InvalidParameterException::class, $parameter);
})->with([
    'a merchant id of 0' => [0, 'test', null, 'merchantId'],
    'a negative merchant id' => [-1, 'test', null, 'merchantId'],
    'an empty payment key' => [1549901, '', null, 'paymentKey'],
    'an empty credit key' => [1549901, 'test', '', 'creditKey'],
]);
