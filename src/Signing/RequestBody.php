<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Signing;

use BackedEnum;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;

/**
 * @internal The inside of a protocol 1.0.1 request: the call's parameters, merchant_id, version and the signature.
 */
final class RequestBody
{
    public const string PROTOCOL_VERSION = '1.0.1';

    /**
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters  Flitt's parameters, by Flitt's names
     * @return array<string, string|int>
     *
     * @throws InvalidParameterException when a value contains "|", which Flitt cannot sign
     * @throws MissingCredentialsException when the call needs the credit key and the credentials have none
     */
    public static function signed(
        array $parameters,
        Credentials $credentials,
        SigningKey $signingKey,
        string $operation,
    ): array {
        $body = self::normalized($parameters);
        $body['merchant_id'] = $credentials->merchantId;
        $body['version'] = self::PROTOCOL_VERSION;
        $body['signature'] = Signer::sign($body, self::key($credentials, $signingKey, $operation));

        return $body;
    }

    /**
     * Booleans become Y and N, enums their value; "" and null are not sent at all.
     *
     * @param  array<string, string|int|bool|BackedEnum|null>  $parameters
     * @return array<string, string|int>
     */
    private static function normalized(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $name => $value) {
            $value = match (true) {
                is_bool($value) => $value ? 'Y' : 'N',
                $value instanceof BackedEnum => $value->value,
                default => $value,
            };

            if ($value === null || $value === '') {
                continue;
            }

            if (is_string($value) && str_contains($value, '|')) {
                throw InvalidParameterException::containsPipe($name);
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    private static function key(Credentials $credentials, SigningKey $signingKey, string $operation): string
    {
        if ($signingKey === SigningKey::PAYMENT) {
            return $credentials->paymentKey();
        }

        return $credentials->creditKey()
            ?? throw MissingCredentialsException::noCreditKey($credentials->merchantId, "the {$operation} call");
    }
}
