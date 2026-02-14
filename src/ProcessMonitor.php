<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\ProcessStore;
use Baton\Events\ProcessCompleted;
use Baton\Events\ProcessFailed;
use Baton\Exceptions\BatonException;
use Baton\Exceptions\ProcessCrashedException;
use Baton\Exceptions\ProcessFailedException;
use Illuminate\Contracts\Events\Dispatcher;

class ProcessMonitor
{
    public function __construct(
        protected BatonManager $manager,
        protected ProcessStore $store,
        protected ?Dispatcher $events = null,
    ) {}

    /**
     * Check one or all tracked processes and return their statuses.
     *
     * Completed/failed processes are removed from the store and events are fired.
     *
     * @return array<string, ProcessStatus>
     */
    public function check(?string $id = null): array
    {
        if ($id !== null) {
            $process = $this->manager->find($id);

            if (! $process) {
                return [];
            }

            return [$id => $this->checkProcess($process)];
        }

        $results = [];

        foreach ($this->manager->active() as $process) {
            $results[$process->id] = $this->checkProcess($process);
        }

        return $results;
    }

    /**
     * Check a single process, fire events on state change, and clean up the store.
     */
    public function checkProcess(Process $process): ProcessStatus
    {
        if ($process->isRunning()) {
            return ProcessStatus::Running;
        }

        try {
            $result = $process->result();
        } catch (ProcessFailedException $e) {
            $this->fire(new ProcessFailed($process, $e->error, $e->usage));
            $this->store->remove($process->id);

            return ProcessStatus::Failed;
        } catch (ProcessCrashedException $e) {
            $this->fire(new ProcessFailed($process, $e->getMessage()));
            $this->store->remove($process->id);

            return ProcessStatus::Failed;
        }

        if ($result !== null) {
            $this->fire(new ProcessCompleted($process, $result));
        } else {
            $this->fire(new ProcessFailed($process, 'Process terminated unexpectedly'));
        }

        $this->store->remove($process->id);

        return $result !== null ? ProcessStatus::Completed : ProcessStatus::Failed;
    }

    /**
     * Check all tracked processes in a loop (daemon mode).
     *
     * @param int $intervalSeconds Seconds between each poll cycle
     * @param callable|null $shouldContinue Return false to stop the loop
     */
    public function watch(int $intervalSeconds = 5, ?callable $shouldContinue = null): void
    {
        $shouldContinue ??= fn () => true;

        while ($shouldContinue()) {
            $this->check();
            sleep($intervalSeconds);
        }
    }

    protected function fire(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
