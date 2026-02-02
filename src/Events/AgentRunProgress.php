<?php

declare(strict_types=1);

namespace AgentSubprocess\Events;

use AgentSubprocess\Models\AgentRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentRunProgress
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AgentRun $agentRun,
        public readonly int $percent,
        public readonly ?string $activity = null,
    ) {}
}
