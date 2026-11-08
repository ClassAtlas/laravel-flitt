<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Contracts;

use ClassAtlas\Flitt\Credentials;

/**
 * Finds the credentials that check a callback or browser return, by the merchant id it carries.
 *
 * Bind your own implementation when your app holds more than one merchant.
 */
interface CredentialsResolver
{
    /**
     * The merchant's credentials, or null for a merchant your app does not know.
     */
    public function forMerchant(int $merchantId): ?Credentials;
}
