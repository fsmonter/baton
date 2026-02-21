<?php

declare(strict_types=1);

namespace Baton\Contracts;

use Baton\PendingProcess;
use Baton\Process;
use Baton\Result;

interface AgentAdapter
{
    public function spawn(PendingProcess $pending): Process;

    public function isRunning(Process $process): bool;

    public function terminate(Process $process): bool;

    public function readOutput(Process $process): OutputReader;

    public function getResult(Process $process): ?Result;

    /** @return array<string, mixed> */
    public function capabilities(): array;
}
