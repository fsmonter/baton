<?php

declare(strict_types=1);

namespace Baton\Output;

class ToolResultEvent extends OutputEvent
{
    public function __construct(
        public readonly ?string $toolUseId,
        public readonly ?string $toolName,
        public readonly mixed $content = null,
        public readonly bool $isError = false,
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
