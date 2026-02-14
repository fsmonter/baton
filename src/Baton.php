<?php

declare(strict_types=1);

namespace Baton;

use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingProcess prompt(string $prompt)
 * @method static Process spawn(string $prompt)
 * @method static Contracts\Driver driver(?string $name = null)
 * @method static BatonManager extend(string $name, \Closure $callback)
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
