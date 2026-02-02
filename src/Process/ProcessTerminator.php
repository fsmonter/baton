<?php

declare(strict_types=1);

namespace AgentSubprocess\Process;

use AgentSubprocess\Data\TerminationResult;

class ProcessTerminator
{
    public function terminate(int $pid, int $gracePeriodMs = 500): TerminationResult
    {
        if (! $this->isAlive($pid)) {
            return new TerminationResult(true, 'already_dead');
        }

        posix_kill($pid, SIGTERM);

        usleep($gracePeriodMs * 1000);

        if (! $this->isAlive($pid)) {
            return new TerminationResult(true, 'sigterm');
        }

        posix_kill($pid, SIGKILL);

        usleep(50000);

        return new TerminationResult(! $this->isAlive($pid), 'sigkill');
    }

    public function isAlive(int $pid): bool
    {
        return posix_kill($pid, 0);
    }
}
