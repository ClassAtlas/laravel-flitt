<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Flitt;
use ClassAtlas\Flitt\FlittConnector;
use LogicException;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\MockConfig;

/**
 * How the package's tests reach Flitt, in three modes:
 *
 * - fixtures (the default): every answer comes from tests/Fixtures/Saloon, and a missing fixture throws;
 * - live (FLITT_LIVE=1): every call goes to Flitt's public test merchant, and nothing is written;
 * - record (FLITT_RECORD=1): the same calls, and each answer is scrubbed, signed again and saved as its fixture.
 *
 * Live and record refuse any merchant but the public test merchant, whose credentials are public and hard-coded.
 */
final class Live
{
    public const int MERCHANT_ID = 1549901;

    public const string PAYMENT_KEY = 'test';

    public const string CREDIT_KEY = 'testcredit';

    public const string FIXTURES = __DIR__.'/../Fixtures/Saloon';

    /**
     * The order ids sent in this run, by the deterministic id each fixture holds instead.
     *
     * @var array<string, string>
     */
    private static array $orderIds = [];

    private static ?string $run = null;

    public static function mode(): string
    {
        return match (true) {
            self::enabled('FLITT_RECORD') => 'record',
            self::enabled('FLITT_LIVE') => 'live',
            default => 'fixtures',
        };
    }

    public static function reachesFlitt(): bool
    {
        return self::mode() !== 'fixtures';
    }

    public static function credentials(): Credentials
    {
        return new Credentials(self::MERCHANT_ID, self::PAYMENT_KEY, self::CREDIT_KEY);
    }

    /**
     * The client for the test merchant.
     */
    public static function client(): Flitt
    {
        return app(Flitt::class)->withCredentials(self::credentials());
    }

    /**
     * An order id for one test. Fixtures hold a fixed id; a run against Flitt needs a new one each time, because Flitt
     * refuses an order id it has seen with other parameters. Recording writes the fixed id back into the fixture.
     */
    public static function orderId(string $name): string
    {
        $fixed = 'lf-fixture-'.$name;

        if (! self::reachesFlitt()) {
            return $fixed;
        }

        self::$run ??= date('ymdHis').'-'.bin2hex(random_bytes(2));
        $unique = "lf-live-{$name}-".self::$run;
        self::$orderIds[$unique] = $fixed;

        return $unique;
    }

    /**
     * @return array<string, string> the ids sent in this run, mapped to the ids their fixtures hold
     */
    public static function orderIds(): array
    {
        return self::$orderIds;
    }

    /**
     * Answers each request class from its fixture, or sends it to Flitt in live mode. A later call for the same class
     * replaces its fixture.
     *
     * @param  array<class-string, string>  $fixtures  request class => fixture name
     */
    public static function answer(array $fixtures): void
    {
        if (self::mode() === 'live') {
            return;
        }

        MockClient::global()->addResponses(array_map(static fn (string $name): FlittFixture => new FlittFixture($name), $fixtures));
    }

    /**
     * Run before each test.
     */
    public static function setUp(): void
    {
        MockConfig::setFixturePath(self::FIXTURES);

        if (self::reachesFlitt()) {
            Config::allowStrayRequests();
            Config::globalMiddleware()->onRequest(self::onlyTheTestMerchant(...), 'only-the-test-merchant');

            return;
        }

        MockConfig::throwOnMissingFixtures();
        Config::preventStrayRequests();
    }

    /**
     * Run after each test.
     */
    public static function tearDown(): void
    {
        MockClient::destroyGlobal();
        Config::clearGlobalMiddleware();
    }

    private static function onlyTheTestMerchant(PendingRequest $pendingRequest): void
    {
        $connector = $pendingRequest->getConnector();

        if ($connector instanceof FlittConnector && $connector->credentials()->merchantId !== self::MERCHANT_ID) {
            throw new LogicException('Live and record runs only call Flitt\'s public test merchant '.self::MERCHANT_ID.'.');
        }
    }

    private static function enabled(string $variable): bool
    {
        return in_array(getenv($variable), ['1', 'true'], true);
    }
}
