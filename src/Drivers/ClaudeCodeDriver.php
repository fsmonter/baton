<?php

declare(strict_types=1);

namespace Baton\Drivers;

use Baton\Contracts\Driver;
use Baton\Contracts\OutputReader;
use Baton\Output\ResultEvent;
use Baton\Parsers\ClaudeStreamParser;
use Baton\PendingProcess;
use Baton\Process;
use Baton\Result;
use Baton\Usage;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Str;

class ClaudeCodeDriver implements Driver
{
    protected ClaudeStreamParser $parser;

    public function __construct(
        protected array $config = [],
    ) {
        $this->parser = new ClaudeStreamParser();
    }

    public function spawn(PendingProcess $pending): Process
    {
        $id = Str::uuid()->toString();
        $workDir = $pending->getDirectory() ?? $this->config['work_dir'] ?? getcwd();
        $outputPath = $this->getOutputPath($id);

        File::ensureDirectoryExists(dirname($outputPath));

        $promptPath = $this->writePromptFile($id, $pending->getPrompt() ?? '');
        $schemaPath = $pending->getSchema() ? $this->writeSchemaFile($id, $pending->getSchema()) : null;
        $wrapperScript = $this->createWrapperScript($id, $pending, $workDir, $promptPath, $outputPath, $schemaPath);

        $pid = $this->executeInBackground($wrapperScript);

        $process = new Process(
            id: $id,
            driver: 'claude-code',
            handle: [
                'pid' => (int) $pid,
                'output_path' => $outputPath,
                'prompt_path' => $promptPath,
                'schema_path' => $schemaPath,
                'wrapper_script' => $wrapperScript,
            ],
            workDir: $workDir,
            startedAt: Carbon::now(),
            driverInstance: $this,
        );

        return $process;
    }

    public function isRunning(Process $process): bool
    {
        $pid = $process->handle['pid'] ?? null;

        if (! $pid) {
            return false;
        }

        return posix_kill($pid, 0);
    }

    public function terminate(Process $process): bool
    {
        $pid = $process->handle['pid'] ?? null;

        if (! $pid) {
            return false;
        }

        return posix_kill($pid, SIGTERM);
    }

    public function readOutput(Process $process): OutputReader
    {
        return new ClaudeCodeOutputReader(
            $process->handle['output_path'] ?? null,
            $this->parser,
        );
    }

    public function getResult(Process $process): ?Result
    {
        $outputPath = $process->handle['output_path'] ?? null;

        if (! $outputPath || ! file_exists($outputPath)) {
            return null;
        }

        $lines = file($outputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach (array_reverse($lines) as $line) {
            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (($data['type'] ?? null) !== 'result') {
                continue;
            }

            $subtype = $data['subtype'] ?? 'success';
            $isError = ($data['is_error'] ?? false) || $subtype === 'error_max_structured_output_retries';

            $resultUsage = $data['usage'] ?? [];
            $usage = new Usage(
                inputTokens: $resultUsage['input_tokens'] ?? 0,
                outputTokens: $resultUsage['output_tokens'] ?? 0,
                cacheReadTokens: $resultUsage['cache_read_input_tokens'] ?? 0,
                cacheWriteTokens: $resultUsage['cache_creation_input_tokens'] ?? 0,
                cost: $data['total_cost_usd'] ?? null,
            );

            // Collect text output from all message events
            $textOutput = $this->collectTextOutput($lines);

            return new Result(
                success: ! $isError,
                output: $textOutput,
                structured: $data['structured_output'] ?? null,
                usage: $usage,
                durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
                error: $isError ? ($data['error'] ?? $subtype) : null,
            );
        }

        return null;
    }

    public function capabilities(): array
    {
        return [
            'streaming' => true,
            'tools' => true,
            'structured_output' => true,
            'models' => ['haiku', 'sonnet', 'opus'],
            'max_turns' => true,
        ];
    }

    protected function collectTextOutput(array $lines): ?string
    {
        $texts = [];

        foreach ($lines as $line) {
            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $type = $data['type'] ?? null;

            if ($type === 'assistant') {
                foreach (($data['message']['content'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $texts[] = $block['text'] ?? '';
                    }
                }
            }
        }

        return $texts ? implode("\n", $texts) : null;
    }

    protected function writePromptFile(string $id, string $prompt): string
    {
        $promptDir = $this->config['prompt_dir'] ?? storage_path('baton/prompts');
        File::ensureDirectoryExists($promptDir);

        $path = "{$promptDir}/{$id}.txt";
        file_put_contents($path, $prompt);

        return $path;
    }

    protected function writeSchemaFile(string $id, array $schema): string
    {
        $schemaDir = $this->config['schema_dir'] ?? storage_path('baton/schemas');
        File::ensureDirectoryExists($schemaDir);

        $path = "{$schemaDir}/{$id}.json";
        file_put_contents($path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    protected function createWrapperScript(
        string $id,
        PendingProcess $pending,
        string $workDir,
        string $promptPath,
        string $outputPath,
        ?string $schemaPath,
    ): string {
        $scriptDir = $this->config['script_dir'] ?? storage_path('baton/scripts');
        File::ensureDirectoryExists($scriptDir);

        $scriptPath = "{$scriptDir}/{$id}.sh";
        $binary = $this->config['binary'] ?? 'claude';
        $command = $this->buildCommandString($pending, $schemaPath);

        $script = <<<BASH
#!/bin/bash
cd {$workDir}
exec {$binary} {$command} < {$promptPath} > {$outputPath} 2>&1
BASH;

        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    protected function buildCommandString(PendingProcess $pending, ?string $schemaPath): string
    {
        $parts = ['-p', '--verbose', '--output-format', 'stream-json'];

        if ($this->config['include_partial_messages'] ?? true) {
            $parts[] = '--include-partial-messages';
        }

        $parts[] = '--setting-sources';
        $parts[] = escapeshellarg($this->config['setting_sources'] ?? 'local');

        if ($this->config['disable_slash_commands'] ?? true) {
            $parts[] = '--disable-slash-commands';
        }

        if ($this->config['no_session_persistence'] ?? true) {
            $parts[] = '--no-session-persistence';
        }

        $tools = $pending->getTools() ?? ($this->config['tools'] ?? null);
        if ($tools) {
            $parts[] = '--tools';
            $parts[] = escapeshellarg(is_array($tools) ? implode(',', $tools) : $tools);
        }

        if ($schemaPath) {
            $parts[] = '--json-schema';
            $parts[] = '"$(cat '.escapeshellarg($schemaPath).')"';
        }

        $maxTurns = $pending->getMaxTurns() ?? ($this->config['max_turns'] ?? null);
        if ($maxTurns) {
            $parts[] = '--max-turns';
            $parts[] = (string) $maxTurns;
        }

        $model = $pending->getModel() ?? ($this->config['model'] ?? null);
        if ($model) {
            $parts[] = '--model';
            $parts[] = escapeshellarg($model);
        }

        return implode(" \\\n    ", $parts);
    }

    protected function executeInBackground(string $scriptPath): string
    {
        $command = sprintf('nohup %s & echo $!', escapeshellarg($scriptPath));
        $result = LaravelProcess::run($command);
        $pid = trim($result->output());

        if (empty($pid) || ! is_numeric($pid)) {
            throw new \RuntimeException('Failed to spawn agent process: '.$result->errorOutput());
        }

        return $pid;
    }

    protected function getOutputPath(string $id): string
    {
        $basePath = $this->config['output_dir'] ?? storage_path('baton/output');

        return "{$basePath}/{$id}.jsonl";
    }
}
