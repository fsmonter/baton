<?php

declare(strict_types=1);

namespace AgentSubprocess\Log;

use AgentSubprocess\Data\Usage;
use Illuminate\Contracts\Support\Arrayable;

final class LogPosition implements Arrayable
{
    public function __construct(
        public readonly int $position = 0,
        public readonly string $buffer = '',
        public readonly int $eventIndex = 0,
        public readonly array $seenToolIds = [],
        public readonly string $textBuffer = '',
        public readonly ?Usage $usage = null,
    ) {}

    public function withUsage(Usage $usage): self
    {
        return new self(
            position: $this->position,
            buffer: $this->buffer,
            eventIndex: $this->eventIndex,
            seenToolIds: $this->seenToolIds,
            textBuffer: $this->textBuffer,
            usage: $usage,
        );
    }

    public function withTextBuffer(string $textBuffer): self
    {
        return new self(
            position: $this->position,
            buffer: $this->buffer,
            eventIndex: $this->eventIndex,
            seenToolIds: $this->seenToolIds,
            textBuffer: $textBuffer,
            usage: $this->usage,
        );
    }

    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'buffer' => $this->buffer,
            'event_index' => $this->eventIndex,
            'seen_tool_ids' => $this->seenToolIds,
            'text_buffer' => $this->textBuffer,
            'usage' => $this->usage?->toArray(),
        ];
    }

    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            position: $data['position'] ?? 0,
            buffer: $data['buffer'] ?? '',
            eventIndex: $data['event_index'] ?? 0,
            seenToolIds: $data['seen_tool_ids'] ?? [],
            textBuffer: $data['text_buffer'] ?? '',
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
        );
    }
}
