<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Requests;

use BackedEnum;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Responses\CheckoutToken;
use Saloon\Http\Response;

/**
 * Creates an order and returns a payment token for Flitt's embedded checkout or mobile SDKs (/api/checkout/token).
 * Flitt does not sign this answer.
 */
final class CheckoutTokenRequest extends FlittRequest
{
    /**
     * @internal Build it with Flitt::checkout().
     *
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters
     */
    public function __construct(private readonly array $parameters) {}

    public function resolveEndpoint(): string
    {
        return '/api/checkout/token';
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function operation(): string
    {
        return 'checkout token';
    }

    public function answerIsSigned(): bool
    {
        return false;
    }

    public function createDtoFromResponse(Response $response): CheckoutToken
    {
        $token = CheckoutToken::fromFields($this->answerFields($response));

        return $token->token !== '' ? $token : throw ConnectionFailedException::unreadable($this->operation());
    }
}
