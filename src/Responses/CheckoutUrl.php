<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Responses;

use ClassAtlas\Flitt\Protocol\Value;

/**
 * Where to send the payer: Flitt's payment page for the order.
 */
final readonly class CheckoutUrl
{
    /**
     * @internal Only the package creates response objects.
     *
     * @param  array<string, mixed>  $raw  everything Flitt sent
     */
    public function __construct(
        public string $checkoutUrl,
        public ?int $paymentId,
        public array $raw,
    ) {}

    /**
     * @internal
     *
     * @param  array<string, mixed>  $fields
     */
    public static function fromFields(array $fields): self
    {
        return new self(
            checkoutUrl: Value::string($fields['checkout_url'] ?? null) ?? '',
            paymentId: Value::integer($fields['payment_id'] ?? null),
            raw: $fields,
        );
    }
}
