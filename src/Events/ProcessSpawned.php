<?php

declare(strict_types=1);

namespace Baton\Events;

use Baton\Process;

class ProcessSpawned
{
    public function __construct(
        public readonly Process $process,
    ) {}
}
