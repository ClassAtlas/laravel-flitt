<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

use Throwable;

/**
 * No usable answer came back: a timeout, a network failure, an HTTP error or a body that cannot be read.
 *
 * Flitt may still have acted, so check the order's status before sending the call again.
 */
final class ConnectionFailedException extends FlittException
{
    private const string CHECK_STATUS = 'Flitt may still have acted: check the order\'s status before sending it again.';

    /**
     * @internal
     */
    public static function noAnswer(string $operation, Throwable $previous): self
    {
        return new self(
            sprintf('Flitt did not answer the %s call (a timeout or a network failure). %s', $operation, self::CHECK_STATUS),
            previous: $previous,
        );
    }

    /**
     * @internal
     */
    public static function unexpectedStatus(string $operation, int $status): self
    {
        return new self(sprintf('Flitt answered the %s call with HTTP %d. %s', $operation, $status, self::CHECK_STATUS));
    }

    /**
     * @internal
     */
    public static function unreadable(string $operation): self
    {
        return new self(sprintf('Flitt\'s answer to the %s call could not be read. %s', $operation, self::CHECK_STATUS));
    }
}
