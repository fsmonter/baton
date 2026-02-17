<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\Driver;

class PendingProcess
{
    protected ?string $promptText = null;
    protected ?string $modelName = null;
    protected ?array $toolsList = null;
    protected ?string $directory = null;
    protected ?string $driverName = null;
    protected ?int $maxTurnsCount = null;
    protected ?int $timeoutSeconds = null;
    protected ?array $jsonSchema = null;

    public function __construct(
        protected BatonManager $manager,
    ) {}

    public function prompt(string $prompt): static
    {
        $this->promptText = $prompt;

        return $this;
    }

    public function model(string $model): static
    {
        $this->modelName = $model;

        return $this;
    }

    public function tools(array $tools): static
    {
        $this->toolsList = $tools;

        return $this;
    }

    public function inDir(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function via(string $driver): static
    {
        $this->driverName = $driver;

        return $this;
    }

    public function maxTurns(int $maxTurns): static
    {
        $this->maxTurnsCount = $maxTurns;

        return $this;
    }

    public function timeout(int $timeout): static
    {
        $this->timeoutSeconds = $timeout;

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->jsonSchema = $schema;

        return $this;
    }

    public function spawn(): Process
    {
        return $this->manager->spawnProcess($this);
    }

    // Accessors for drivers to read config
    public function getPrompt(): ?string { return $this->promptText; }
    public function getModel(): ?string { return $this->modelName; }
    public function getTools(): ?array { return $this->toolsList; }
    public function getDirectory(): ?string { return $this->directory; }
    public function getDriverName(): ?string { return $this->driverName; }
    public function getMaxTurns(): ?int { return $this->maxTurnsCount; }
    public function getTimeout(): ?int { return $this->timeoutSeconds; }
    public function getSchema(): ?array { return $this->jsonSchema; }
}
