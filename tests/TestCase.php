<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests;

use ClassAtlas\Flitt\FlittServiceProvider;
use ClassAtlas\Flitt\Tests\Support\Live;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Live::setUp();
    }

    protected function tearDown(): void
    {
        Live::tearDown();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FlittServiceProvider::class];
    }
}
