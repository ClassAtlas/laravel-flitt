<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Facades;

use ClassAtlas\Flitt\Credentials;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \ClassAtlas\Flitt\Flitt withCredentials(Credentials $credentials)
 *
 * @see \ClassAtlas\Flitt\Flitt
 */
final class Flitt extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ClassAtlas\Flitt\Flitt::class;
    }
}
