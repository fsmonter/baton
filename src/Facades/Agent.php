<?php

declare(strict_types=1);

namespace AgentSubprocess\Facades;

use AgentSubprocess\AgentManager;
use AgentSubprocess\Contracts\AgentDriver;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AgentDriver driver(?string $driver = null)
 * @method static AgentManager extend(string $driver, \Closure $callback)
 * @method static string getDefaultDriver()
 *
 * @see \AgentSubprocess\AgentManager
 */
class Agent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AgentManager::class;
    }
}
