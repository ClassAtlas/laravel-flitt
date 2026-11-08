<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

/**
 * A call has no credentials to use, or needs the credit key and the credentials have none.
 */
final class MissingCredentialsException extends FlittException
{
    /**
     * @internal
     */
    public static function none(): self
    {
        return new self(
            'No Flitt credentials: pass them with Flitt::withCredentials(), '
            .'or set FLITT_MERCHANT_ID and FLITT_PAYMENT_KEY for a single merchant.',
        );
    }

    /**
     * @internal
     */
    public static function noCreditKey(int $merchantId, string $subject): self
    {
        return new self(sprintf(
            '%s is signed with the credit key, and the credentials for Flitt merchant %d have none. '
            .'Set FLITT_CREDIT_KEY, or pass creditKey in the credentials.',
            ucfirst($subject),
            $merchantId,
        ));
    }
}
