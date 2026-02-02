<?php

declare(strict_types=1);

namespace AgentSubprocess\Contracts;

interface AgentRunnable
{
    public function getAgentDriver(): ?string;

    public function getAgentPrompt(): string;

    /**
     * @return array<string, mixed>
     */
    public function buildAgentConfig(): array;
}
