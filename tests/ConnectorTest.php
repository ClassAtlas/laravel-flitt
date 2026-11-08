<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Flitt;
use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Logging\CallLog;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\SigningKey;
use ClassAtlas\Flitt\Tests\Support\MemoryLogger;
use ClassAtlas\Flitt\Tests\Support\ProbeRequest;
use GuzzleHttp\RequestOptions;
use Monolog\Handler\TestHandler;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

function timedOut(): MockResponse
{
    return MockResponse::make()->throw(
        static fn (PendingRequest $pendingRequest) => new FatalRequestException(new RuntimeException('cURL error 28'), $pendingRequest),
    );
}

it('sends to pay.flitt.com with the timeouts it was given', function () {
    $pendingRequest = new FlittConnector(new Credentials(1549901, 'test'), timeout: 25, connectTimeout: 5)
        ->createPendingRequest(new ProbeRequest);

    expect($pendingRequest->getUrl())->toBe('https://pay.flitt.com/api/status/order_id')
        ->and($pendingRequest->getMethod()->value)->toBe('POST')
        ->and($pendingRequest->config()->all())->toMatchArray([
            RequestOptions::TIMEOUT => 25,
            RequestOptions::CONNECT_TIMEOUT => 5,
        ]);
});

it('sends the parameters inside a request root, with merchant_id, version and the signature', function () {
    $mockClient = new MockClient([ProbeRequest::class => MockResponse::make(['response' => []])]);

    new FlittConnector(new Credentials(1549901, 'test'))
        ->send(new ProbeRequest(['order_id' => 'inv-1042']), $mockClient);

    $body = $mockClient->getLastPendingRequest()?->body()?->all();
    $signed = ['order_id' => 'inv-1042', 'merchant_id' => 1549901, 'version' => '1.0.1'];

    expect($body)->toBe(['request' => [...$signed, 'signature' => Signer::sign($signed, 'test')]]);
});

it('never retries, and says to check the status when no answer came', function () {
    $attempts = 0;
    $mockClient = new MockClient([ProbeRequest::class => function () use (&$attempts): MockResponse {
        $attempts++;

        return timedOut();
    }]);

    try {
        new FlittConnector(new Credentials(1549901, 'test'))->send(new ProbeRequest, $mockClient);
    } catch (ConnectionFailedException $exception) {
        expect($attempts)->toBe(1)
            ->and($exception->getMessage())->toContain('did not answer the probe call')
            ->toContain("check the order's status before sending it again")
            ->and($exception->getPrevious())->toBeInstanceOf(FatalRequestException::class);

        return;
    }

    $this->fail('No ConnectionFailedException.');
});

it('sends nothing when a value cannot be signed', function () {
    $mockClient = new MockClient([ProbeRequest::class => MockResponse::make(['response' => []])]);

    expect(fn () => new FlittConnector(new Credentials(1549901, 'test'))
        ->send(new ProbeRequest(['order_desc' => 'a|b']), $mockClient))
        ->toThrow(InvalidParameterException::class);

    $mockClient->assertNothingSent();
});

it('sends nothing when a payout has no credit key', function () {
    $mockClient = new MockClient([ProbeRequest::class => MockResponse::make(['response' => []])]);

    expect(fn () => new FlittConnector(new Credentials(1549901, 'test'))
        ->send(new ProbeRequest(key: SigningKey::CREDIT), $mockClient))
        ->toThrow(MissingCredentialsException::class);

    $mockClient->assertNothingSent();
});

it('logs one line per call, with nothing but the allowed fields', function (MockResponse $answer, string $level, ?int $status, ?int $errorCode) {
    $logger = new MemoryLogger;

    try {
        new FlittConnector(new Credentials(1549901, 'test-secret-key'), logger: $logger)
            ->send(new ProbeRequest(['order_id' => 'inv-1042', 'sender_email' => 'payer@example.com']), new MockClient([
                ProbeRequest::class => $answer,
            ]));
    } catch (ConnectionFailedException) {
    }

    expect($logger->lines)->toHaveCount(1)
        ->and($logger->lines[0]['level'])->toBe($level)
        ->and($logger->lines[0]['message'])->toBe(CallLog::MESSAGE)
        ->and(array_keys($logger->lines[0]['context']))
        ->toBe(['operation', 'merchant_id', 'order_id', 'http_status', 'error_code', 'duration_ms'])
        ->and($logger->lines[0]['context'])->toMatchArray([
            'operation' => 'probe',
            'merchant_id' => 1549901,
            'order_id' => 'inv-1042',
            'http_status' => $status,
            'error_code' => $errorCode,
        ])
        ->and($logger->lines[0]['context']['duration_ms'])->toBeInt()
        ->and(json_encode($logger->lines, JSON_THROW_ON_ERROR))
        ->not->toContain('test-secret-key')
        ->not->toContain('payer@example.com')
        ->not->toContain('signature');
})->with([
    'an answer' => [fn () => MockResponse::make(['response' => ['order_status' => 'approved']]), 'info', 200, null],
    'an error' => [fn () => MockResponse::make(['response' => ['error_code' => 1018, 'error_message' => 'Order not found']]), 'warning', 200, 1018],
    'an HTTP error' => [fn () => MockResponse::make('Not found', 404), 'warning', 404, null],
    'no answer' => [fn () => timedOut(), 'warning', null, null],
]);

it('builds its connector from config, with logging off by default', function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test', 'flitt.timeout' => 20, 'flitt.connect_timeout' => 4]);

    $connector = app(Flitt::class)->connector();
    $mockClient = new MockClient([ProbeRequest::class => MockResponse::make(['response' => []])]);
    $connector->send(new ProbeRequest, $mockClient);

    expect($connector->credentials()->merchantId)->toBe(1549901)
        ->and($mockClient->getLastPendingRequest()?->config()->all())->toMatchArray([
            RequestOptions::TIMEOUT => 20,
            RequestOptions::CONNECT_TIMEOUT => 4,
        ])
        ->and($mockClient->getLastPendingRequest()?->middleware()->getResponsePipeline()->getPipes())->toBeEmpty();
});

it('logs to the configured channel when FLITT_LOG_REQUESTS is on', function () {
    config([
        'flitt.merchant_id' => '1549901',
        'flitt.payment_key' => 'test',
        'flitt.log_requests' => true,
        'flitt.log_channel' => 'flitt-test',
        'logging.channels.flitt-test' => ['driver' => 'monolog', 'handler' => TestHandler::class],
    ]);

    app(Flitt::class)->connector()->send(new ProbeRequest, new MockClient([
        ProbeRequest::class => MockResponse::make(['response' => []]),
    ]));

    /** @var TestHandler $handler */
    $handler = app('log')->channel('flitt-test')->getLogger()->getHandlers()[0];

    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->message)->toBe(CallLog::MESSAGE);
});

it('refuses to call Flitt with no credentials at all', function () {
    app(Flitt::class)->connector();
})->throws(MissingCredentialsException::class, 'No Flitt credentials');
