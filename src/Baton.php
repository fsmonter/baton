<?php

declare(strict_types=1);

namespace Baton;

use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingProcess prompt(string $prompt)
 * @method static Process spawn(string $prompt)
 * @method static Process|null find(string $id)
 * @method static Process[] active()
 * @method static ProcessMonitor monitor()
 * @method static Contracts\AgentAdapter agent(?string $name = null)
 * @method static BatonManager extend(string $name, \Closure $callback)
 * @method static BatonManager setStore(Contracts\ProcessStore $store)
 *
 * @see BatonManager
 */
class Baton extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BatonManager::class;
    }
}
