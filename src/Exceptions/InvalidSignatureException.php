<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

/**
 * An answer or callback whose signature is missing or does not match: it may not come from Flitt, or the key is wrong.
 */
final class InvalidSignatureException extends FlittException
{
    /**
     * @internal
     */
    public static function missing(string $source): self
    {
        return new self(sprintf('%s carries no signature, so it cannot be trusted.', ucfirst($source)));
    }

    /**
     * @internal
     */
    public static function mismatch(string $source): self
    {
        return new self(sprintf(
            '%s has a signature that does not match: it was not signed by Flitt with this merchant\'s key.',
            ucfirst($source),
        ));
    }
}
