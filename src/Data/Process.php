<?php

declare(strict_types=1);

namespace AgentSubprocess\Data;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

final class Process implements Arrayable
{
    public function __construct(
        public readonly ?int $pid,
        public readonly string $driver,
        public readonly array $command,
        public readonly string $workDir,
        public readonly ?string $outputPath = null,
        public readonly ?string $logPath = null,
        public readonly ?Carbon $startedAt = null,
        public readonly array $meta = [],
    ) {}

    public function isStarted(): bool
    {
        return $this->pid !== null;
    }

    public function toArray(): array
    {
        return [
            'pid' => $this->pid,
            'driver' => $this->driver,
            'command' => $this->command,
            'work_dir' => $this->workDir,
            'output_path' => $this->outputPath,
            'log_path' => $this->logPath,
            'started_at' => $this->startedAt?->toIso8601String(),
            'meta' => $this->meta,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            pid: $data['pid'] ?? null,
            driver: $data['driver'],
            command: $data['command'] ?? [],
            workDir: $data['work_dir'] ?? getcwd(),
            outputPath: $data['output_path'] ?? null,
            logPath: $data['log_path'] ?? null,
            startedAt: isset($data['started_at']) ? Carbon::parse($data['started_at']) : null,
            meta: $data['meta'] ?? [],
        );
    }
}
