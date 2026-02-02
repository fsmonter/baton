<?php

declare(strict_types=1);

namespace AgentSubprocess\Events\Output;

class SystemEvent extends OutputEvent
{
    public function __construct(
        public readonly string $model,
        public readonly array $tools = [],
        public readonly array $mcpServers = [],
        public readonly ?string $version = null,
        public readonly array $meta = [],
        ?string $raw = null,
    ) {
        parent::__construct($raw);
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'tools' => $this->tools,
            'mcp_servers' => $this->mcpServers,
            'version' => $this->version,
            'meta' => $this->meta,
        ];
    }
}
