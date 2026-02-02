<?php

declare(strict_types=1);

namespace AgentSubprocess\Models;

use AgentSubprocess\Data\Process;
use AgentSubprocess\Enums\RunStatus;
use AgentSubprocess\Facades\Agent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AgentRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'driver',
        'status',
        'progress_percent',
        'current_activity',
        'process',
        'composed_context',
        'config',
        'system_info',
        'prompt',
        'result',
        'error',
        'log_position',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => RunStatus::class,
        'progress_percent' => 'integer',
        'process' => 'array',
        'composed_context' => 'array',
        'config' => 'array',
        'system_info' => 'array',
        'result' => 'array',
        'log_position' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => RunStatus::Pending,
        'progress_percent' => 0,
    ];

    public function runnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('order');
    }

    public function getProcessDto(): ?Process
    {
        if (! $this->process) {
            return null;
        }

        return Process::fromArray($this->process);
    }

    public function setProcessDto(Process $process): self
    {
        $this->process = $process->toArray();

        return $this;
    }

    public function start(): self
    {
        $this->status = RunStatus::Running;
        $this->started_at = now();
        $this->save();

        return $this;
    }

    public function complete(array $result = []): self
    {
        $this->status = RunStatus::Completed;
        $this->progress_percent = 100;
        $this->result = $result;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function fail(string $error): self
    {
        $this->status = RunStatus::Failed;
        $this->error = $error;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function cancel(): self
    {
        $this->status = RunStatus::Cancelled;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function updateProgress(int $percent, ?string $activity = null): self
    {
        $this->progress_percent = min(100, max(0, $percent));

        if ($activity !== null) {
            $this->current_activity = $activity;
        }

        $this->save();

        return $this;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function getLiveOutput(int $lines = 50): string
    {
        $process = $this->getProcessDto();

        if (! $process) {
            return '';
        }

        $driver = Agent::driver($this->driver);

        return $driver->getLiveOutput($process, $lines);
    }
}
