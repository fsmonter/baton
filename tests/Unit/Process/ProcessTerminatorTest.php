<?php

declare(strict_types=1);

use AgentSubprocess\Process\ProcessTerminator;

it('reports already dead for non-existent process', function () {
    $terminator = new ProcessTerminator;

    $result = $terminator->terminate(999999999);

    expect($result->success)->toBeTrue()
        ->and($result->method)->toBe('already_dead');
});

it('checks if process is alive', function () {
    $terminator = new ProcessTerminator;

    $nonExistentPid = 999999999;
    expect($terminator->isAlive($nonExistentPid))->toBeFalse();

    $currentPid = getmypid();
    expect($terminator->isAlive($currentPid))->toBeTrue();
});

it('sends sigterm signal to running process', function () {
    $process = new Symfony\Component\Process\Process(['sleep', '60']);
    $process->start();
    $pid = $process->getPid();

    $terminator = new ProcessTerminator;
    expect($terminator->isAlive($pid))->toBeTrue();

    $result = $terminator->terminate($pid, 500);

    expect($result->method)->toBeIn(['sigterm', 'sigkill']);

    $process->stop(0);
});

it('returns correct result structure', function () {
    $terminator = new ProcessTerminator;

    $result = $terminator->terminate(999999999);

    expect($result)->toBeInstanceOf(\AgentSubprocess\Data\TerminationResult::class)
        ->and($result->success)->toBeBool()
        ->and($result->method)->toBeString();
});
