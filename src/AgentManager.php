<?php

declare(strict_types=1);

namespace AgentSubprocess;

use AgentSubprocess\Contracts\AgentDriver;
use AgentSubprocess\Drivers\ClaudeCodeDriver;
use AgentSubprocess\Drivers\NullDriver;
use Closure;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class AgentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('agents.default', 'claude-code');
    }

    public function driver($driver = null): AgentDriver
    {
        return parent::driver($driver);
    }

    protected function createClaudeCodeDriver(): AgentDriver
    {
        return new ClaudeCodeDriver(
            $this->config->get('agents.drivers.claude-code', [])
        );
    }

    protected function createNullDriver(): AgentDriver
    {
        return new NullDriver(
            $this->config->get('agents.drivers.null', [])
        );
    }

    public function extend($driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    protected function callCustomCreator($driver): AgentDriver
    {
        return $this->customCreators[$driver]($this->container, $this->getDriverConfig($driver));
    }

    protected function createDriver($driver): AgentDriver
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create'.str($driver)->studly()->replace('-', '').'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    protected function getDriverConfig(string $driver): array
    {
        return $this->config->get("agents.drivers.{$driver}", []);
    }
}
