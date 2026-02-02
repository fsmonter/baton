<?php

declare(strict_types=1);

namespace AgentSubprocess\Log;

use AgentSubprocess\Events\Output\OutputEvent;

final class LogReadResult
{
    public function __construct(
        public readonly LogPosition $position,
        /** @var array<OutputEvent> */
        public readonly array $events,
    ) {}

    public function hasEvents(): bool
    {
        return count($this->events) > 0;
    }
}
