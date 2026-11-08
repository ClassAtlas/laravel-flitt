<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Facades;

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Flitt as FlittClient;
use ClassAtlas\Flitt\Testing\FakeAnswers;
use ClassAtlas\Flitt\Testing\FakeOrder;
use Closure;
use Illuminate\Support\Facades\Facade;
use LogicException;
use PHPUnit\Framework\Assert;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

/**
 * @method static \ClassAtlas\Flitt\Builders\Checkout checkout(string $orderId, int $amount, \ClassAtlas\Flitt\Enums\Currency $currency, string $orderDesc)
 * @method static \ClassAtlas\Flitt\Builders\Recurring recurring(string $orderId, int $amount, \ClassAtlas\Flitt\Enums\Currency $currency, string $orderDesc, string $rectoken)
 * @method static \ClassAtlas\Flitt\Responses\Order status(string $orderId)
 * @method static \ClassAtlas\Flitt\Flitt withCredentials(Credentials $credentials)
 *
 * @see FlittClient
 */
final class Flitt extends Facade
{
    /**
     * Stops every Flitt call from leaving the app. Each operation answers with a sensible default (an approved order, a
     * checkout URL and so on); prepare an answer per request class when you need a particular one. Orders are signed
     * with the key the call used, as Flitt would sign them.
     *
     * @param  array<class-string, FakeOrder|MockResponse|callable>  $responses
     */
    public static function fake(array $responses = []): FlittClient
    {
        $app = self::getFacadeApplication() ?? throw new LogicException('Flitt::fake() needs a Laravel application.');
        $fake = $app->make(FlittClient::class)->faked(FakeAnswers::mockClient($responses));

        // Bound again, so the fake survives a reset of scoped instances (the queue worker, Octane).
        $app->scoped(FlittClient::class, static fn (): FlittClient => $fake);
        self::swap($fake);

        return $fake;
    }

    /**
     * Asserts that a request of this class was sent, and that $callback accepts its parameters (Flitt's names).
     *
     * @param  class-string  $request
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public static function assertSent(string $request, ?Closure $callback = null): void
    {
        Assert::assertNotEmpty(
            self::sentParameters($request, $callback),
            $callback === null
                ? "No {$request} was sent to Flitt."
                : "No {$request} was sent to Flitt with parameters that match.",
        );
    }

    /**
     * Asserts that no request of this class (whose parameters $callback accepts) was sent.
     *
     * @param  class-string  $request
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public static function assertNotSent(string $request, ?Closure $callback = null): void
    {
        Assert::assertEmpty(self::sentParameters($request, $callback), "An unexpected {$request} was sent to Flitt.");
    }

    /**
     * Asserts that nothing was sent to Flitt at all.
     */
    public static function assertNothingSent(): void
    {
        Assert::assertEmpty(self::recorded(), 'Calls were sent to Flitt.');
    }

    protected static function getFacadeAccessor(): string
    {
        return FlittClient::class;
    }

    /**
     * @param  class-string  $request
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     * @return list<array<string, mixed>>
     */
    private static function sentParameters(string $request, ?Closure $callback): array
    {
        $matches = [];

        foreach (self::recorded() as $response) {
            $parameters = FakeAnswers::sent($response->getPendingRequest());

            if ($response->getRequest() instanceof $request && ($callback === null || $callback($parameters))) {
                $matches[] = $parameters;
            }
        }

        return $matches;
    }

    /**
     * @return array<Response>
     */
    private static function recorded(): array
    {
        $root = self::getFacadeRoot();
        $mockClient = $root instanceof FlittClient ? $root->mockClient() : null;

        return $mockClient?->getRecordedResponses() ?? throw new LogicException('Call Flitt::fake() before asserting on Flitt calls.');
    }
}
