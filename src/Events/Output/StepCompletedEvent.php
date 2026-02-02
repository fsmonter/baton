<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class StepCompletedEvent extends OutputEvent
{
    public function __construct(
        public readonly string $stepName,
        public readonly array $result = [],
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
