<?php

declare(strict_types=1);

namespace AgentSubprocess\Data;

use Illuminate\Contracts\Support\Arrayable;

final class AgentConfig implements Arrayable
{
    public function __construct(
        public readonly ?string $model = null,
        public readonly array $tools = [],
        public readonly array $allowedCommands = [],
        public readonly ?int $maxTurns = null,
        public readonly ?int $timeout = null,
        public readonly array $extra = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'model' => $this->model,
            'tools' => $this->tools,
            'allowed_commands' => $this->allowedCommands,
            'max_turns' => $this->maxTurns,
            'timeout' => $this->timeout,
            ...$this->extra,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            model: $data['model'] ?? null,
            tools: $data['tools'] ?? [],
            allowedCommands: $data['allowed_commands'] ?? [],
            maxTurns: $data['max_turns'] ?? null,
            timeout: $data['timeout'] ?? null,
            extra: array_diff_key($data, array_flip(['model', 'tools', 'allowed_commands', 'max_turns', 'timeout'])),
        );
    }
}
