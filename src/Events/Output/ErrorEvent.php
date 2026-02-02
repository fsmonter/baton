<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class ErrorEvent extends OutputEvent
{
    public function __construct(
        public readonly string $message,
        public readonly ?int $code = null,
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
