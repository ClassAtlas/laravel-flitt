<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use BackedEnum;
use ClassAtlas\Flitt\Requests\FlittRequest;
use ClassAtlas\Flitt\Signing\SigningKey;
use Saloon\Http\Response;

/**
 * A request with nothing of its own, to test what every Flitt call shares.
 */
final class ProbeRequest extends FlittRequest
{
    /**
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters
     */
    public function __construct(
        private readonly array $parameters = ['order_id' => 'inv-1042'],
        private readonly SigningKey $key = SigningKey::PAYMENT,
        private readonly bool $signed = true,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/status/order_id';
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function operation(): string
    {
        return 'probe';
    }

    public function signingKey(): SigningKey
    {
        return $this->key;
    }

    public function answerIsSigned(): bool
    {
        return $this->signed;
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(Response $response): array
    {
        return $this->answerFields($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(Response $response): array
    {
        return $this->answerRows($response);
    }
}
