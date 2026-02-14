<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\ProcessStore;
use Baton\Events\ProcessCompleted;
use Baton\Events\ProcessFailed;
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
        $status = $process->status();

        if ($status === ProcessStatus::Running) {
            return $status;
        }

        // Process finished — resolve result and fire events
        $result = $process->result();

        if ($status === ProcessStatus::Completed && $result !== null) {
            $this->fire(new ProcessCompleted($process, $result));
        } else {
            $error = $result?->error ?? 'Process terminated unexpectedly';
            $this->fire(new ProcessFailed($process, $result, $error));
        }

        // Remove from store — it's done
        $this->store->remove($process->id);

        return $status;
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
