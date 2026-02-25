<?php

use Baton\Output\ErrorEvent;
use Baton\Output\MessageEvent;
use Baton\Output\ResultEvent;
use Baton\Output\ToolResultEvent;
use Baton\Output\ToolUseEvent;
use Baton\Parsers\ClaudeStreamParser;

test('parser returns null for invalid json', function () {
    $parser = new ClaudeStreamParser();

    expect($parser->parse('not json'))->toBeNull();
});

test('parser parses tool_use events', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => ['path' => 'file.txt']]);

    $result = $parser->parse($line);

    expect($result)->toBeInstanceOf(ToolUseEvent::class)
        ->and($result->tool)->toBe('Read')
        ->and($result->input)->toBe(['path' => 'file.txt'])
        ->and($result->toolId)->toBe('tool_1');
});

test('parser deduplicates tool_use by id', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]);

    $parser->parse($line);
    $result = $parser->parse($line);

    expect($result)->toBeNull();
});

test('parser parses tool_result events', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode(['type' => 'tool_result', 'tool_use_id' => 'tool_1', 'tool_name' => 'Read', 'content' => 'file contents', 'is_error' => false]);

    $result = $parser->parse($line);

    expect($result)->toBeInstanceOf(ToolResultEvent::class)
        ->and($result->toolUseId)->toBe('tool_1')
        ->and($result->toolName)->toBe('Read')
        ->and($result->isError)->toBeFalse();
});

test('parser parses result events', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode([
        'type' => 'result',
        'subtype' => 'success',
        'is_error' => false,
        'duration_ms' => 5000,
        'num_turns' => 3,
        'total_cost_usd' => 0.05,
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
    ]);

    $result = $parser->parse($line);

    expect($result)->toBeInstanceOf(ResultEvent::class)
        ->and($result->success)->toBeTrue()
        ->and($result->durationMs)->toBe(5000.0)
        ->and($result->costUsd)->toBe(0.05);
});

test('parser parses error result', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode(['type' => 'result', 'subtype' => 'error_max_structured_output_retries', 'is_error' => true]);

    $result = $parser->parse($line);

    expect($result)->toBeInstanceOf(ResultEvent::class)
        ->and($result->success)->toBeFalse();
});

test('parser parses error events', function () {
    $parser = new ClaudeStreamParser();
    $line = json_encode(['type' => 'error', 'message' => 'Something went wrong', 'code' => 500]);

    $result = $parser->parse($line);

    expect($result)->toBeInstanceOf(ErrorEvent::class)
        ->and($result->message)->toBe('Something went wrong')
        ->and($result->code)->toBe(500);
});

test('parser emits message events from text deltas', function () {
    $parser = new ClaudeStreamParser();

    $event1 = $parser->parse(json_encode([
        'type' => 'stream_event',
        'event' => [
            'type' => 'content_block_delta',
            'delta' => ['type' => 'text_delta', 'text' => 'Hello '],
        ],
    ]));

    $event2 = $parser->parse(json_encode([
        'type' => 'stream_event',
        'event' => [
            'type' => 'content_block_delta',
            'delta' => ['type' => 'text_delta', 'text' => 'World'],
        ],
    ]));

    expect($event1)->toBeInstanceOf(MessageEvent::class)
        ->and($event1->content)->toBe('Hello ')
        ->and($event2)->toBeInstanceOf(MessageEvent::class)
        ->and($event2->content)->toBe('World');
});

test('parser reset clears state', function () {
    $parser = new ClaudeStreamParser();

    $parser->parse(json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]));
    $parser->reset();

    // Should be able to parse same tool_id again after reset
    $result = $parser->parse(json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]));

    expect($result)->toBeInstanceOf(ToolUseEvent::class);
});

test('parser tracks usage from message_start', function () {
    $parser = new ClaudeStreamParser();

    $parser->parse(json_encode([
        'type' => 'stream_event',
        'event' => [
            'type' => 'message_start',
            'message' => [
                'usage' => ['input_tokens' => 100, 'output_tokens' => 0],
            ],
        ],
    ]));

    $usage = $parser->getUsage();

    expect($usage)->not->toBeNull()
        ->and($usage->inputTokens)->toBe(100);
});
