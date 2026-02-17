<?php

declare(strict_types=1);

namespace Baton\Exceptions;

class ProcessCrashedException extends BatonException
{
    public function __construct(
        public readonly string $processId,
        public readonly int $exitCode,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Process [{$processId}] crashed with exit code {$exitCode}",
        );
    }
}
