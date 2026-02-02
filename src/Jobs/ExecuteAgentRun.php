<?php

declare(strict_types=1);

namespace AgentSubprocess\Jobs;

use AgentSubprocess\Events\AgentRunFailed;
use AgentSubprocess\Events\AgentRunStarted;
use AgentSubprocess\Facades\Agent;
use AgentSubprocess\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAgentRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AgentRun $agentRun,
        public ?string $driver = null,
    ) {}

    public function handle(): void
    {
        try {
            $driver = $this->driver ?? $this->agentRun->driver;

            $this->agentRun->start();

            AgentRunStarted::dispatch($this->agentRun);

            $process = Agent::driver($driver)->spawn($this->agentRun);

            $this->agentRun->setProcessDto($process)->save();

            MonitorAgentProcess::dispatch($this->agentRun)
                ->delay(now()->addSeconds(5));

        } catch (Throwable $e) {
            $this->agentRun->fail($e->getMessage());

            AgentRunFailed::dispatch($this->agentRun, $e->getMessage());

            throw $e;
        }
    }
}
