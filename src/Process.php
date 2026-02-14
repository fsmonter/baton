<?php

declare(strict_types=1);

namespace Baton;

use Baton\Contracts\Driver;
use Baton\Contracts\OutputReader;
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

    public function status(): ProcessStatus
    {
        if ($this->isRunning()) {
            return ProcessStatus::Running;
        }

        $result = $this->result();

        if ($result === null) {
            return ProcessStatus::Failed;
        }

        return $result->success ? ProcessStatus::Completed : ProcessStatus::Failed;
    }

    public function output(): OutputReader
    {
        return $this->driverInstance->readOutput($this);
    }

    public function result(): ?Result
    {
        return $this->driverInstance->getResult($this);
    }

    public function kill(): bool
    {
        return $this->driverInstance->terminate($this);
    }
}
