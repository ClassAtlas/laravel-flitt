<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * The currencies Flitt lists. Which ones a merchant may use is Flitt's decision.
 */
enum Currency: string
{
    case GEL = 'GEL';
    case USD = 'USD';
    case EUR = 'EUR';
    case AMD = 'AMD';
    case AZN = 'AZN';
    case KZT = 'KZT';
    case MDL = 'MDL';
    case UZS = 'UZS';
    case GBP = 'GBP';
    case CZK = 'CZK';

    /**
     * How many minor units make one major unit, as a power of ten. Flitt gives every currency it lists two decimals.
     */
    public function decimals(): int
    {
        return 2;
    }
}
