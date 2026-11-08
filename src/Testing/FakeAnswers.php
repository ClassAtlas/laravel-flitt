<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Testing;

use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Protocol\Value;
use ClassAtlas\Flitt\Requests\CheckoutTokenRequest;
use ClassAtlas\Flitt\Requests\CheckoutUrlRequest;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\Requests\RecurringRequest;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\Verifier;
use Closure;
use LogicException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * @internal The MockClient behind Flitt::fake(): a default answer for every operation, replaced by the answers a test
 * prepares. An order is filled from what was sent and signed with the key the call used, as Flitt would sign it.
 */
final class FakeAnswers
{
    /**
     * @param  array<class-string, FakeOrder|MockResponse|callable>  $prepared
     */
    public static function mockClient(array $prepared): MockClient
    {
        $answers = [
            CheckoutUrlRequest::class => self::checkoutUrl(...),
            CheckoutTokenRequest::class => self::checkoutToken(...),
            OrderStatusRequest::class => FakeOrder::approved(),
            RecurringRequest::class => FakeOrder::approved(),
            ...$prepared,
        ];

        return new MockClient(array_map(
            static fn (FakeOrder|MockResponse|callable $answer): MockResponse|callable => $answer instanceof FakeOrder
                ? self::signedOrder($answer)
                : $answer,
            $answers,
        ));
    }

    /**
     * What a request sent, under Flitt's names, without the signature.
     *
     * @return array<string, mixed>
     */
    public static function sent(PendingRequest $pendingRequest): array
    {
        $body = $pendingRequest->body()?->all();
        $sent = is_array($body) && is_array($body['request'] ?? null) ? $body['request'] : [];

        $parameters = [];

        foreach ($sent as $name => $value) {
            if ($name !== 'signature') {
                $parameters[(string) $name] = $value;
            }
        }

        return $parameters;
    }

    private static function signedOrder(FakeOrder $order): Closure
    {
        return static function (PendingRequest $pendingRequest) use ($order): MockResponse {
            $connector = $pendingRequest->getConnector();

            if (! $connector instanceof FlittConnector) {
                throw new LogicException('Flitt::fake() answers requests sent through FlittConnector only.');
            }

            $fields = $order->fields(self::sent($pendingRequest));
            $fields['signature'] = Signer::sign($fields, Verifier::keyFor($fields, $connector->credentials()));

            return MockResponse::make(['response' => $fields]);
        };
    }

    private static function checkoutUrl(PendingRequest $pendingRequest): MockResponse
    {
        $orderId = Value::string(self::sent($pendingRequest)['order_id'] ?? null) ?? '';

        return MockResponse::make(['response' => [
            'checkout_url' => 'https://pay.flitt.com/merchants/fake/default/index.html?token='.sha1('url|'.$orderId),
            'payment_id' => (string) abs(crc32('payment|'.$orderId)),
            'response_status' => 'success',
        ]]);
    }

    private static function checkoutToken(PendingRequest $pendingRequest): MockResponse
    {
        $orderId = Value::string(self::sent($pendingRequest)['order_id'] ?? null) ?? '';

        return MockResponse::make(['response' => [
            'token' => sha1('token|'.$orderId),
            'response_status' => 'success',
        ]]);
    }
}
