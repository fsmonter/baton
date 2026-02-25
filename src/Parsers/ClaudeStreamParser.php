<?php

declare(strict_types=1);

namespace Baton\Parsers;

use Baton\Output\ErrorEvent;
use Baton\Output\MessageEvent;
use Baton\Output\OutputEvent;
use Baton\Output\ResultEvent;
use Baton\Output\SystemEvent;
use Baton\Output\ToolResultEvent;
use Baton\Output\ToolUseEvent;
use Baton\Usage;

class ClaudeStreamParser
{
    protected array $seenToolIds = [];
    protected Usage $usage;

    public function __construct()
    {
        $this->usage = new Usage();
    }

    public function parse(string $line): OutputEvent|array|null
    {
        $data = json_decode(trim($line), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $type = $data['type'] ?? null;

        return match ($type) {
            'system' => $this->parseSystem($data),
            'stream_event' => $this->parseStreamEvent($data),
            'tool_use' => $this->parseToolUse($data),
            'tool_result' => $this->parseToolResult($data),
            'assistant' => $this->parseAssistant($data),
            'user' => $this->parseUser($data),
            'result' => $this->parseResult($data),
            'error' => $this->parseError($data),
            default => null,
        };
    }

    public function reset(): void
    {
        $this->seenToolIds = [];
        $this->usage = new Usage();
    }

    public function getUsage(): ?Usage
    {
        return $this->usage->totalTokens() > 0 ? $this->usage : null;
    }

    protected function parseSystem(array $data): SystemEvent
    {
        $tools = $data['tools'] ?? [];

        $mcpServers = collect($tools)
            ->filter(fn (string $tool) => str_starts_with($tool, 'mcp__'))
            ->map(fn (string $tool) => explode('__', $tool)[1] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new SystemEvent(
            model: $data['model'] ?? 'unknown',
            version: $data['version'] ?? null,
            tools: $tools,
            mcpServers: $mcpServers,
            raw: json_encode($data),
        );
    }

    protected function parseStreamEvent(array $data): OutputEvent|array|null
    {
        $event = $data['event'] ?? [];
        $eventType = $event['type'] ?? null;

        return match ($eventType) {
            'content_block_delta' => $this->parseContentDelta($event),
            'message_start' => $this->parseMessageStart($event),
            'message_delta' => $this->parseMessageDelta($event),
            default => null,
        };
    }

    protected function parseContentDelta(array $event): ?OutputEvent
    {
        $delta = $event['delta'] ?? [];

        if (($delta['type'] ?? '') === 'text_delta') {
            $text = $delta['text'] ?? '';

            if ($text !== '') {
                return new MessageEvent(content: $text, role: 'assistant');
            }
        }

        return null;
    }

    protected function parseMessageStart(array $event): ?OutputEvent
    {
        $messageUsage = $event['message']['usage'] ?? [];

        if ($messageUsage) {
            $this->usage = $this->usage->merge(new Usage(
                inputTokens: $messageUsage['input_tokens'] ?? 0,
                outputTokens: $messageUsage['output_tokens'] ?? 0,
                cacheReadTokens: $messageUsage['cache_read_input_tokens'] ?? 0,
                cacheWriteTokens: $messageUsage['cache_creation_input_tokens'] ?? 0,
            ));
        }

        return null;
    }

    protected function parseMessageDelta(array $event): ?OutputEvent
    {
        $usage = $event['usage'] ?? [];

        if ($usage) {
            $this->usage = $this->usage->merge(new Usage(
                outputTokens: $usage['output_tokens'] ?? 0,
            ));
        }

        return null;
    }

    protected function parseToolUse(array $data): ?ToolUseEvent
    {
        $toolId = $data['id'] ?? null;

        if ($toolId && isset($this->seenToolIds[$toolId])) {
            return null;
        }

        if ($toolId) {
            $this->seenToolIds[$toolId] = true;
        }

        return new ToolUseEvent(
            tool: $data['name'] ?? 'unknown',
            input: $data['input'] ?? [],
            output: null,
            toolId: $toolId,
            raw: json_encode($data),
        );
    }

    protected function parseToolResult(array $data): ToolResultEvent
    {
        return new ToolResultEvent(
            toolUseId: $data['tool_use_id'] ?? null,
            toolName: $data['tool_name'] ?? null,
            content: $data['content'] ?? null,
            isError: $data['is_error'] ?? false,
            raw: json_encode($data),
        );
    }

    protected function parseAssistant(array $data): OutputEvent|array|null
    {
        $message = $data['message'] ?? [];
        $content = $message['content'] ?? [];
        $events = [];

        foreach ($content as $block) {
            $blockType = $block['type'] ?? '';

            if ($blockType === 'tool_use') {
                $toolId = $block['id'] ?? null;

                if ($toolId && isset($this->seenToolIds[$toolId])) {
                    continue;
                }

                if ($toolId) {
                    $this->seenToolIds[$toolId] = true;
                }

                $events[] = new ToolUseEvent(
                    tool: $block['name'] ?? 'unknown',
                    input: $block['input'] ?? [],
                    output: null,
                    toolId: $toolId,
                    raw: json_encode($block),
                );
            }
        }

        return count($events) === 0 ? null : (count($events) === 1 ? $events[0] : $events);
    }

    protected function parseUser(array $data): OutputEvent|array|null
    {
        $message = $data['message'] ?? [];
        $content = $message['content'] ?? [];
        $events = [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_result') {
                $events[] = new ToolResultEvent(
                    toolUseId: $block['tool_use_id'] ?? null,
                    toolName: null,
                    content: $block['content'] ?? null,
                    isError: $block['is_error'] ?? false,
                    raw: json_encode($block),
                );
            }
        }

        return count($events) === 0 ? null : (count($events) === 1 ? $events[0] : $events);
    }

    protected function parseResult(array $data): ResultEvent
    {
        $resultUsage = $data['usage'] ?? [];
        if ($resultUsage) {
            $this->usage = new Usage(
                inputTokens: $resultUsage['input_tokens'] ?? $this->usage->inputTokens,
                outputTokens: $resultUsage['output_tokens'] ?? $this->usage->outputTokens,
                cacheReadTokens: $resultUsage['cache_read_input_tokens'] ?? $this->usage->cacheReadTokens,
                cacheWriteTokens: $resultUsage['cache_creation_input_tokens'] ?? $this->usage->cacheWriteTokens,
                cost: $data['total_cost_usd'] ?? null,
            );
        }

        $subtype = $data['subtype'] ?? 'success';
        $isError = ($data['is_error'] ?? false) || $subtype === 'error_max_structured_output_retries';

        return new ResultEvent(
            success: ! $isError,
            subtype: $subtype,
            structuredOutput: $data['structured_output'] ?? null,
            durationMs: $data['duration_ms'] ?? null,
            numTurns: $data['num_turns'] ?? null,
            costUsd: $data['total_cost_usd'] ?? null,
            usage: $this->usage->toArray(),
            error: $isError ? ($data['error'] ?? $subtype) : null,
            raw: json_encode($data),
        );
    }

    protected function parseError(array $data): ErrorEvent
    {
        return new ErrorEvent(
            message: $data['message'] ?? $data['error'] ?? 'Unknown error',
            code: $data['code'] ?? null,
            raw: json_encode($data),
        );
    }
}
