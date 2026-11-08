<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Requests\FlittRequest;
use ClassAtlas\Flitt\Responses\Order;
use Saloon\Http\Response;

/**
 * Test only, never shipped: pays an order directly with Flitt's no-3-D-Secure test card, so the live run gets an
 * approved order without a browser. The package itself never handles card data.
 */
final class Step1Request extends FlittRequest
{
    public const string TEST_CARD = '4444555511116666';

    public function __construct(
        private readonly string $orderId,
        private readonly int $amount,
        private readonly bool $preauth = false,
        private readonly bool $requiredRectoken = false,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/3dsecure_step1';
    }

    public function parameters(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_desc' => 'Live run',
            'amount' => $this->amount,
            'currency' => 'GEL',
            'card_number' => self::TEST_CARD,
            'cvv2' => '123',
            'expiry_date' => '1230',
            'preauth' => $this->preauth,
            'required_rectoken' => $this->requiredRectoken,
        ];
    }

    public function operation(): string
    {
        return '3dsecure_step1';
    }

    public function createDtoFromResponse(Response $response): Order
    {
        return Order::fromFields($this->answerFields($response));
    }
}
