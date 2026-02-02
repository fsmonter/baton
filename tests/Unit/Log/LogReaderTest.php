<?php

declare(strict_types=1);

use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Events\Output\ResultEvent;
use AgentSubprocess\Events\Output\SystemEvent;
use AgentSubprocess\Events\Output\ToolUseEvent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReader;
use AgentSubprocess\Parsers\ClaudeStreamParser;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/baton-tests-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->parser = new ClaudeStreamParser;
    $this->reader = new LogReader($this->parser);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir.'/*'));
        rmdir($this->tempDir);
    }
    $this->parser->reset();
});

it('returns empty result for non-existent file', function () {
    $position = new LogPosition;
    $result = $this->reader->readIncremental('/non/existent/file.jsonl', $position);

    expect($result->hasEvents())->toBeFalse()
        ->and($result->position)->toBe($position);
});

it('reads initial content from beginning', function () {
    $path = $this->tempDir.'/output.jsonl';
    file_put_contents($path, json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => ['Read', 'Write']])."\n");

    $position = new LogPosition;
    $result = $this->reader->readIncremental($path, $position);

    expect($result->hasEvents())->toBeTrue()
        ->and($result->events)->toHaveCount(1)
        ->and($result->events[0])->toBeInstanceOf(SystemEvent::class)
        ->and($result->events[0]->model)->toBe('sonnet')
        ->and($result->position->position)->toBeGreaterThan(0)
        ->and($result->position->eventIndex)->toBe(1);
});

it('reads incrementally from last position', function () {
    $path = $this->tempDir.'/output.jsonl';

    file_put_contents($path, json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []])."\n");
    $position = new LogPosition;
    $result1 = $this->reader->readIncremental($path, $position);

    file_put_contents($path, json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => ['path' => '/test']])."\n", FILE_APPEND);
    $result2 = $this->reader->readIncremental($path, $result1->position);

    expect($result2->events)->toHaveCount(1)
        ->and($result2->events[0])->toBeInstanceOf(ToolUseEvent::class)
        ->and($result2->events[0]->tool)->toBe('Read')
        ->and($result2->position->eventIndex)->toBe(2);
});

it('handles incomplete lines in buffer', function () {
    $path = $this->tempDir.'/output.jsonl';
    $incomplete = substr(json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []]), 0, 10);
    file_put_contents($path, $incomplete);

    $position = new LogPosition;
    $result = $this->reader->readIncremental($path, $position);

    expect($result->hasEvents())->toBeFalse()
        ->and($result->position->buffer)->toBe($incomplete);
});

it('completes buffered content on next read', function () {
    $path = $this->tempDir.'/output.jsonl';
    $fullLine = json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []]);

    file_put_contents($path, substr($fullLine, 0, 10));
    $result1 = $this->reader->readIncremental($path, new LogPosition);

    expect($result1->hasEvents())->toBeFalse()
        ->and($result1->position->buffer)->not->toBe('');

    file_put_contents($path, $fullLine."\n");
    $result2 = $this->reader->readIncremental($path, $result1->position);

    expect($result2->hasEvents())->toBeTrue()
        ->and($result2->events[0])->toBeInstanceOf(SystemEvent::class);
});

it('tracks multiple event types', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => ['Read']]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]),
        json_encode(['type' => 'tool_result', 'tool_use_id' => 'tool_1', 'tool_name' => 'Read', 'content' => 'file contents']),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $result = $this->reader->readIncremental($path, new LogPosition);

    expect($result->events)->toHaveCount(3)
        ->and($result->events[0])->toBeInstanceOf(SystemEvent::class)
        ->and($result->events[1])->toBeInstanceOf(ToolUseEvent::class)
        ->and($result->position->eventIndex)->toBe(3);
});

it('skips empty lines', function () {
    $path = $this->tempDir.'/output.jsonl';

    $content = json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []])."\n\n\n";
    file_put_contents($path, $content);

    $result = $this->reader->readIncremental($path, new LogPosition);

    expect($result->events)->toHaveCount(1);
});

it('returns no new events when file unchanged', function () {
    $path = $this->tempDir.'/output.jsonl';
    file_put_contents($path, json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []])."\n");

    $result1 = $this->reader->readIncremental($path, new LogPosition);
    $result2 = $this->reader->readIncremental($path, $result1->position);

    expect($result2->hasEvents())->toBeFalse()
        ->and($result2->position->position)->toBe($result1->position->position);
});

it('reads all events with generator', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_2', 'name' => 'Write', 'input' => []]),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $events = iterator_to_array($this->reader->readAll($path));

    expect($events)->toHaveCount(3)
        ->and($events[0])->toBeInstanceOf(SystemEvent::class)
        ->and($events[2])->toBeInstanceOf(ToolUseEvent::class);
});

it('returns empty generator for non-existent file', function () {
    $events = iterator_to_array($this->reader->readAll('/non/existent.jsonl'));

    expect($events)->toBe([]);
});

it('parses result events with structured output', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []]),
        json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'structured_output' => ['answer' => 'test'],
            'duration_ms' => 1500,
            'num_turns' => 3,
            'total_cost_usd' => 0.05,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $events = iterator_to_array($this->reader->readAll($path));

    expect($events)->toHaveCount(2)
        ->and($events[1])->toBeInstanceOf(ResultEvent::class)
        ->and($events[1]->success)->toBeTrue()
        ->and($events[1]->structuredOutput)->toBe(['answer' => 'test'])
        ->and($events[1]->numTurns)->toBe(3);
});

it('accumulates text from stream events and flushes on tool use', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'system', 'model' => 'sonnet', 'tools' => []]),
        json_encode(['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello ']]]),
        json_encode(['type' => 'stream_event', 'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'World']]]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $events = iterator_to_array($this->reader->readAll($path));

    expect($events)->toHaveCount(3)
        ->and($events[0])->toBeInstanceOf(SystemEvent::class)
        ->and($events[1])->toBeInstanceOf(MessageEvent::class)
        ->and($events[1]->content)->toBe('Hello World')
        ->and($events[2])->toBeInstanceOf(ToolUseEvent::class);
});

it('deduplicates tool use events by id', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_1', 'name' => 'Read', 'input' => []]),
        json_encode(['type' => 'tool_use', 'id' => 'tool_2', 'name' => 'Write', 'input' => []]),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $events = iterator_to_array($this->reader->readAll($path));

    expect($events)->toHaveCount(2)
        ->and($events[0]->toolId)->toBe('tool_1')
        ->and($events[1]->toolId)->toBe('tool_2');
});

it('tracks usage from message_start events', function () {
    $path = $this->tempDir.'/output.jsonl';

    $lines = [
        json_encode(['type' => 'stream_event', 'event' => [
            'type' => 'message_start',
            'message' => ['usage' => ['input_tokens' => 100, 'output_tokens' => 0]],
        ]]),
        json_encode(['type' => 'result', 'subtype' => 'success', 'usage' => ['input_tokens' => 100, 'output_tokens' => 50]]),
    ];

    file_put_contents($path, implode("\n", $lines)."\n");

    $events = iterator_to_array($this->reader->readAll($path));
    $resultEvent = $events[0];

    expect($resultEvent)->toBeInstanceOf(ResultEvent::class)
        ->and($resultEvent->usage['input_tokens'])->toBe(100)
        ->and($resultEvent->usage['output_tokens'])->toBe(50);
});
