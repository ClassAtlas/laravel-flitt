<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * The outcome of one reverse.
 */
enum ReverseStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
}
