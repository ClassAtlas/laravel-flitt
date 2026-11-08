<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests;

use ClassAtlas\Flitt\FlittServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FlittServiceProvider::class];
    }
}
