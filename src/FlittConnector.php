<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Logging\CallLog;
use ClassAtlas\Flitt\Requests\FlittRequest;
use ClassAtlas\Flitt\Signing\RequestBody;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Sends Flitt's requests for one merchant to pay.flitt.com, signed with that merchant's keys.
 *
 * It never retries: after a timeout you cannot tell whether Flitt acted, so a blind retry of a recurring charge or a
 * payout could move money twice. Check the order's status instead.
 */
final class FlittConnector extends Connector
{
    use HasTimeout;

    public const string BASE_URL = 'https://pay.flitt.com';

    protected int $requestTimeout;

    protected int $connectTimeout;

    /**
     * @internal Use the Flitt client, which builds the connector from the credentials and config/flitt.php.
     */
    public function __construct(
        private readonly Credentials $credentials,
        int $timeout = 10,
        int $connectTimeout = 3,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->requestTimeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    public function resolveBaseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * @internal
     */
    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    /**
     * Signs the request's parameters into the body, and attaches the call log when logging is on.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();

        if (! $request instanceof FlittRequest) {
            return;
        }

        $pendingRequest->body()?->set([
            'request' => RequestBody::signed(
                $request->parameters(),
                $this->credentials,
                $request->signingKey(),
                $request->operation(),
            ),
        ]);

        if ($this->logger !== null) {
            CallLog::attach($pendingRequest, $request, $this->credentials->merchantId, $this->logger);
        }
    }

    /**
     * @throws ConnectionFailedException when no answer comes back: a timeout or a network failure
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $exception) {
            throw ConnectionFailedException::noAnswer(
                $request instanceof FlittRequest ? $request->operation() : 'Flitt',
                $exception,
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
