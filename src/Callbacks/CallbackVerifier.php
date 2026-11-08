<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Callbacks;

use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Exceptions\UnknownMerchantException;
use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Protocol\Value;
use ClassAtlas\Flitt\Responses\Order;
use ClassAtlas\Flitt\Signing\Verifier;

/**
 * Checks a callback or browser return from Flitt and reads the order it carries.
 *
 * The bound CredentialsResolver supplies the key for the merchant_id in the body (inside a 2.0 envelope, the order's).
 * A payout order is checked with the credit key, everything else with the payment key. Use it to process a body you
 * stored earlier; in a controller, type-hint CallbackRequest or BrowserReturnRequest instead.
 */
final readonly class CallbackVerifier
{
    private const string SOURCE = 'the callback';

    public function __construct(private CredentialsResolver $resolver) {}

    /**
     * @param  string  $body  the raw body, exactly as Flitt sent it
     * @param  string|null  $contentType  the body's Content-Type header: JSON or form
     *
     * @throws InvalidSignatureException when the body is unreadable, unsigned or not signed with the merchant's key
     * @throws UnknownMerchantException when the resolver does not know the merchant
     * @throws MissingCredentialsException when a payout's callback arrives and the merchant has no credit key
     */
    public function verify(string $body, ?string $contentType = null): Order
    {
        $payload = Body::parse($body, $contentType) ?? throw InvalidSignatureException::missing(self::SOURCE);

        return $this->check($payload) ?? throw InvalidSignatureException::missing(self::SOURCE);
    }

    /**
     * @internal The checked order, or null when the payload is not a readable order with a merchant id.
     *
     * @param  array<array-key, mixed>  $payload  a decoded JSON or form body
     * @param  string  $source  what the body is, in error messages: "the callback" or "the browser return"
     *
     * @throws InvalidSignatureException
     * @throws UnknownMerchantException
     * @throws MissingCredentialsException
     */
    public function check(array $payload, string $source = self::SOURCE): ?Order
    {
        $fields = Verifier::order($payload, $this->credentialsFor(...), $source);

        return $fields === null ? null : Order::fromFields($fields);
    }

    /**
     * The merchant's credentials, or null when the body names no merchant at all.
     *
     * @param  array<string, mixed>  $order
     *
     * @throws UnknownMerchantException
     */
    private function credentialsFor(array $order): ?Credentials
    {
        $merchantId = Value::integer($order['merchant_id'] ?? null);

        if ($merchantId === null || $merchantId < 1) {
            return null;
        }

        return $this->resolver->forMerchant($merchantId) ?? throw new UnknownMerchantException($merchantId);
    }
}
