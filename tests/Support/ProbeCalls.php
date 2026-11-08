<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Signing\Signer;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

final class ProbeCalls
{
    public static function answer(MockResponse $mock, ?Credentials $credentials = null): Response
    {
        return new FlittConnector($credentials ?? new Credentials(1549901, 'test', 'testcredit'))
            ->send(new ProbeRequest, new MockClient([ProbeRequest::class => $mock]));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function signedWith(string $key, array $fields): array
    {
        return [...$fields, 'signature' => Signer::sign($fields, $key)];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function envelopeSignedWith(string $key, array $order, array $beside = []): array
    {
        $data = base64_encode(json_encode(['order' => $order], JSON_THROW_ON_ERROR));

        return ['data' => $data, 'version' => '2.0', 'signature' => Signer::signData($data, $key), ...$beside];
    }

    public static function timedOut(): MockResponse
    {
        return MockResponse::make()->throw(
            static fn (PendingRequest $pendingRequest) => new FatalRequestException(new RuntimeException('cURL error 28'), $pendingRequest),
        );
    }
}
