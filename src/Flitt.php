<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Builders\Checkout;
use ClassAtlas\Flitt\Builders\Recurring;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Requests\FlittRequest;
use ClassAtlas\Flitt\Requests\OrderStatusRequest;
use ClassAtlas\Flitt\Responses\Order;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Response;
use SensitiveParameter;

/**
 * The client: every Flitt operation starts here, usually through the Flitt facade.
 */
final readonly class Flitt
{
    /**
     * @internal Resolve the client from the container, or use the facade.
     */
    public function __construct(
        private Repository $config,
        private LogManager $log,
        private ?Credentials $credentials = null,
        private ?MockClient $mockClient = null,
    ) {}

    /**
     * A hosted checkout: finish it with url() or token().
     *
     * @param  int  $amount  in minor units: 12_500 is 125.00
     */
    public function checkout(string $orderId, int $amount, Currency $currency, string $orderDesc): Checkout
    {
        return new Checkout($this, $orderId, $amount, $currency, $orderDesc);
    }

    /**
     * A charge to a card saved earlier with requiredRectoken(): finish it with charge().
     *
     * @param  int  $amount  in minor units: 12_500 is 125.00
     * @param  string  $rectoken  the card token from the first order's callback ($order->rectoken)
     */
    public function recurring(
        string $orderId,
        int $amount,
        Currency $currency,
        string $orderDesc,
        #[SensitiveParameter]
        string $rectoken,
    ): Recurring {
        return new Recurring($this, $orderId, $amount, $currency, $orderDesc, $rectoken);
    }

    /**
     * The order as Flitt has it now.
     */
    public function status(string $orderId): Order
    {
        if ($orderId === '') {
            throw InvalidParameterException::invalid('orderId', 'must not be empty');
        }

        $request = new OrderStatusRequest($orderId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * A client that uses these credentials for every call made through it.
     */
    public function withCredentials(Credentials $credentials): self
    {
        return new self($this->config, $this->log, $credentials, $this->mockClient);
    }

    /**
     * @internal The credentials a call made through this client uses: its own, or else the merchant in config/flitt.php.
     *
     * @throws MissingCredentialsException when there are neither
     * @throws InvalidParameterException when FLITT_MERCHANT_ID is set to something that is not a merchant id
     */
    public function credentials(): Credentials
    {
        $credentials = $this->credentials ?? new ConfigCredentialsResolver($this->config)->configured();

        if ($credentials !== null) {
            return $credentials;
        }

        $merchantId = $this->config->get('flitt.merchant_id');

        if ($merchantId !== null && $merchantId !== '') {
            throw InvalidParameterException::invalid('FLITT_MERCHANT_ID', 'must be a whole number above 0');
        }

        throw MissingCredentialsException::none();
    }

    /**
     * @internal A client whose calls are answered by this MockClient and never leave the app. Use Flitt::fake().
     */
    public function faked(MockClient $mockClient): self
    {
        return new self($this->config, $this->log, $this->credentials, $mockClient);
    }

    /**
     * @internal
     */
    public function mockClient(): ?MockClient
    {
        return $this->mockClient;
    }

    /**
     * @internal Sends a request with this client's credentials.
     */
    public function send(FlittRequest $request): Response
    {
        return $this->connector()->send($request);
    }

    /**
     * @internal
     *
     * @throws MissingCredentialsException
     */
    public function connector(): FlittConnector
    {
        $connector = new FlittConnector(
            credentials: $this->credentials(),
            timeout: $this->integerSetting('flitt.timeout', 10),
            connectTimeout: $this->integerSetting('flitt.connect_timeout', 3),
            logger: $this->logger(),
        );

        return $this->mockClient === null ? $connector : $connector->withMockClient($this->mockClient);
    }

    private function logger(): ?LoggerInterface
    {
        if (! (bool) $this->config->get('flitt.log_requests')) {
            return null;
        }

        $channel = $this->config->get('flitt.log_channel');

        return $this->log->channel(is_string($channel) && $channel !== '' ? $channel : null);
    }

    /**
     * A timeout of 0 would make Guzzle wait forever, so an empty, zero or negative setting falls back to the default.
     */
    private function integerSetting(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
