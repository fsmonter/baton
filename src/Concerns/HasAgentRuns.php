<?php

declare(strict_types=1);

namespace AgentSubprocess\Concerns;

use AgentSubprocess\Enums\RunStatus;
use AgentSubprocess\Models\AgentRun;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAgentRuns
{
    public function agentRuns(): MorphMany
    {
        return $this->morphMany(AgentRun::class, 'runnable');
    }

    public function createAgentRun(array $attributes = []): AgentRun
    {
        return $this->agentRuns()->create($attributes);
    }

    public function latestAgentRun(): ?AgentRun
    {
        return $this->agentRuns()->latest()->first();
    }

    public function activeAgentRun(): ?AgentRun
    {
        return $this->agentRuns()
            ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
            ->latest()
            ->first();
    }

    public function hasActiveAgentRun(): bool
    {
        return $this->activeAgentRun() !== null;
    }

    public function completedAgentRuns(): MorphMany
    {
        return $this->agentRuns()->where('status', RunStatus::Completed);
    }

    public function failedAgentRuns(): MorphMany
    {
        return $this->agentRuns()->where('status', RunStatus::Failed);
    }
}
