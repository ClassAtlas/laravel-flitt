<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Requests;

use BackedEnum;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Responses\Order;
use Saloon\Http\Response;

/**
 * Charges a saved card with its token (/api/recurring). The answer is a signed, final order.
 */
final class RecurringRequest extends FlittRequest
{
    /**
     * @internal Build it with Flitt::recurring().
     *
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters
     */
    public function __construct(private readonly array $parameters) {}

    public function resolveEndpoint(): string
    {
        return '/api/recurring';
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function operation(): string
    {
        return 'recurring';
    }

    public function createDtoFromResponse(Response $response): Order
    {
        $order = Order::fromFields($this->answerFields($response));

        return $order->orderId !== '' ? $order : throw ConnectionFailedException::unreadable($this->operation());
    }
}
