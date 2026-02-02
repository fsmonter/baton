<?php

declare(strict_types=1);

namespace AgentSubprocess\Commands;

use AgentSubprocess\Enums\RunStatus;
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
use AgentSubprocess\Log\LogReader;
use AgentSubprocess\Models\AgentRun;
use Illuminate\Console\Command;

class MonitorAgentRunsCommand extends Command
{
    protected $signature = 'agent:monitor {--interval=5 : Polling interval in seconds}';

    protected $description = 'Monitor active agent runs as a daemon';

    protected bool $shouldRun = true;

    public function handle(): int
    {
        $interval = (int) $this->option('interval');

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldRun = false);
            pcntl_signal(SIGTERM, fn () => $this->shouldRun = false);
        }

        $this->info("Starting agent monitor daemon (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        while ($this->shouldRun) {
            $this->pollActiveRuns();
            sleep($interval);
        }

        $this->newLine();
        $this->info('Shutting down gracefully...');

        return self::SUCCESS;
    }

    protected function pollActiveRuns(): void
    {
        $activeRuns = AgentRun::query()
            ->whereIn('status', [RunStatus::Running, RunStatus::Queued])
            ->get();

        if ($activeRuns->isEmpty()) {
            return;
        }

        $this->comment("[{$this->timestamp()}] Polling {$activeRuns->count()} active run(s)");

        $activeRuns->each(fn (AgentRun $run) => $this->processRun($run));
    }

    protected function processRun(AgentRun $run): void
    {
        $process = $run->getProcessDto();

        if (! $process) {
            $this->handleMissingProcess($run);

            return;
        }

        $driver = Agent::driver($run->driver);

        $logPosition = LogPosition::fromArray($run->log_position);
        $reader = new LogReader($driver->getParser());
        $result = $reader->readIncremental($process->outputPath, $logPosition);

        $lastMessage = null;
        $resultEvent = null;

        foreach ($result->events as $event) {
            if ($event instanceof SystemEvent && ! $run->system_info) {
                $run->system_info = $event->toArray();
                $this->line("  [{$run->id}] System: {$event->model} ({$event->version})");
            }

            if ($event instanceof ProgressEvent) {
                $run->updateProgress($event->percent, $event->activity);
                AgentRunProgress::dispatch($run, $event->percent, $event->activity);
                $this->line("  [{$run->id}] Progress: {$event->percent}% - {$event->activity}");
            }

            if ($event instanceof MessageEvent) {
                $lastMessage = $event->content;
            }

            if ($event instanceof ResultEvent) {
                $resultEvent = $event;
            }

            if ($event instanceof ErrorEvent) {
                $run->log_position = $result->position->toArray();
                $run->save();
                $run->fail($event->message);
                AgentRunFailed::dispatch($run, $event->message);
                $this->error("  [{$run->id}] Failed: {$event->message}");

                return;
            }
        }

        $run->log_position = $result->position->toArray();
        $run->save();

        if (! $driver->isRunning($process)) {
            if ($resultEvent && ! $resultEvent->success) {
                $error = $resultEvent->error ?? 'Process failed';
                $run->fail($error);
                AgentRunFailed::dispatch($run, $error);
                $this->error("  [{$run->id}] Failed: {$error}");

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

            $run->complete($resultData);
            AgentRunCompleted::dispatch($run, $resultData);
            $this->info("  [{$run->id}] Completed");
        }
    }

    protected function handleMissingProcess(AgentRun $run): void
    {
        $run->fail('No process handle found');
        AgentRunFailed::dispatch($run, 'No process handle found');
        $this->error("  [{$run->id}] Failed: No process handle found");
    }

    protected function timestamp(): string
    {
        return now()->format('H:i:s');
    }
}
