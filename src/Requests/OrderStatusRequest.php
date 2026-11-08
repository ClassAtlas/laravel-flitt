<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Requests;

use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Responses\Order;
use Saloon\Http\Response;

/**
 * Reads an order (/api/status/order_id). The "order_id" in the path is literal: the real id travels in the body.
 */
final class OrderStatusRequest extends FlittRequest
{
    /**
     * @internal Use Flitt::status().
     */
    public function __construct(private readonly string $orderId) {}

    public function resolveEndpoint(): string
    {
        return '/api/status/order_id';
    }

    public function parameters(): array
    {
        return ['order_id' => $this->orderId];
    }

    public function operation(): string
    {
        return 'status';
    }

    public function createDtoFromResponse(Response $response): Order
    {
        $order = Order::fromFields($this->answerFields($response));

        return $order->orderId !== '' ? $order : throw ConnectionFailedException::unreadable($this->operation());
    }
}
