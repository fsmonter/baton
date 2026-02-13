<?php

declare(strict_types=1);

namespace Baton\Events;

use Baton\Process;
use Baton\Result;

class ProcessFailed
{
    public function __construct(
        public readonly Process $process,
        public readonly ?Result $result = null,
        public readonly ?string $error = null,
    ) {}
}
