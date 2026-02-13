<?php

declare(strict_types=1);

namespace Baton\Events;

use Baton\Process;
use Baton\Result;

class ProcessCompleted
{
    public function __construct(
        public readonly Process $process,
        public readonly Result $result,
    ) {}
}
