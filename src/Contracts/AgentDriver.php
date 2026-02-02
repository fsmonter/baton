<?php

declare(strict_types=1);

namespace AgentSubprocess\Contracts;

use AgentSubprocess\Data\Process;
use AgentSubprocess\Data\TerminationResult;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReadResult;
use AgentSubprocess\Models\AgentRun;
use Generator;

interface AgentDriver
{
    public function spawn(AgentRun $run): Process;

    public function isRunning(Process $process): bool;

    public function terminate(Process $process): bool;

    public function terminateGracefully(Process $process, int $gracePeriodMs = 500): TerminationResult;

    /**
     * @return Generator<OutputEvent>
     */
    public function parseOutput(Process $process): Generator;

    public function readLogIncremental(Process $process, LogPosition $position): LogReadResult;

    public function getLiveOutput(Process $process, int $lines = 50): string;

    public function getParser(): OutputParser;

    /**
     * @return array<string, mixed>
     */
    public function getCapabilities(): array;
}
