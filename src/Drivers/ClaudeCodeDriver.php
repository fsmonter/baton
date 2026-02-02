<?php

declare(strict_types=1);

namespace AgentSubprocess\Drivers;

use AgentSubprocess\Contracts\AgentDriver;
use AgentSubprocess\Contracts\OutputParser;
use AgentSubprocess\Data\Process;
use AgentSubprocess\Data\TerminationResult;
use AgentSubprocess\Events\Output\OutputEvent;
use AgentSubprocess\Events\Output\ResultEvent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReader;
use AgentSubprocess\Log\LogReadResult;
use AgentSubprocess\Models\AgentRun;
use AgentSubprocess\Parsers\ClaudeStreamParser;
use AgentSubprocess\Process\ProcessTerminator;
use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process as LaravelProcess;
use SplFileObject;

class ClaudeCodeDriver implements AgentDriver
{
    protected ClaudeStreamParser $parser;

    public function __construct(
        protected array $config = [],
    ) {
        $this->parser = new ClaudeStreamParser;
    }

    public function spawn(AgentRun $run): Process
    {
        $workDir = $run->config['work_dir'] ?? $this->config['work_dir'] ?? getcwd();
        $outputPath = $this->getOutputPath($run);
        $logPath = $this->getLogPath($run);

        File::ensureDirectoryExists(dirname($outputPath));
        File::ensureDirectoryExists(dirname($logPath));

        $promptPath = $this->writePromptFile($run);
        $schemaPath = $this->writeSchemaFile($run);
        $wrapperScript = $this->createWrapperScript($run, $workDir, $promptPath, $outputPath, $schemaPath);

        $pid = $this->executeInBackground($wrapperScript);

        return new Process(
            pid: (int) $pid,
            driver: 'claude-code',
            command: [$wrapperScript],
            workDir: $workDir,
            outputPath: $outputPath,
            logPath: $logPath,
            startedAt: Carbon::now(),
            meta: [
                'model' => $run->config['model'] ?? $this->config['model'] ?? 'sonnet',
                'prompt_path' => $promptPath,
                'schema_path' => $schemaPath,
                'wrapper_script' => $wrapperScript,
            ],
        );
    }

    public function isRunning(Process $process): bool
    {
        if (! $process->pid) {
            return false;
        }

        return posix_kill($process->pid, 0);
    }

    public function terminate(Process $process): bool
    {
        if (! $process->pid) {
            return false;
        }

        return posix_kill($process->pid, SIGTERM);
    }

    public function terminateGracefully(Process $process, int $gracePeriodMs = 500): TerminationResult
    {
        if (! $process->pid) {
            return new TerminationResult(true, 'no_pid');
        }

        $terminator = new ProcessTerminator;

        return $terminator->terminate($process->pid, $gracePeriodMs);
    }

    public function parseOutput(Process $process): Generator
    {
        if (! $process->outputPath || ! file_exists($process->outputPath)) {
            return;
        }

        $this->parser->reset();

        $handle = fopen($process->outputPath, 'r');

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

    public function readLogIncremental(Process $process, LogPosition $position): LogReadResult
    {
        if (! $process->outputPath) {
            return new LogReadResult($position, []);
        }

        $reader = new LogReader($this->parser);

        return $reader->readIncremental($process->outputPath, $position);
    }

    public function getLiveOutput(Process $process, int $lines = 50): string
    {
        if (! $process->outputPath || ! file_exists($process->outputPath)) {
            return '';
        }

        $file = new SplFileObject($process->outputPath, 'r');
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

    public function getParser(): OutputParser
    {
        return $this->parser;
    }

    public function getCapabilities(): array
    {
        return [
            'streaming' => true,
            'tools' => true,
            'structured_output' => true,
            'models' => ['haiku', 'sonnet', 'opus'],
            'max_turns' => true,
            'background_execution' => true,
        ];
    }

    public function extractStructuredOutput(Process $process): ?array
    {
        if (! $process->outputPath || ! file_exists($process->outputPath)) {
            return null;
        }

        $lines = file($process->outputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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
            $structuredOutput = $data['structured_output'] ?? null;

            if ($subtype === 'error_max_structured_output_retries') {
                return [
                    'success' => false,
                    'error' => 'max_structured_output_retries',
                    'data' => null,
                ];
            }

            if ($structuredOutput && $subtype === 'success') {
                return [
                    'success' => true,
                    'error' => null,
                    'data' => $structuredOutput,
                ];
            }

            if ($subtype === 'success' && ! $structuredOutput) {
                return [
                    'success' => false,
                    'error' => 'no_structured_output',
                    'data' => null,
                ];
            }
        }

        return null;
    }

    public function parseCurrentActivity(Process $process, int $tailLines = 50): array
    {
        if (! $process->outputPath || ! file_exists($process->outputPath)) {
            return [
                'status' => 'unknown',
                'activity' => 'Log file not found',
            ];
        }

        $output = $this->getLiveOutput($process, $tailLines);
        $lines = array_filter(explode("\n", $output));

        $currentActivity = 'Initializing...';
        $status = 'running';

        foreach (array_reverse($lines) as $line) {
            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $type = $data['type'] ?? null;

            if ($type === 'result') {
                $isError = $data['is_error'] ?? false;
                $status = $isError ? 'failed' : 'completed';
                $currentActivity = $isError ? 'Failed with error' : 'Completed successfully';
                break;
            }

            if ($type === 'tool_use') {
                $currentActivity = 'Using tool: '.($data['name'] ?? 'unknown');
                break;
            }

            if ($type === 'tool_result') {
                $currentActivity = 'Completed: '.($data['tool_name'] ?? 'tool');
                break;
            }

            if ($type === 'stream_event') {
                $eventType = $data['event']['type'] ?? null;
                if ($eventType === 'content_block_delta') {
                    $currentActivity = 'Writing response...';
                    break;
                }
            }
        }

        return [
            'status' => $status,
            'activity' => $currentActivity,
        ];
    }

    protected function writePromptFile(AgentRun $run): string
    {
        $promptDir = $this->config['prompt_dir'] ?? storage_path('agent-subprocess/prompts');
        File::ensureDirectoryExists($promptDir);

        $promptPath = "{$promptDir}/{$run->id}.txt";
        file_put_contents($promptPath, $run->prompt);

        return $promptPath;
    }

    protected function writeSchemaFile(AgentRun $run): ?string
    {
        $schema = $run->config['json_schema'] ?? null;

        if (! $schema) {
            return null;
        }

        $schemaDir = $this->config['schema_dir'] ?? storage_path('agent-subprocess/schemas');
        File::ensureDirectoryExists($schemaDir);

        $schemaPath = "{$schemaDir}/{$run->id}.json";
        file_put_contents($schemaPath, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $schemaPath;
    }

    protected function createWrapperScript(
        AgentRun $run,
        string $workDir,
        string $promptPath,
        string $outputPath,
        ?string $schemaPath
    ): string {
        $scriptDir = $this->config['script_dir'] ?? storage_path('agent-subprocess/scripts');
        File::ensureDirectoryExists($scriptDir);

        $scriptPath = "{$scriptDir}/{$run->id}.sh";

        $binary = $this->config['binary'] ?? 'claude';
        $command = $this->buildCommandString($run, $schemaPath);

        $script = <<<BASH
#!/bin/bash
cd {$workDir}
exec {$binary} {$command} < {$promptPath} > {$outputPath} 2>&1
BASH;

        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        return $scriptPath;
    }

    protected function buildCommandString(AgentRun $run, ?string $schemaPath): string
    {
        $parts = ['-p'];

        if ($this->config['verbose'] ?? true) {
            $parts[] = '--verbose';
        }

        $parts[] = '--output-format';
        $parts[] = 'stream-json';

        if ($this->config['include_partial_messages'] ?? true) {
            $parts[] = '--include-partial-messages';
        }

        $settingSources = $run->config['setting_sources'] ?? $this->config['setting_sources'] ?? 'local';
        $parts[] = '--setting-sources';
        $parts[] = escapeshellarg($settingSources);

        if ($run->config['disable_slash_commands'] ?? $this->config['disable_slash_commands'] ?? true) {
            $parts[] = '--disable-slash-commands';
        }

        if ($run->config['no_session_persistence'] ?? $this->config['no_session_persistence'] ?? true) {
            $parts[] = '--no-session-persistence';
        }

        $tools = $run->config['tools'] ?? $this->config['tools'] ?? null;
        if ($tools) {
            $parts[] = '--tools';
            $parts[] = escapeshellarg(is_array($tools) ? implode(',', $tools) : $tools);
        }

        $allowedTools = $run->config['allowed_tools'] ?? $this->config['allowed_tools'] ?? null;
        if ($allowedTools) {
            $parts[] = '--allowedTools';
            $parts[] = escapeshellarg(is_array($allowedTools) ? implode(',', $allowedTools) : $allowedTools);
        }

        if ($schemaPath) {
            $parts[] = '--json-schema';
            $parts[] = '"$(cat '.escapeshellarg($schemaPath).')"';
        }

        $maxTurns = $run->config['max_turns'] ?? $this->config['max_turns'] ?? null;
        if ($maxTurns) {
            $parts[] = '--max-turns';
            $parts[] = (string) $maxTurns;
        }

        $model = $run->config['model'] ?? $this->config['model'] ?? null;
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

    protected function getOutputPath(AgentRun $run): string
    {
        $basePath = $this->config['output_dir'] ?? storage_path('agent-subprocess/output');

        return "{$basePath}/{$run->id}.jsonl";
    }

    protected function getLogPath(AgentRun $run): string
    {
        $basePath = $this->config['log_dir'] ?? storage_path('agent-subprocess/logs');

        return "{$basePath}/{$run->id}.log";
    }
}
