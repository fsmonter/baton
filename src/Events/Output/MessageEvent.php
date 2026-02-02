<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class MessageEvent extends OutputEvent
{
    public function __construct(
        public readonly string $content,
        public readonly string $role = 'assistant',
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
