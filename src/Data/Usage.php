<?php

declare(strict_types=1);

namespace AgentSubprocess\Data;

use Illuminate\Contracts\Support\Arrayable;

final class Usage implements Arrayable
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly ?float $costUsd = null,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function hasCost(): bool
    {
        return $this->costUsd !== null;
    }

    public function merge(Usage $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $other->cacheWriteTokens,
            costUsd: $this->costUsd !== null || $other->costUsd !== null
                ? ($this->costUsd ?? 0) + ($other->costUsd ?? 0)
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'cost_usd' => $this->costUsd,
        ];
    }

    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            inputTokens: $data['input_tokens'] ?? 0,
            outputTokens: $data['output_tokens'] ?? 0,
            cacheReadTokens: $data['cache_read_tokens'] ?? 0,
            cacheWriteTokens: $data['cache_write_tokens'] ?? 0,
            costUsd: $data['cost_usd'] ?? null,
        );
    }
}
