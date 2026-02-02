<?php

declare(strict_types=1);

namespace AgentSubprocess\Drivers;

use AgentSubprocess\Contracts\AgentDriver;
use AgentSubprocess\Contracts\OutputParser;
use AgentSubprocess\Data\Process;
use AgentSubprocess\Data\TerminationResult;
use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReadResult;
use AgentSubprocess\Models\AgentRun;
use AgentSubprocess\Parsers\ClaudeStreamParser;
use Carbon\Carbon;
use Generator;

class NullDriver implements AgentDriver
{
    protected array $spawned = [];

    protected array $outputs = [];

    public function __construct(
        protected array $config = [],
    ) {}

    public function spawn(AgentRun $run): Process
    {
        $process = new Process(
            pid: rand(10000, 99999),
            driver: 'null',
            command: ['null-agent', $run->prompt],
            workDir: getcwd(),
            outputPath: null,
            logPath: null,
            startedAt: Carbon::now(),
            meta: ['simulated' => true],
        );

        $this->spawned[$process->pid] = $process;

        return $process;
    }

    public function isRunning(Process $process): bool
    {
        return isset($this->spawned[$process->pid]);
    }

    public function terminate(Process $process): bool
    {
        unset($this->spawned[$process->pid]);

        return true;
    }

    public function terminateGracefully(Process $process, int $gracePeriodMs = 500): TerminationResult
    {
        unset($this->spawned[$process->pid]);

        return new TerminationResult(true, 'simulated');
    }

    public function parseOutput(Process $process): Generator
    {
        $outputs = $this->outputs[$process->pid] ?? [];

        foreach ($outputs as $output) {
            yield $output;
        }
    }

    public function readLogIncremental(Process $process, LogPosition $position): LogReadResult
    {
        return new LogReadResult($position, []);
    }

    public function getLiveOutput(Process $process, int $lines = 50): string
    {
        return '';
    }

    public function getParser(): OutputParser
    {
        return new ClaudeStreamParser;
    }

    public function getCapabilities(): array
    {
        return [
            'streaming' => false,
            'tools' => false,
            'models' => [],
            'testing' => true,
        ];
    }

    public function simulateComplete(Process $process, string $message = 'Completed'): void
    {
        $this->outputs[$process->pid][] = new MessageEvent(
            content: $message,
            role: 'assistant',
        );

        unset($this->spawned[$process->pid]);
    }

    public function simulateOutput(Process $process, MessageEvent $event): void
    {
        $this->outputs[$process->pid][] = $event;
    }
}
