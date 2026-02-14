<?php

declare(strict_types=1);

namespace Baton\Tests;

use Baton\BatonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BatonServiceProvider::class,
        ];
    }
}
