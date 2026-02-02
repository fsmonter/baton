<?php

declare(strict_types=1);

use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Models\AgentRun;

it('stores log position as JSON', function () {
    $run = AgentRun::create([
        'prompt' => 'Test',
        'log_position' => [
            'position' => 1024,
            'buffer' => 'partial',
            'event_index' => 5,
            'seen_tool_ids' => ['tool-1'],
        ],
    ]);

    $run->refresh();

    expect($run->log_position)->toBeArray()
        ->and($run->log_position['position'])->toBe(1024)
        ->and($run->log_position['buffer'])->toBe('partial')
        ->and($run->log_position['event_index'])->toBe(5)
        ->and($run->log_position['seen_tool_ids'])->toBe(['tool-1']);
});

it('initializes with null log position', function () {
    $run = AgentRun::create(['prompt' => 'Test']);

    expect($run->log_position)->toBeNull();

    $position = LogPosition::fromArray($run->log_position);

    expect($position->position)->toBe(0)
        ->and($position->buffer)->toBe('')
        ->and($position->eventIndex)->toBe(0);
});

it('updates log position during processing', function () {
    $run = AgentRun::create(['prompt' => 'Test']);

    $position1 = new LogPosition(position: 100, eventIndex: 2);
    $run->log_position = $position1->toArray();
    $run->save();

    $run->refresh();
    $restored = LogPosition::fromArray($run->log_position);

    expect($restored->position)->toBe(100)
        ->and($restored->eventIndex)->toBe(2);

    $position2 = new LogPosition(position: 500, eventIndex: 10, seenToolIds: ['a', 'b']);
    $run->log_position = $position2->toArray();
    $run->save();

    $run->refresh();
    $updated = LogPosition::fromArray($run->log_position);

    expect($updated->position)->toBe(500)
        ->and($updated->eventIndex)->toBe(10)
        ->and($updated->seenToolIds)->toBe(['a', 'b']);
});

it('preserves log position through status transitions', function () {
    $run = AgentRun::create([
        'prompt' => 'Test',
        'log_position' => (new LogPosition(position: 250))->toArray(),
    ]);

    $run->start();
    $run->refresh();
    expect(LogPosition::fromArray($run->log_position)->position)->toBe(250);

    $run->updateProgress(50, 'Working');
    $run->refresh();
    expect(LogPosition::fromArray($run->log_position)->position)->toBe(250);

    $run->complete(['result' => 'done']);
    $run->refresh();
    expect(LogPosition::fromArray($run->log_position)->position)->toBe(250);
});
