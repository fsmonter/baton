<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

use Carbon\Carbon;

abstract class OutputEvent
{
    public Carbon $timestamp;

    public function __construct(
        public readonly ?string $raw = null,
    ) {
        $this->timestamp = Carbon::now();
    }
}
