<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class ResultEvent extends OutputEvent
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $subtype = null,
        public readonly ?array $structuredOutput = null,
        public readonly ?float $durationMs = null,
        public readonly ?int $numTurns = null,
        public readonly ?float $costUsd = null,
        public readonly ?array $usage = null,
        public readonly ?string $error = null,
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }

    public function hasStructuredOutput(): bool
    {
        return $this->structuredOutput !== null;
    }

    public function isError(): bool
    {
        return ! $this->success;
    }

    public function getDurationSeconds(): ?float
    {
        return $this->durationMs !== null ? round($this->durationMs / 1000, 2) : null;
    }
}
