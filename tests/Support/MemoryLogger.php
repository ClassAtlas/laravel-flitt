<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class MemoryLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $lines = [];

    /**
     * @param  array<mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->lines[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
