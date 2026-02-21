<?php

declare(strict_types=1);

namespace Baton\Adapters;

use Baton\Contracts\AgentAdapter;
use Baton\Contracts\OutputReader;
use Baton\Exceptions\ProcessFailedException;
use Baton\Output\MessageEvent;
use Baton\PendingProcess;
use Baton\Process;
use Baton\Result;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NullAdapter implements AgentAdapter
{
    protected array $running = [];
    protected array $results = [];
    protected array $errors = [];
    protected array $outputs = [];

    public function __construct(
        protected array $config = [],
    ) {}

    public function spawn(PendingProcess $pending): Process
    {
        $id = Str::uuid()->toString();

        $process = new Process(
            id: $id,
            agent: 'null',
            handle: ['simulated' => true],
            workDir: $pending->getDirectory() ?? getcwd(),
            startedAt: Carbon::now(),
            agentInstance: $this,
        );

        $this->running[$id] = true;

        return $process;
    }

    public function isRunning(Process $process): bool
    {
        return $this->running[$process->id] ?? false;
    }

    public function terminate(Process $process): bool
    {
        unset($this->running[$process->id]);

        return true;
    }

    public function readOutput(Process $process): OutputReader
    {
        return new NullOutputReader($this->outputs[$process->id] ?? []);
    }

    public function getResult(Process $process): ?Result
    {
        if (isset($this->errors[$process->id])) {
            throw new ProcessFailedException(
                processId: $process->id,
                error: $this->errors[$process->id],
            );
        }

        return $this->results[$process->id] ?? null;
    }

    public function capabilities(): array
    {
        return [
            'streaming' => false,
            'tools' => false,
            'testing' => true,
        ];
    }

    // Test helpers

    public function complete(Process $process, string $output = 'Done'): void
    {
        unset($this->running[$process->id]);

        $this->results[$process->id] = new Result(output: $output);
        $this->outputs[$process->id][] = new MessageEvent(content: $output);
    }

    public function fail(Process $process, string $error = 'Failed'): void
    {
        unset($this->running[$process->id]);

        $this->errors[$process->id] = $error;
    }

    public function addOutput(Process $process, MessageEvent $event): void
    {
        $this->outputs[$process->id][] = $event;
    }
}
