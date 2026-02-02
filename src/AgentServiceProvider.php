<?php

declare(strict_types=1);

namespace AgentSubprocess;

use AgentSubprocess\Commands\MonitorAgentRunsCommand;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/agents.php',
            'agents'
        );

        $this->app->singleton(AgentManager::class, function ($app) {
            return new AgentManager($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorAgentRunsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/agents.php' => config_path('agents.php'),
            ], 'agents-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'agents-migrations');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
