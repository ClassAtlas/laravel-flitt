<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Signing;

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use InvalidArgumentException;

/**
 * @internal Checks what Flitt signed, strictly. The key follows the order's type, not the request that fetched it:
 * Flitt signs a payout order with the credit key, even in an answer to a call signed with the payment key.
 */
final class Verifier
{
    /**
     * Fields Flitt sends beside the signed ones. response_signature_string is Flitt's own debugging aid and is never
     * trusted.
     */
    private const array UNSIGNED_FIELDS = ['signature', 'response_signature_string'];

    /**
     * A protocol 1.0.1 body: every field but the signature itself is signed.
     *
     * @param  array<string, mixed>  $fields
     *
     * @throws InvalidSignatureException
     * @throws MissingCredentialsException
     */
    public static function flat(array $fields, Credentials $credentials, string $source): void
    {
        $signature = self::signatureIn($fields, $source);
        $signed = array_diff_key($fields, array_flip(self::UNSIGNED_FIELDS));

        try {
            $expected = Signer::sign($signed, self::keyFor($fields, $credentials));
        } catch (InvalidArgumentException) {
            throw InvalidSignatureException::mismatch($source);
        }

        if (! hash_equals($expected, $signature)) {
            throw InvalidSignatureException::mismatch($source);
        }
    }

    /**
     * A protocol 2.0 envelope: the base64 data string is signed as it is.
     *
     * @param  array<string, mixed>  $envelope
     * @param  array<string, mixed>  $order  the order inside the data, which decides the key
     *
     * @throws InvalidSignatureException
     * @throws MissingCredentialsException
     */
    public static function envelope(array $envelope, string $data, array $order, Credentials $credentials, string $source): void
    {
        $signature = self::signatureIn($envelope, $source);

        if (! hash_equals(Signer::signData($data, self::keyFor($order, $credentials)), $signature)) {
            throw InvalidSignatureException::mismatch($source);
        }
    }

    /**
     * @param  array<string, mixed>  $order
     *
     * @throws MissingCredentialsException
     */
    public static function keyFor(array $order, Credentials $credentials): string
    {
        if (($order['tran_type'] ?? null) !== 'p2p credit') {
            return $credentials->paymentKey();
        }

        return $credentials->creditKey()
            ?? throw MissingCredentialsException::noCreditKey($credentials->merchantId, 'a payout order (tran_type "p2p credit")');
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws InvalidSignatureException
     */
    private static function signatureIn(array $fields, string $source): string
    {
        $signature = $fields['signature'] ?? null;

        if (! is_string($signature) || $signature === '') {
            throw InvalidSignatureException::missing($source);
        }

        return $signature;
    }
}
