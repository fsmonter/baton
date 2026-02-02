<?php

declare(strict_types=1);

namespace AgentSubprocess\Data;

use Illuminate\Contracts\Support\Arrayable;

final class ContextLayer implements Arrayable
{
    public function __construct(
        public readonly mixed $content,
        public readonly int $priority = 0,
        public readonly ?string $label = null,
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'priority' => $this->priority,
            'label' => $this->label,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? null,
            priority: $data['priority'] ?? 0,
            label: $data['label'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
