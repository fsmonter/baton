<?php

declare(strict_types=1);

namespace AgentSubprocess\Models;

use AgentSubprocess\Enums\StepStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_run_id',
        'name',
        'description',
        'order',
        'status',
        'result',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'order' => 'integer',
        'status' => StepStatus::class,
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => StepStatus::Pending,
        'order' => 0,
    ];

    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    public function start(): self
    {
        $this->status = StepStatus::Running;
        $this->started_at = now();
        $this->save();

        return $this;
    }

    public function complete(array $result = []): self
    {
        $this->status = StepStatus::Completed;
        $this->result = $result;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function fail(string $error): self
    {
        $this->status = StepStatus::Failed;
        $this->error = $error;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function skip(): self
    {
        $this->status = StepStatus::Skipped;
        $this->completed_at = now();
        $this->save();

        return $this;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
