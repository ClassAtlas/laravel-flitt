<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Enums;

/**
 * Whether a preauthorised order's money is still held or has been captured.
 */
enum CaptureStatus: string
{
    case HOLD = 'hold';
    case CAPTURED = 'captured';
}
