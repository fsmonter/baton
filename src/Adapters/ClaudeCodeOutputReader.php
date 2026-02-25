<?php

declare(strict_types=1);

namespace Baton\Adapters;

use Baton\Contracts\OutputReader;
use Baton\Output\OutputEvent;
use Baton\Parsers\ClaudeStreamParser;
use Closure;
use Generator;

class ClaudeCodeOutputReader implements OutputReader
{
    public function __construct(
        protected ?string $outputPath,
        protected ClaudeStreamParser $parser,
        protected ?Closure $isRunning = null,
    ) {}

    public function events(): Generator
    {
        if (! $this->outputPath || ! file_exists($this->outputPath)) {
            return;
        }

        $this->parser->reset();

        $handle = fopen($this->outputPath, 'r');
        if (! $handle) {
            return;
        }

        try {
            while (! feof($handle)) {
                $line = fgets($handle);
                if ($line === false || trim($line) === '') {
                    continue;
                }

                yield from $this->yieldParsed($line);
            }

        } finally {
            fclose($handle);
        }
    }

    public function follow(): Generator
    {
        if (! $this->outputPath) {
            return;
        }

        $this->parser->reset();

        while (! file_exists($this->outputPath)) {
            if ($this->isRunning && ! ($this->isRunning)()) {
                return;
            }
            usleep(100_000);
        }

        $handle = fopen($this->outputPath, 'r');
        if (! $handle) {
            return;
        }

        try {
            while (true) {
                $line = fgets($handle);

                if ($line !== false && trim($line) !== '') {
                    yield from $this->yieldParsed($line);
                    continue;
                }

                // Clear PHP's internal EOF flag so fgets() can see new data
                fseek($handle, ftell($handle));

                if ($this->isRunning && ! ($this->isRunning)()) {
                    break;
                }

                // Yield null as idle tick so consumers can show progress
                yield null;

                usleep(100_000);
            }

            // Final read — drain remaining lines after process exits
            fseek($handle, ftell($handle));
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    yield from $this->yieldParsed($line);
                }
            }

        } finally {
            fclose($handle);
        }
    }

    /** @return iterable<OutputEvent> */
    protected function yieldParsed(string $line): iterable
    {
        $parsed = $this->parser->parse($line);

        return match (true) {
            $parsed === null => [],
            $parsed instanceof OutputEvent => [$parsed],
            is_array($parsed) => $parsed,
            default => [],
        };
    }

    public function tail(int $lines = 50): string
    {
        if (! $this->outputPath || ! file_exists($this->outputPath)) {
            return '';
        }

        $file = new \SplFileObject($this->outputPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0) {
            return '';
        }

        $startLine = max(0, $totalLines - $lines);
        $output = [];

        $file->seek($startLine);
        while (! $file->eof()) {
            $line = $file->current();
            if (trim($line) !== '') {
                $output[] = $line;
            }
            $file->next();
        }

        return implode('', $output);
    }
}
