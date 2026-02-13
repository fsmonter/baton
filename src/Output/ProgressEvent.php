<?php

declare(strict_types=1);

namespace Baton\Output;

class ProgressEvent extends OutputEvent
{
    public function __construct(
        public readonly int $percent,
        public readonly ?string $activity = null,
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
