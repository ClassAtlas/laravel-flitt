<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Testing;

use ClassAtlas\Flitt\Signing\Signer;
use SensitiveParameter;

/**
 * A correctly signed callback or browser return, to post to your own routes in tests:
 * postJson() for a callback, post() for a browser return.
 */
final readonly class FakeCallback
{
    private function __construct(private FakeOrder $order) {}

    public static function for(FakeOrder $order): self
    {
        return new self($order);
    }

    /**
     * The body, signed with this key: the payment key, or the credit key for a payout order.
     *
     * @return array<string, string|int>
     */
    public function signedWith(#[SensitiveParameter] string $key): array
    {
        $fields = $this->order->fields();

        return [...$fields, 'signature' => Signer::sign($fields, $key)];
    }
}
