<?php

declare(strict_types=1);

namespace AgentSubprocess\Parsers;

use AgentSubprocess\Contracts\OutputParser;
use AgentSubprocess\Data\Usage;
use AgentSubprocess\Events\Output\ErrorEvent;
use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Events\Output\OutputEvent;
use AgentSubprocess\Events\Output\ProgressEvent;
use AgentSubprocess\Events\Output\ToolUseEvent;

class JsonLinesParser implements OutputParser
{
    public function parse(string $line): OutputEvent|array|null
    {
        $data = json_decode(trim($line), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $type = $data['type'] ?? $data['event'] ?? null;

        return match ($type) {
            'message', 'text', 'response' => new MessageEvent(
                content: $data['content'] ?? $data['text'] ?? $data['message'] ?? '',
                role: $data['role'] ?? 'assistant',
                raw: $line,
            ),
            'tool', 'tool_call', 'function_call' => new ToolUseEvent(
                tool: $data['tool'] ?? $data['name'] ?? $data['function'] ?? 'unknown',
                input: $data['input'] ?? $data['arguments'] ?? $data['params'] ?? [],
                output: $data['output'] ?? $data['result'] ?? null,
                toolId: $data['id'] ?? $data['tool_id'] ?? null,
                raw: $line,
            ),
            'progress', 'status' => new ProgressEvent(
                percent: $data['percent'] ?? $data['progress'] ?? 0,
                activity: $data['activity'] ?? $data['status'] ?? $data['message'] ?? null,
                raw: $line,
            ),
            'error' => new ErrorEvent(
                message: $data['message'] ?? $data['error'] ?? 'Unknown error',
                code: $data['code'] ?? null,
                raw: $line,
            ),
            default => null,
        };
    }

    public function flush(): array
    {
        return [];
    }

    public function reset(): void {}

    public function getUsage(): ?Usage
    {
        return null;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }
}
