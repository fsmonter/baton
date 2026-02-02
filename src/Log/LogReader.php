<?php

declare(strict_types=1);

namespace AgentSubprocess\Log;

use AgentSubprocess\Contracts\OutputParser;
use AgentSubprocess\Events\Output\OutputEvent;
use AgentSubprocess\Events\Output\ToolUseEvent;
use Generator;

class LogReader
{
    public function __construct(
        protected OutputParser $parser,
    ) {}

    public function readIncremental(string $path, LogPosition $position): LogReadResult
    {
        if (! file_exists($path)) {
            return new LogReadResult($position, []);
        }

        $fileSize = filesize($path);

        if ($fileSize <= $position->position) {
            return new LogReadResult($position, []);
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            return new LogReadResult($position, []);
        }

        fseek($handle, $position->position);

        $newContent = fread($handle, $fileSize - $position->position);
        $newPosition = ftell($handle);

        fclose($handle);

        $content = $position->buffer.$newContent;
        $lines = explode("\n", $content);

        $remainingBuffer = '';
        if (! str_ends_with($content, "\n")) {
            $remainingBuffer = array_pop($lines);
        }

        $events = [];
        $newToolIds = [];
        $eventIndex = $position->eventIndex;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parsed = $this->parser->parse($line);

            $lineEvents = match (true) {
                $parsed === null => [],
                $parsed instanceof OutputEvent => [$parsed],
                is_array($parsed) => $parsed,
                default => [],
            };

            foreach ($lineEvents as $event) {
                if ($event instanceof OutputEvent) {
                    $events[] = $event;
                    $eventIndex++;

                    if ($event instanceof ToolUseEvent && $event->hasToolId()) {
                        if (! in_array($event->toolId, $position->seenToolIds, true)) {
                            $newToolIds[] = $event->toolId;
                        }
                    }
                }
            }
        }

        $newLogPosition = new LogPosition(
            position: $newPosition,
            buffer: $remainingBuffer,
            eventIndex: $eventIndex,
            seenToolIds: [...$position->seenToolIds, ...$newToolIds],
            textBuffer: $position->textBuffer,
            usage: $this->parser->getUsage() ?? $position->usage,
        );

        return new LogReadResult($newLogPosition, $events);
    }

    public function readAll(string $path): Generator
    {
        if (! file_exists($path)) {
            return;
        }

        $this->parser->reset();

        $handle = fopen($path, 'r');

        if (! $handle) {
            return;
        }

        try {
            while (! feof($handle)) {
                $line = fgets($handle);

                if ($line === false || trim($line) === '') {
                    continue;
                }

                $parsed = $this->parser->parse($line);

                $events = match (true) {
                    $parsed === null => [],
                    $parsed instanceof OutputEvent => [$parsed],
                    is_array($parsed) => $parsed,
                    default => [],
                };

                foreach ($events as $event) {
                    if ($event instanceof OutputEvent) {
                        yield $event;
                    }
                }
            }

            foreach ($this->parser->flush() as $event) {
                yield $event;
            }
        } finally {
            fclose($handle);
        }
    }
}
