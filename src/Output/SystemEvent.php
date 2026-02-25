<?php

declare(strict_types=1);

namespace Baton\Output;

class SystemEvent extends OutputEvent
{
    public function __construct(
        public readonly string $model,
        public readonly ?string $version = null,
        public readonly array $tools = [],
        public readonly array $mcpServers = [],
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }
}
