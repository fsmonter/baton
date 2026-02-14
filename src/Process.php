<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\Driver;
use Baton\Contracts\OutputReader;
use Baton\Exceptions\BatonException;
use Carbon\Carbon;

class Process
{
    public function __construct(
        public readonly string $id,
        public readonly string $driver,
        public readonly array $handle,
        public readonly string $workDir,
        public readonly Carbon $startedAt,
        protected Driver $driverInstance,
    ) {}

    public function isRunning(): bool
    {
        return $this->driverInstance->isRunning($this);
    }

    /**
     * @throws \Baton\Exceptions\ProcessFailedException
     * @throws \Baton\Exceptions\ProcessCrashedException
     */
    public function result(): ?Result
    {
        return $this->driverInstance->getResult($this);
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
        return $this->driverInstance->readOutput($this);
    }

    public function kill(): bool
    {
        return $this->driverInstance->terminate($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver,
            'handle' => $this->handle,
            'work_dir' => $this->workDir,
            'started_at' => $this->startedAt->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, Driver $driverInstance): static
    {
        return new static(
            id: $data['id'],
            driver: $data['driver'],
            handle: $data['handle'],
            workDir: $data['work_dir'],
            startedAt: Carbon::parse($data['started_at']),
            driverInstance: $driverInstance,
        );
    }
}
