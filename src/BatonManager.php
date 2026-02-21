<?php

declare(strict_types=1);

namespace Baton;

use Baton\Adapters\ClaudeCodeAdapter;
use Baton\Adapters\NullAdapter;
use Baton\Contracts\AgentAdapter;
use Baton\Contracts\ProcessStore;
use Baton\Events\ProcessSpawned;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;

class BatonManager
{
    /** @var array<string, AgentAdapter> */
    protected array $agents = [];

    /** @var array<string, Closure> */
    protected array $customCreators = [];

    protected ?ProcessStore $store = null;

    protected ?Dispatcher $events = null;

    public function __construct(
        protected array $config = [],
    ) {}

    // --- Fluent API ---

    public function prompt(string $prompt): PendingProcess
    {
        return (new PendingProcess($this))->prompt($prompt);
    }

    /**
     * Shorthand: spawn a process with just a prompt string.
     */
    public function spawn(string $prompt): Process
    {
        return $this->prompt($prompt)->spawn();
    }

    /**
     * Spawn a process from a PendingProcess, persist it, and fire events.
     *
     * Called by PendingProcess::spawn() — this is the central spawn path.
     */
    public function spawnProcess(PendingProcess $pending): Process
    {
        $agent = $this->agent($pending->getAgentName());
        $process = $agent->spawn($pending);

        $this->store?->save($process->id, $process->toArray());
        $this->fire(new ProcessSpawned($process));

        return $process;
    }

    // --- Store integration ---

    /**
     * Find a tracked process by ID.
     */
    public function find(string $id): ?Process
    {
        if (! $this->store) {
            return null;
        }

        $data = $this->store->get($id);

        if ($data === null) {
            return null;
        }

        return $this->hydrate($data);
    }

    /**
     * Get all tracked (active) processes.
     *
     * @return Process[]
     */
    public function active(): array
    {
        if (! $this->store) {
            return [];
        }

        $processes = [];

        foreach ($this->store->all() as $data) {
            $process = $this->hydrate($data);

            if ($process !== null) {
                $processes[] = $process;
            }
        }

        return $processes;
    }

    /**
     * Create a ProcessMonitor for checking tracked processes.
     */
    public function monitor(): ProcessMonitor
    {
        if (! $this->store) {
            throw new \RuntimeException(
                'ProcessStore is required for monitoring. Configure a store in baton.store or call setStore().'
            );
        }

        return new ProcessMonitor($this, $this->store, $this->events);
    }

    // --- Agent management ---

    public function agent(?string $name = null): AgentAdapter
    {
        $name ??= $this->getDefaultAgent();

        return $this->agents[$name] ??= $this->resolve($name);
    }

    public function extend(string $name, Closure $callback): static
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function getDefaultAgent(): string
    {
        return $this->config['default'] ?? 'claude-code';
    }

    // --- Configuration ---

    public function setStore(ProcessStore $store): static
    {
        $this->store = $store;

        return $this;
    }

    public function getStore(): ?ProcessStore
    {
        return $this->store;
    }

    public function setEventDispatcher(Dispatcher $events): static
    {
        $this->events = $events;

        return $this;
    }

    // --- Internal ---

    protected function resolve(string $name): AgentAdapter
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->getAgentConfig($name));
        }

        return match ($name) {
            'claude-code' => new ClaudeCodeAdapter($this->getAgentConfig($name)),
            'null' => new NullAdapter($this->getAgentConfig($name)),
            default => throw new InvalidArgumentException("Agent [{$name}] is not supported."),
        };
    }

    protected function getAgentConfig(string $name): array
    {
        return $this->config['agents'][$name] ?? [];
    }

    protected function hydrate(array $data): ?Process
    {
        $agentName = $data['agent'] ?? null;

        if (! $agentName) {
            return null;
        }

        try {
            $agent = $this->agent($agentName);
        } catch (InvalidArgumentException) {
            return null;
        }

        return Process::fromArray($data, $agent);
    }

    protected function fire(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
