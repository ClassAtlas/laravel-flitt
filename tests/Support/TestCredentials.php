<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Credentials;

final class TestCredentials
{
    public static function testMerchant(): Credentials
    {
        return new Credentials(1549901, 'test', 'testcredit');
    }

    public static function withKeys(): Credentials
    {
        return new Credentials(merchantId: 1549901, paymentKey: 'payment-secret', creditKey: 'credit-secret');
    }

    public static function configure(mixed $merchantId = '1549901', mixed $paymentKey = 'test', mixed $creditKey = 'testcredit'): void
    {
        config([
            'flitt.merchant_id' => $merchantId,
            'flitt.payment_key' => $paymentKey,
            'flitt.credit_key' => $creditKey,
        ]);
    }
}
