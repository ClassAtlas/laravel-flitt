<?php

declare(strict_types=1);

use ClassAtlas\Flitt\ConfigCredentialsResolver;
use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Facades\Flitt as FlittFacade;
use ClassAtlas\Flitt\Flitt;
use ClassAtlas\Flitt\FlittServiceProvider;
use Illuminate\Support\ServiceProvider;

it('loads the default config', function () {
    expect(config('flitt'))->toBe([
        'merchant_id' => null,
        'payment_key' => null,
        'credit_key' => null,
        'timeout' => 10,
        'connect_timeout' => 3,
        'log_requests' => false,
        'log_channel' => null,
    ]);
});

it('reads each setting from its FLITT_ variable', function (string $variable, string $value, string $key, mixed $expected) {
    putenv("{$variable}={$value}");

    try {
        $config = require __DIR__.'/../config/flitt.php';
    } finally {
        putenv($variable);
    }

    expect($config[$key])->toBe($expected);
})->with([
    ['FLITT_MERCHANT_ID', '1549901', 'merchant_id', '1549901'],
    ['FLITT_PAYMENT_KEY', 'test', 'payment_key', 'test'],
    ['FLITT_CREDIT_KEY', 'testcredit', 'credit_key', 'testcredit'],
    ['FLITT_TIMEOUT', '25', 'timeout', 25],
    ['FLITT_CONNECT_TIMEOUT', '5', 'connect_timeout', 5],
    ['FLITT_LOG_REQUESTS', 'true', 'log_requests', true],
    ['FLITT_LOG_CHANNEL', 'payments', 'log_channel', 'payments'],
]);

it('publishes the config file under the flitt-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(FlittServiceProvider::class, 'flitt-config');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(config_path('flitt.php'));
});

it('resolves callback credentials from config by default', function () {
    expect(app(CredentialsResolver::class))->toBeInstanceOf(ConfigCredentialsResolver::class);
});

it('lets the app bind its own resolver', function () {
    $resolver = new class implements CredentialsResolver
    {
        public function forMerchant(int $merchantId): ?Credentials
        {
            return null;
        }
    };

    app()->instance(CredentialsResolver::class, $resolver);

    expect(app(CredentialsResolver::class))->toBe($resolver);
});

it('serves the client through the container and the facade', function () {
    expect(app(Flitt::class))
        ->toBeInstanceOf(Flitt::class)
        ->toBe(FlittFacade::getFacadeRoot());
});
