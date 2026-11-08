<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Requests;

use BackedEnum;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Responses\CheckoutUrl;
use Saloon\Http\Response;

/**
 * Creates an order and returns the URL of Flitt's payment page (/api/checkout/url). Flitt does not sign this answer.
 */
final class CheckoutUrlRequest extends FlittRequest
{
    /**
     * @internal Build it with Flitt::checkout().
     *
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters
     */
    public function __construct(private readonly array $parameters) {}

    public function resolveEndpoint(): string
    {
        return '/api/checkout/url';
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function operation(): string
    {
        return 'checkout URL';
    }

    public function answerIsSigned(): bool
    {
        return false;
    }

    public function createDtoFromResponse(Response $response): CheckoutUrl
    {
        $checkoutUrl = CheckoutUrl::fromFields($this->answerFields($response));

        return $checkoutUrl->checkoutUrl !== '' ? $checkoutUrl : throw ConnectionFailedException::unreadable($this->operation());
    }
}
