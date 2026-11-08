<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * What kind of transaction an order or a transaction-list row is.
 */
enum TranType: string
{
    case PURCHASE = 'purchase';
    case VERIFICATION = 'verification';
    case CAPTURE = 'capture';
    case REVERSE = 'reverse';
    case P2P_CREDIT = 'p2p credit';
    case P2P_TRANSFER = 'p2p transfer';
    case SETTLEMENT = 'settlement';
}
