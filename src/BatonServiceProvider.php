<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\ProcessStore;
use Baton\Store\FilesystemStore;
use Illuminate\Support\ServiceProvider;

class BatonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/baton.php', 'baton');

        $this->app->singleton(ProcessStore::class, function ($app) {
            $config = $app['config']['baton.store'] ?? [];
            $driver = $config['driver'] ?? 'filesystem';

            return match ($driver) {
                'filesystem' => new FilesystemStore(
                    $config['path'] ?? storage_path('baton/processes'),
                ),
                default => new FilesystemStore(storage_path('baton/processes')),
            };
        });

        $this->app->singleton(BatonManager::class, function ($app) {
            $manager = new BatonManager($app['config']['baton'] ?? []);

            $manager->setStore($app->make(ProcessStore::class));

            if ($app->bound('events')) {
                $manager->setEventDispatcher($app['events']);
            }

            return $manager;
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
