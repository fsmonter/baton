<?php

declare(strict_types=1);

namespace Baton\Output;

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
}
