<?php

use Baton\Baton;
use Baton\ProcessStatus;
use Baton\Result;
use Baton\Usage;

test('spawns a real claude process and gets a result', function () {
    config([
        'baton.default' => 'claude-code',
        'baton.agents.claude-code' => [
            'binary' => 'claude',
            'model' => 'haiku',
            'max_turns' => 1,
        ],
    ]);

    $process = Baton::prompt('What is 2 + 2? Reply with ONLY the number, nothing else.')
        ->model('haiku')
        ->maxTurns(1)
        ->spawn();

    expect($process->id)->toBeString()->not->toBeEmpty();

    // Poll until done (timeout after 60s)
    $deadline = time() + 60;
    while ($process->isRunning() && time() < $deadline) {
        usleep(500_000); // 500ms
    }

    expect($process->isRunning())->toBeFalse('Process should have finished within 60s');
    expect($process->status())->toBe(ProcessStatus::Completed);

    $result = $process->result();


    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->output)->toBeString()->not->toBeEmpty()
        ->and($result->output)->toContain('4')
        ->and($result->usage)->toBeInstanceOf(Usage::class)
        ->and($result->usage->inputTokens)->toBeGreaterThan(0)
        ->and($result->usage->outputTokens)->toBeGreaterThan(0);
})->group('e2e');

test('spawns a process with structured output', function () {
    config([
        'baton.default' => 'claude-code',
        'baton.agents.claude-code' => [
            'binary' => 'claude',
            'model' => 'haiku',
            'max_turns' => 3,
        ],
    ]);

    $process = Baton::prompt('List three primary colors.')
        ->model('haiku')
        ->maxTurns(3)
        ->schema([
            'type' => 'object',
            'properties' => [
                'colors' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['colors'],
        ])
        ->spawn();

    $deadline = time() + 60;
    while ($process->isRunning() && time() < $deadline) {
        usleep(500_000);
    }

    expect($process->isRunning())->toBeFalse('Process should have finished within 60s');

    $result = $process->result();

    dd($process, $result);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->structured)->toBeArray()
        ->and($result->structured)->toHaveKey('colors')
        ->and($result->structured['colors'])->toBeArray()->not->toBeEmpty()
        ->and($result->usage->cost)->toBeGreaterThan(0);
})->group('e2e');

test('process can be killed while running', function () {
    config([
        'baton.default' => 'claude-code',
        'baton.agents.claude-code' => [
            'binary' => 'claude',
            'model' => 'haiku',
            'max_turns' => 50,
        ],
    ]);

    $process = Baton::prompt('Write a very long essay about the history of mathematics. Be thorough and detailed.')
        ->model('haiku')
        ->maxTurns(50)
        ->spawn();

    // Give it a moment to start
    sleep(3);

    expect($process->isRunning())->toBeTrue();

    $killed = $process->kill();
    expect($killed)->toBeTrue();

    // Poll until stopped (up to 5s for cleanup)
    $deadline = time() + 5;
    while ($process->isRunning() && time() < $deadline) {
        usleep(500_000);
    }

    expect($process->isRunning())->toBeFalse();
})->group('e2e');
