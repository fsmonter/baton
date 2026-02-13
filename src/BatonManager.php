<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\Driver;
use Baton\Drivers\ClaudeCodeDriver;
use Baton\Drivers\NullDriver;
use Closure;
use InvalidArgumentException;

class BatonManager
{
    /** @var array<string, Driver> */
    protected array $drivers = [];

    /** @var array<string, Closure> */
    protected array $customCreators = [];

    public function __construct(
        protected array $config = [],
    ) {}

    public function prompt(string $prompt): PendingProcess
    {
        return (new PendingProcess($this))->prompt($prompt);
    }

    public function spawn(string $prompt): Process
    {
        return $this->prompt($prompt)->spawn();
    }

    public function driver(?string $name = null): Driver
    {
        $name ??= $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    public function extend(string $name, Closure $callback): static
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'claude-code';
    }

    protected function resolve(string $name): Driver
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->getDriverConfig($name));
        }

        return match ($name) {
            'claude-code' => new ClaudeCodeDriver($this->getDriverConfig($name)),
            'null' => new NullDriver($this->getDriverConfig($name)),
            default => throw new InvalidArgumentException("Driver [{$name}] is not supported."),
        };
    }

    protected function getDriverConfig(string $name): array
    {
        return $this->config['drivers'][$name] ?? [];
    }
}
