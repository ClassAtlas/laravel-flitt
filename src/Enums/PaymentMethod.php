<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * The bank to preselect for open banking or installments. DEMO is Flitt's test bank.
 */
enum PaymentMethod: string
{
    case TBC = 'tbc';
    case BOG = 'bog';
    case LIBERTY = 'liberty';
    case CREDO = 'credo';
    case DEMO = 'x';
}
