<?php

declare(strict_types=1);

use AgentSubprocess\Enums\RunStatus;
use AgentSubprocess\Models\AgentRun;

it('creates an agent run with default values', function () {
    $run = AgentRun::create([
        'driver' => 'claude-code',
        'prompt' => 'Test prompt',
    ]);

    expect($run->status)->toBe(RunStatus::Pending)
        ->and($run->progress_percent)->toBe(0)
        ->and($run->driver)->toBe('claude-code')
        ->and($run->prompt)->toBe('Test prompt');
});

it('can start a run', function () {
    $run = AgentRun::create(['driver' => 'null', 'prompt' => 'test']);

    $run->start();

    expect($run->status)->toBe(RunStatus::Running)
        ->and($run->started_at)->not->toBeNull();
});

it('can complete a run with result', function () {
    $run = AgentRun::create(['driver' => 'null', 'prompt' => 'test']);
    $run->start();

    $run->complete(['output' => 'Success']);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->progress_percent)->toBe(100)
        ->and($run->result)->toBe(['output' => 'Success'])
        ->and($run->completed_at)->not->toBeNull();
});

it('can fail a run with error', function () {
    $run = AgentRun::create(['driver' => 'null', 'prompt' => 'test']);
    $run->start();

    $run->fail('Something went wrong');

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('Something went wrong')
        ->and($run->completed_at)->not->toBeNull();
});

it('can cancel a run', function () {
    $run = AgentRun::create(['driver' => 'null', 'prompt' => 'test']);
    $run->start();

    $run->cancel();

    expect($run->status)->toBe(RunStatus::Cancelled)
        ->and($run->completed_at)->not->toBeNull();
});

it('updates progress within bounds', function () {
    $run = AgentRun::create(['driver' => 'null', 'prompt' => 'test']);

    $run->updateProgress(50, 'Processing...');
    expect($run->progress_percent)->toBe(50)
        ->and($run->current_activity)->toBe('Processing...');

    $run->updateProgress(150);
    expect($run->progress_percent)->toBe(100);

    $run->updateProgress(-10);
    expect($run->progress_percent)->toBe(0);
});
