<?php

declare(strict_types=1);

namespace AgentSubprocess\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running]);
    }
}
