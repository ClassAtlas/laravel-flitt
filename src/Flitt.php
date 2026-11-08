<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use Illuminate\Contracts\Config\Repository;

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
        private ?Credentials $credentials = null,
    ) {}

    /**
     * A client that uses these credentials for every call made through it.
     */
    public function withCredentials(Credentials $credentials): self
    {
        return new self($this->config, $credentials);
    }

    /**
     * @internal The credentials a call made through this client uses: its own, or else the merchant in config/flitt.php.
     */
    public function credentials(): ?Credentials
    {
        return $this->credentials ?? new ConfigCredentialsResolver($this->config)->configured();
    }
}
