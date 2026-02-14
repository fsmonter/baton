<?php

declare(strict_types=1);

namespace Baton\Events;

use Baton\Process;
use Baton\Usage;

class ProcessFailed
{
    public function __construct(
        public readonly Process $process,
        public readonly ?string $error = null,
        public readonly ?Usage $usage = null,
    ) {}
}
