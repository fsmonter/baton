<?php

declare(strict_types=1);

use AgentSubprocess\Data\TerminationResult;

it('creates termination result with success and method', function () {
    $result = new TerminationResult(success: true, method: 'sigterm');

    expect($result->success)->toBeTrue()
        ->and($result->method)->toBe('sigterm');
});

it('converts to array and back', function () {
    $original = new TerminationResult(success: true, method: 'sigkill');

    $array = $original->toArray();
    $restored = TerminationResult::fromArray($array);

    expect($restored->success)->toBe($original->success)
        ->and($restored->method)->toBe($original->method);
});

it('represents different termination methods', function (bool $success, string $method) {
    $result = new TerminationResult(success: $success, method: $method);

    expect($result->success)->toBe($success)
        ->and($result->method)->toBe($method);
})->with([
    'already dead' => [true, 'already_dead'],
    'sigterm success' => [true, 'sigterm'],
    'sigkill success' => [true, 'sigkill'],
    'sigkill failed' => [false, 'sigkill'],
    'no pid' => [true, 'no_pid'],
    'simulated' => [true, 'simulated'],
]);
