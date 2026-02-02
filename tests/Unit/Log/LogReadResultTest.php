<?php

declare(strict_types=1);

use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReadResult;

it('creates with position and events', function () {
    $position = new LogPosition(position: 100);
    $events = [new MessageEvent('Test', 'assistant')];

    $result = new LogReadResult($position, $events);

    expect($result->position)->toBe($position)
        ->and($result->events)->toBe($events);
});

it('reports when it has events', function () {
    $position = new LogPosition;

    $withEvents = new LogReadResult($position, [new MessageEvent('Test', 'assistant')]);
    $withoutEvents = new LogReadResult($position, []);

    expect($withEvents->hasEvents())->toBeTrue()
        ->and($withoutEvents->hasEvents())->toBeFalse();
});

it('handles empty events array', function () {
    $position = new LogPosition(position: 500);
    $result = new LogReadResult($position, []);

    expect($result->hasEvents())->toBeFalse()
        ->and($result->events)->toBe([]);
});
