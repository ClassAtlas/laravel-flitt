<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

/**
 * A value Flitt cannot accept, found before anything is sent. The message names the parameter, never its value.
 */
final class InvalidParameterException extends FlittException
{
    /**
     * @internal
     */
    public static function containsPipe(string $parameter): self
    {
        return new self(sprintf(
            'The value of %s contains "|", which Flitt cannot sign. Remove it before sending.',
            $parameter,
        ));
    }

    /**
     * @internal
     */
    public static function invalid(string $parameter, string $requirement): self
    {
        return new self(sprintf('%s %s.', $parameter, $requirement));
    }
}
