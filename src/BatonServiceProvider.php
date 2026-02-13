<?php

declare(strict_types=1);

namespace Baton;

use Illuminate\Support\ServiceProvider;

class BatonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baton.php', 'baton');

        $this->app->singleton(BatonManager::class, function ($app) {
            return new BatonManager($app['config']['baton'] ?? []);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/baton.php' => config_path('baton.php'),
            ], 'baton-config');
        }
    }
}
