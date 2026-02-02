<?php

declare(strict_types=1);

namespace AgentSubprocess\Jobs;

use AgentSubprocess\Events\AgentRunCompleted;
use AgentSubprocess\Events\AgentRunFailed;
use AgentSubprocess\Events\AgentRunProgress;
use AgentSubprocess\Events\Output\ErrorEvent;
use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Events\Output\ProgressEvent;
use AgentSubprocess\Events\Output\ResultEvent;
use AgentSubprocess\Events\Output\SystemEvent;
use AgentSubprocess\Facades\Agent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class MonitorAgentProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1000;

    public int $maxExceptions = 3;

    public function __construct(
        public AgentRun $agentRun,
    ) {}

    public function handle(): void
    {
        $process = $this->agentRun->getProcessDto();

        if (! $process) {
            $this->agentRun->fail('No process handle found');
            AgentRunFailed::dispatch($this->agentRun, 'No process handle found');

            return;
        }

        $driver = Agent::driver($this->agentRun->driver);

        try {
            $logPosition = LogPosition::fromArray($this->agentRun->log_position);
            $result = $driver->readLogIncremental($process, $logPosition);

            $lastMessage = null;
            $resultEvent = null;

            foreach ($result->events as $event) {
                if ($event instanceof SystemEvent && ! $this->agentRun->system_info) {
                    $this->agentRun->system_info = $event->toArray();
                }

                if ($event instanceof ProgressEvent) {
                    $this->agentRun->updateProgress($event->percent, $event->activity);
                    AgentRunProgress::dispatch($this->agentRun, $event->percent, $event->activity);
                }

                if ($event instanceof MessageEvent) {
                    $lastMessage = $event->content;
                }

                if ($event instanceof ResultEvent) {
                    $resultEvent = $event;
                }

                if ($event instanceof ErrorEvent) {
                    $this->agentRun->log_position = $result->position->toArray();
                    $this->agentRun->save();
                    $this->agentRun->fail($event->message);
                    AgentRunFailed::dispatch($this->agentRun, $event->message);

                    return;
                }
            }

            $this->agentRun->log_position = $result->position->toArray();
            $this->agentRun->save();

            if ($driver->isRunning($process)) {
                self::dispatch($this->agentRun)
                    ->delay(now()->addSeconds(5));

                return;
            }

            if ($resultEvent && ! $resultEvent->success) {
                $error = $resultEvent->error ?? 'Process failed';
                $this->agentRun->fail($error);
                AgentRunFailed::dispatch($this->agentRun, $error);

                return;
            }

            $structuredOutput = $resultEvent?->structuredOutput;
            if (! $structuredOutput && method_exists($driver, 'extractStructuredOutput')) {
                $extracted = $driver->extractStructuredOutput($process);
                $structuredOutput = $extracted['data'] ?? null;
            }

            $resultData = array_filter([
                'output' => $lastMessage,
                'structured_output' => $structuredOutput,
                'usage' => $resultEvent?->usage,
                'duration_ms' => $resultEvent?->durationMs,
                'cost_usd' => $resultEvent?->costUsd,
                'num_turns' => $resultEvent?->numTurns,
                'success' => $resultEvent?->success ?? true,
            ], fn ($value) => $value !== null);

            $this->agentRun->complete($resultData);
            AgentRunCompleted::dispatch($this->agentRun, $resultData);

        } catch (Throwable $e) {
            $this->agentRun->fail($e->getMessage());
            AgentRunFailed::dispatch($this->agentRun, $e->getMessage());

            throw $e;
        }
    }
}
