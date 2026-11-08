<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Responses;

use ClassAtlas\Flitt\Protocol\Value;

/**
 * A payment token for Flitt's embedded checkout or mobile SDKs.
 */
final readonly class CheckoutToken
{
    /**
     * @internal Only the package creates response objects.
     *
     * @param  array<string, mixed>  $raw  everything Flitt sent
     */
    public function __construct(
        public string $token,
        public array $raw,
    ) {}

    /**
     * @internal
     *
     * @param  array<string, mixed>  $fields
     */
    public static function fromFields(array $fields): self
    {
        return new self(token: Value::string($fields['token'] ?? null) ?? '', raw: $fields);
    }
}
