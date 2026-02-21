<?php

declare(strict_types=1);

namespace Baton\Adapters;

use Baton\Contracts\OutputReader;
use Baton\Output\OutputEvent;
use Baton\Parsers\ClaudeStreamParser;
use Generator;

class ClaudeCodeOutputReader implements OutputReader
{
    public function __construct(
        protected ?string $outputPath,
        protected ClaudeStreamParser $parser,
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

                $parsed = $this->parser->parse($line);
                $events = match (true) {
                    $parsed === null => [],
                    $parsed instanceof OutputEvent => [$parsed],
                    is_array($parsed) => $parsed,
                    default => [],
                };

                foreach ($events as $event) {
                    yield $event;
                }
            }

            foreach ($this->parser->flush() as $event) {
                yield $event;
            }
        } finally {
            fclose($handle);
        }
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
