<?php

declare(strict_types=1);

use AgentSubprocess\Data\Process;
use Carbon\Carbon;

it('creates a process with all properties', function () {
    $process = new Process(
        pid: 12345,
        driver: 'claude-code',
        command: ['claude', '--print', 'hello'],
        workDir: '/tmp',
        outputPath: '/tmp/output.jsonl',
        logPath: '/tmp/log.txt',
        startedAt: Carbon::parse('2024-01-01 12:00:00'),
        meta: ['model' => 'sonnet'],
    );

    expect($process->pid)->toBe(12345)
        ->and($process->driver)->toBe('claude-code')
        ->and($process->command)->toBe(['claude', '--print', 'hello'])
        ->and($process->workDir)->toBe('/tmp')
        ->and($process->outputPath)->toBe('/tmp/output.jsonl')
        ->and($process->logPath)->toBe('/tmp/log.txt')
        ->and($process->meta)->toBe(['model' => 'sonnet']);
});

it('knows when it has started', function () {
    $started = new Process(pid: 123, driver: 'test', command: [], workDir: '/tmp');
    $notStarted = new Process(pid: null, driver: 'test', command: [], workDir: '/tmp');

    expect($started->isStarted())->toBeTrue()
        ->and($notStarted->isStarted())->toBeFalse();
});

it('converts to array and back', function () {
    $original = new Process(
        pid: 12345,
        driver: 'claude-code',
        command: ['claude', '--print'],
        workDir: '/tmp',
        outputPath: '/tmp/out.jsonl',
        startedAt: Carbon::parse('2024-01-01 12:00:00'),
    );

    $array = $original->toArray();
    $restored = Process::fromArray($array);

    expect($restored->pid)->toBe($original->pid)
        ->and($restored->driver)->toBe($original->driver)
        ->and($restored->command)->toBe($original->command)
        ->and($restored->workDir)->toBe($original->workDir)
        ->and($restored->outputPath)->toBe($original->outputPath);
});
