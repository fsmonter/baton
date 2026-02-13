<?php

declare(strict_types=1);

namespace Baton;

final class Result
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $output = null,
        public readonly ?array $structured = null,
        public readonly ?Usage $usage = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $error = null,
    ) {}
}
