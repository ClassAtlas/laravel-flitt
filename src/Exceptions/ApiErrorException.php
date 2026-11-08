<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

/**
 * Flitt answered with an error. It does so with HTTP 200 and an error_code, so this is the only error to catch.
 */
final class ApiErrorException extends FlittException
{
    /**
     * @internal Only the package throws this exception.
     */
    public function __construct(
        public readonly string $operation,
        public readonly int $errorCode,
        public readonly string $errorMessage,
        public readonly ?string $requestId,
    ) {
        parent::__construct(
            sprintf('Flitt refused the %s call with error %d: %s', $operation, $errorCode, $errorMessage),
            $errorCode,
        );
    }
}
