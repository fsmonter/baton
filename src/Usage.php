<?php

declare(strict_types=1);

namespace Baton;

final class Usage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly ?float $cost = null,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function merge(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $other->cacheWriteTokens,
            cost: $this->cost !== null || $other->cost !== null
                ? ($this->cost ?? 0) + ($other->cost ?? 0)
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
            'cost' => $this->cost,
        ];
    }

    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self();
        }

        return new self(
            inputTokens: $data['input_tokens'] ?? 0,
            outputTokens: $data['output_tokens'] ?? 0,
            cacheReadTokens: $data['cache_read_tokens'] ?? 0,
            cacheWriteTokens: $data['cache_write_tokens'] ?? 0,
            cost: $data['cost'] ?? $data['cost_usd'] ?? null,
        );
    }
}
