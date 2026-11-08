<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * An order's status. A partial reverse keeps APPROVED, and so does a hold: read reversalAmount, settlementAmount and additionalInfo->captureStatus beside it.
 */
enum OrderStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case DECLINED = 'declined';
    case APPROVED = 'approved';
    case EXPIRED = 'expired';
    case REVERSED = 'reversed';
}
