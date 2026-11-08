<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * How an order was paid, and which payment systems a checkout offers.
 */
enum PaymentSystem: string
{
    case CARD = 'card';
    case OPB = 'opb';
    case INSTALLMENTS = 'installments';
    case P2P_IBAN_GE = 'p2p_iban_ge';
}
