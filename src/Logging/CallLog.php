<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Logging;

use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Requests\FlittRequest;
use Psr\Log\LoggerInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

/**
 * @internal One log line per call, with the operation, merchant id, order id, HTTP status, Flitt's error code and the
 * time taken. Never bodies, keys, signatures, card tokens, card numbers, emails or IP addresses.
 */
final class CallLog
{
    public const string MESSAGE = 'Flitt call';

    public static function attach(
        PendingRequest $pendingRequest,
        FlittRequest $request,
        int $merchantId,
        LoggerInterface $logger,
    ): void {
        $startedAt = hrtime(true);
        $orderId = $request->parameters()['order_id'] ?? null;

        $context = static fn (?int $httpStatus, ?int $errorCode): array => [
            'operation' => $request->operation(),
            'merchant_id' => $merchantId,
            'order_id' => is_string($orderId) ? $orderId : null,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
        ];

        $pendingRequest->middleware()->onResponse(static function (Response $response) use ($logger, $context): void {
            $errorCode = self::errorCode($response);
            $level = $response->status() === 200 && $errorCode === null ? 'info' : 'warning';

            $logger->log($level, self::MESSAGE, $context($response->status(), $errorCode));
        });

        $pendingRequest->middleware()->onFatalException(static function () use ($logger, $context): void {
            $logger->warning(self::MESSAGE, $context(null, null));
        });
    }

    private static function errorCode(Response $response): ?int
    {
        $body = Body::parse($response->body(), $response->header('Content-Type')) ?? [];
        $answer = is_array($body['response'] ?? null) ? $body['response'] : $body;
        $errorCode = $answer['error_code'] ?? null;

        return is_numeric($errorCode) ? (int) $errorCode : null;
    }
}
