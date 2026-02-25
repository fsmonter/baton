<?php

declare(strict_types=1);

namespace Baton;

class PendingProcess
{
    protected ?string $promptText = null;
    protected ?string $modelName = null;
    protected ?array $toolsList = null;
    protected ?string $directory = null;
    protected ?string $agentName = null;
    protected ?int $maxTurnsCount = null;
    protected ?int $timeoutSeconds = null;
    protected ?array $jsonSchema = null;
    protected ?string $mcpConfigPath = null;
    protected ?string $permissionModeName = null;
    protected ?array $allowedToolsList = null;
    protected ?array $envVariables = null;
    protected ?string $settingSourcesValue = null;

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

    public function via(string $agent): static
    {
        $this->agentName = $agent;

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

    public function mcpConfig(string $path): static
    {
        $this->mcpConfigPath = $path;

        return $this;
    }

    public function permissionMode(string $mode): static
    {
        $this->permissionModeName = $mode;

        return $this;
    }

    public function allowedTools(array $tools): static
    {
        $this->allowedToolsList = $tools;

        return $this;
    }

    public function env(array $variables): static
    {
        $this->envVariables = $variables;

        return $this;
    }

    public function settingSources(string $sources): static
    {
        $this->settingSourcesValue = $sources;

        return $this;
    }

    public function spawn(): Process
    {
        return $this->manager->spawnProcess($this);
    }

    // Accessors for adapters to read config
    public function getPrompt(): ?string { return $this->promptText; }
    public function getModel(): ?string { return $this->modelName; }
    public function getTools(): ?array { return $this->toolsList; }
    public function getDirectory(): ?string { return $this->directory; }
    public function getAgentName(): ?string { return $this->agentName; }
    public function getMaxTurns(): ?int { return $this->maxTurnsCount; }
    public function getTimeout(): ?int { return $this->timeoutSeconds; }
    public function getSchema(): ?array { return $this->jsonSchema; }
    public function getMcpConfig(): ?string { return $this->mcpConfigPath; }
    public function getPermissionMode(): ?string { return $this->permissionModeName; }
    public function getAllowedTools(): ?array { return $this->allowedToolsList; }
    public function getEnv(): ?array { return $this->envVariables; }
    public function getSettingSources(): ?string { return $this->settingSourcesValue; }
}
