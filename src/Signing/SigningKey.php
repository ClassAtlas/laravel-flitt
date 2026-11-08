<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Signing;

/**
 * @internal Which of a merchant's two keys signs a request: payouts take the credit key, everything else the payment key.
 */
enum SigningKey: string
{
    case PAYMENT = 'payment';
    case CREDIT = 'credit';
}
