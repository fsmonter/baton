<?php

declare(strict_types=1);

use AgentSubprocess\Data\Usage;
use AgentSubprocess\Log\LogPosition;

it('creates with default values', function () {
    $position = new LogPosition;

    expect($position->position)->toBe(0)
        ->and($position->buffer)->toBe('')
        ->and($position->eventIndex)->toBe(0)
        ->and($position->seenToolIds)->toBe([])
        ->and($position->textBuffer)->toBe('')
        ->and($position->usage)->toBeNull();
});

it('creates with custom values', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);
    $position = new LogPosition(
        position: 1024,
        buffer: 'partial line',
        eventIndex: 5,
        seenToolIds: ['tool-1', 'tool-2'],
        textBuffer: 'accumulated text',
        usage: $usage,
    );

    expect($position->position)->toBe(1024)
        ->and($position->buffer)->toBe('partial line')
        ->and($position->eventIndex)->toBe(5)
        ->and($position->seenToolIds)->toBe(['tool-1', 'tool-2'])
        ->and($position->textBuffer)->toBe('accumulated text')
        ->and($position->usage)->toBe($usage);
});

it('converts to array and back', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);
    $original = new LogPosition(
        position: 2048,
        buffer: 'incomplete',
        eventIndex: 10,
        seenToolIds: ['abc', 'def'],
        textBuffer: 'some text',
        usage: $usage,
    );

    $array = $original->toArray();
    $restored = LogPosition::fromArray($array);

    expect($restored->position)->toBe($original->position)
        ->and($restored->buffer)->toBe($original->buffer)
        ->and($restored->eventIndex)->toBe($original->eventIndex)
        ->and($restored->seenToolIds)->toBe($original->seenToolIds)
        ->and($restored->textBuffer)->toBe($original->textBuffer)
        ->and($restored->usage->inputTokens)->toBe($usage->inputTokens)
        ->and($restored->usage->outputTokens)->toBe($usage->outputTokens);
});

it('creates from null array', function () {
    $position = LogPosition::fromArray(null);

    expect($position->position)->toBe(0)
        ->and($position->buffer)->toBe('')
        ->and($position->eventIndex)->toBe(0)
        ->and($position->seenToolIds)->toBe([])
        ->and($position->textBuffer)->toBe('')
        ->and($position->usage)->toBeNull();
});

it('handles partial array data', function () {
    $position = LogPosition::fromArray(['position' => 500]);

    expect($position->position)->toBe(500)
        ->and($position->buffer)->toBe('')
        ->and($position->eventIndex)->toBe(0)
        ->and($position->seenToolIds)->toBe([])
        ->and($position->textBuffer)->toBe('');
});

it('serializes to snake_case keys', function () {
    $usage = new Usage(inputTokens: 100);
    $position = new LogPosition(
        position: 100,
        buffer: 'test',
        eventIndex: 3,
        seenToolIds: ['id-1'],
        textBuffer: 'hello',
        usage: $usage,
    );

    $array = $position->toArray();

    expect($array)->toHaveKeys(['position', 'buffer', 'event_index', 'seen_tool_ids', 'text_buffer', 'usage'])
        ->and($array['event_index'])->toBe(3)
        ->and($array['seen_tool_ids'])->toBe(['id-1'])
        ->and($array['text_buffer'])->toBe('hello')
        ->and($array['usage']['input_tokens'])->toBe(100);
});

it('creates new instance with usage via withUsage', function () {
    $position = new LogPosition(position: 100);
    $usage = new Usage(inputTokens: 50);

    $newPosition = $position->withUsage($usage);

    expect($newPosition->position)->toBe(100)
        ->and($newPosition->usage)->toBe($usage)
        ->and($position->usage)->toBeNull();
});

it('creates new instance with text buffer via withTextBuffer', function () {
    $position = new LogPosition(position: 100, textBuffer: 'old');

    $newPosition = $position->withTextBuffer('new content');

    expect($newPosition->position)->toBe(100)
        ->and($newPosition->textBuffer)->toBe('new content')
        ->and($position->textBuffer)->toBe('old');
});
