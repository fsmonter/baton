<?php

declare(strict_types=1);

namespace Baton\Exceptions;

use Baton\Usage;

class ProcessFailedException extends BatonException
{
    public function __construct(
        public readonly string $processId,
        public readonly ?string $error = null,
        public readonly ?Usage $usage = null,
    ) {
        parent::__construct(
            $error ?? "Process [{$processId}] failed",
        );
    }
}
