<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

/**
 * A callback or browser return for a merchant the bound CredentialsResolver does not know.
 */
final class UnknownMerchantException extends FlittException
{
    /**
     * @internal Only the package throws this exception.
     */
    public function __construct(public readonly int $merchantId)
    {
        parent::__construct(sprintf('No credentials are known for Flitt merchant %d.', $merchantId));
    }
}
