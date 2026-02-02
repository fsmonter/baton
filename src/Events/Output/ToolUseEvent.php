<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class ToolUseEvent extends OutputEvent
{
    public function __construct(
        public readonly string $tool,
        public readonly array $input = [],
        public readonly mixed $output = null,
        public readonly ?string $toolId = null,
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }

    public function hasOutput(): bool
    {
        return $this->output !== null;
    }

    public function hasToolId(): bool
    {
        return $this->toolId !== null;
    }
}
