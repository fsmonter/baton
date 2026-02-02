<?php

declare(strict_types=1);

namespace AgentSubprocess\Data;

use Illuminate\Contracts\Support\Arrayable;

final class TerminationResult implements Arrayable
{
    public function __construct(
        public readonly bool $success,
        public readonly string $method,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'method' => $this->method,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            method: $data['method'],
        );
    }
}
