<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use Illuminate\Contracts\Config\Repository;

/**
 * The default resolver: it knows only the merchant in config/flitt.php.
 */
final readonly class ConfigCredentialsResolver implements CredentialsResolver
{
    public function __construct(private Repository $config) {}

    public function forMerchant(int $merchantId): ?Credentials
    {
        $credentials = $this->configured();

        return $credentials?->merchantId === $merchantId ? $credentials : null;
    }

    /**
     * The merchant in config/flitt.php, or null when its id or payment key is not set.
     */
    public function configured(): ?Credentials
    {
        $merchantId = filter_var($this->config->get('flitt.merchant_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $paymentKey = $this->config->get('flitt.payment_key');
        $creditKey = $this->config->get('flitt.credit_key');

        if ($merchantId === false || ! is_string($paymentKey) || $paymentKey === '') {
            return null;
        }

        return new Credentials(
            merchantId: $merchantId,
            paymentKey: $paymentKey,
            creditKey: is_string($creditKey) && $creditKey !== '' ? $creditKey : null,
        );
    }
}
