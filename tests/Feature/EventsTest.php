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
    $result = new Result(success: true, output: 'Done');
    $event = new ProcessCompleted($process, $result);

    expect($event->process)->toBe($process)
        ->and($event->result->success)->toBeTrue();
});

test('process failed event holds process and error', function () {
    $process = Mockery::mock(Process::class);
    $event = new ProcessFailed($process, error: 'Something broke');

    expect($event->process)->toBe($process)
        ->and($event->error)->toBe('Something broke');
});
