<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;

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
    ) {}

    /**
     * A client that uses these credentials for every call made through it.
     */
    public function withCredentials(Credentials $credentials): self
    {
        return new self($this->config, $this->log, $credentials);
    }

    /**
     * @internal The credentials a call made through this client uses: its own, or else the merchant in config/flitt.php.
     *
     * @throws MissingCredentialsException when there are neither
     */
    public function credentials(): Credentials
    {
        return $this->credentials
            ?? new ConfigCredentialsResolver($this->config)->configured()
            ?? throw MissingCredentialsException::none();
    }

    /**
     * @internal
     *
     * @throws MissingCredentialsException
     */
    public function connector(): FlittConnector
    {
        return new FlittConnector(
            credentials: $this->credentials(),
            timeout: $this->integerSetting('flitt.timeout', 10),
            connectTimeout: $this->integerSetting('flitt.connect_timeout', 3),
            logger: $this->logger(),
        );
    }

    private function logger(): ?LoggerInterface
    {
        if (! (bool) $this->config->get('flitt.log_requests')) {
            return null;
        }

        $channel = $this->config->get('flitt.log_channel');

        return $this->log->channel(is_string($channel) && $channel !== '' ? $channel : null);
    }

    private function integerSetting(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
