<?php

declare(strict_types=1);

namespace AgentSubprocess\Contracts;

use AgentSubprocess\Data\Usage;
use AgentSubprocess\Events\Output\OutputEvent;

interface OutputParser
{
    /**
     * Parse a single line of output.
     *
     * @return OutputEvent|array<OutputEvent>|null
     */
    public function parse(string $line): OutputEvent|array|null;

    /**
     * Flush any buffered content as events.
     *
     * @return array<OutputEvent>
     */
    public function flush(): array;

    /**
     * Reset parser state.
     */
    public function reset(): void;

    /**
     * Get accumulated usage statistics.
     */
    public function getUsage(): ?Usage;

    public function supportsStreaming(): bool;
}
