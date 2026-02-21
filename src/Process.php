<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\AgentAdapter;
use Baton\Contracts\OutputReader;
use Baton\Exceptions\BatonException;
use Carbon\Carbon;

class Process
{
    public function __construct(
        public readonly string $id,
        public readonly string $adapter,
        public readonly array $handle,
        public readonly string $workDir,
        public readonly Carbon $startedAt,
        protected AgentAdapter $adapterInstance,
    ) {}

    public function isRunning(): bool
    {
        return $this->adapterInstance->isRunning($this);
    }

    /**
     * @throws \Baton\Exceptions\ProcessFailedException
     * @throws \Baton\Exceptions\ProcessCrashedException
     */
    public function result(): ?Result
    {
        return $this->adapterInstance->getResult($this);
    }

    public function status(): ProcessStatus
    {
        if ($this->isRunning()) {
            return ProcessStatus::Running;
        }

        try {
            $result = $this->result();
        } catch (BatonException) {
            return ProcessStatus::Failed;
        }

        return $result !== null ? ProcessStatus::Completed : ProcessStatus::Failed;
    }

    public function output(): OutputReader
    {
        return $this->adapterInstance->readOutput($this);
    }

    public function kill(): bool
    {
        return $this->adapterInstance->terminate($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'adapter' => $this->adapter,
            'handle' => $this->handle,
            'work_dir' => $this->workDir,
            'started_at' => $this->startedAt->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, AgentAdapter $adapterInstance): static
    {
        return new static(
            id: $data['id'],
            adapter: $data['adapter'],
            handle: $data['handle'],
            workDir: $data['work_dir'],
            startedAt: Carbon::parse($data['started_at']),
            adapterInstance: $adapterInstance,
        );
    }
}
