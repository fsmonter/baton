<?php

declare(strict_types=1);

namespace Baton\Adapters;

use Baton\Contracts\OutputReader;
use Baton\Output\MessageEvent;
use Generator;

class NullOutputReader implements OutputReader
{
    public function __construct(
        protected array $events = [],
    ) {}

    public function events(): Generator
    {
        foreach ($this->events as $event) {
            yield $event;
        }
    }

    public function follow(): Generator
    {
        yield from $this->events();
    }

    public function tail(int $lines = 50): string
    {
        $messages = array_filter($this->events, fn ($e) => $e instanceof MessageEvent);
        $last = array_slice($messages, -$lines);

        return implode("\n", array_map(fn ($e) => $e->content, $last));
    }
}
