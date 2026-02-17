<?php

use Baton\Events\ProcessCompleted;
use Baton\Events\ProcessFailed;
use Baton\Events\ProcessSpawned;
use Baton\Process;
use Baton\Result;

test('process spawned event holds process', function () {
    $process = Mockery::mock(Process::class);
    $event = new ProcessSpawned($process);

    expect($event->process)->toBe($process);
});

test('process completed event holds process and result', function () {
    $process = Mockery::mock(Process::class);
    $result = new Result(output: 'Done');
    $event = new ProcessCompleted($process, $result);

    expect($event->process)->toBe($process)
        ->and($event->result->output)->toBe('Done');
});

test('process failed event holds process and error', function () {
    $process = Mockery::mock(Process::class);
    $event = new ProcessFailed($process, error: 'Something broke');

    expect($event->process)->toBe($process)
        ->and($event->error)->toBe('Something broke');
});

test('process failed event holds usage from failed run', function () {
    $process = Mockery::mock(Process::class);
    $usage = new \Baton\Usage(inputTokens: 500, outputTokens: 100);
    $event = new ProcessFailed($process, error: 'Token limit', usage: $usage);

    expect($event->usage)->toBe($usage)
        ->and($event->usage->inputTokens)->toBe(500);
});
