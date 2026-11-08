<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Protocol;

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\ApiErrorException;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Signing\Verifier;
use Saloon\Http\Response;

/**
 * @internal Reads Flitt's answer to an API call by its shape, never by the protocol the request used.
 *
 * Flitt answers errors with HTTP 200 and an error_code, so any other HTTP status is a transport failure.
 */
final class AnswerReader
{
    /**
     * The fields of an order or an operation's result, with the signature checked.
     *
     * @param  bool  $signed  whether Flitt signs this operation's answer; a signature that is present is always checked
     * @return array<string, mixed>
     *
     * @throws ApiErrorException
     * @throws ConnectionFailedException
     * @throws InvalidSignatureException
     * @throws MissingCredentialsException
     */
    public static function fields(Response $response, Credentials $credentials, string $operation, bool $signed): array
    {
        $payload = self::payload($response, $operation);
        self::throwIfError($payload, $operation);

        if (array_is_list($payload)) {
            throw ConnectionFailedException::unreadable($operation);
        }

        $source = "Flitt's answer to the {$operation} call";

        if (Envelope::is($payload)) {
            $data = $payload['data'];
            $order = Envelope::order($payload);

            if (! is_string($data) || $order === null) {
                throw ConnectionFailedException::unreadable($operation);
            }

            if ($signed || isset($payload['signature'])) {
                Verifier::envelope(Body::stringKeyed($payload), $data, $order, $credentials, $source);
            }

            return $order + Body::stringKeyed(array_intersect_key($payload, ['response_status' => true]));
        }

        $fields = Body::stringKeyed($payload);

        if ($signed || (isset($fields['signature']) && $fields['signature'] !== '')) {
            Verifier::flat($fields, $credentials, $source);
        }

        return $fields;
    }

    /**
     * The rows of a list answer, such as the transaction list. Flitt does not sign lists.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ApiErrorException
     * @throws ConnectionFailedException
     */
    public static function rows(Response $response, string $operation): array
    {
        $payload = self::payload($response, $operation);
        self::throwIfError($payload, $operation);

        if (! array_is_list($payload)) {
            throw ConnectionFailedException::unreadable($operation);
        }

        $rows = [];

        foreach ($payload as $row) {
            if (! is_array($row) || ($row !== [] && array_is_list($row))) {
                throw ConnectionFailedException::unreadable($operation);
            }

            $rows[] = Body::stringKeyed($row);
        }

        return $rows;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws ConnectionFailedException
     */
    private static function payload(Response $response, string $operation): array
    {
        if ($response->status() !== 200) {
            throw ConnectionFailedException::unexpectedStatus($operation, $response->status());
        }

        $body = Body::parse($response->body(), $response->header('Content-Type'))
            ?? throw ConnectionFailedException::unreadable($operation);

        if (! array_key_exists('response', $body)) {
            return $body;
        }

        return is_array($body['response']) ? $body['response'] : throw ConnectionFailedException::unreadable($operation);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @throws ApiErrorException
     */
    private static function throwIfError(array $payload, string $operation): void
    {
        $errorCode = $payload['error_code'] ?? null;

        if ($errorCode === null || $errorCode === '') {
            return;
        }

        $errorMessage = $payload['error_message'] ?? '';
        $requestId = $payload['request_id'] ?? null;

        throw new ApiErrorException(
            operation: $operation,
            errorCode: is_numeric($errorCode) ? (int) $errorCode : 0,
            errorMessage: is_string($errorMessage) ? $errorMessage : '',
            requestId: is_string($requestId) && $requestId !== '' ? $requestId : null,
        );
    }
}
