<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Exceptions;

use RuntimeException;

/**
 * Every exception the package throws extends this one. Messages never carry request or response bodies, or keys.
 */
abstract class FlittException extends RuntimeException {}
