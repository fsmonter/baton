<?php

use Baton\Exceptions\BatonException;
use Baton\Exceptions\ProcessCrashedException;

test('exception carries process id and exit code', function () {
    $e = new ProcessCrashedException('abc-123', 137);

    expect($e->processId)->toBe('abc-123')
        ->and($e->exitCode)->toBe(137)
        ->and($e->getMessage())->toContain('abc-123')
        ->and($e->getMessage())->toContain('137');
});

test('exception accepts custom message', function () {
    $e = new ProcessCrashedException('abc-123', 1, 'OOM killed');

    expect($e->getMessage())->toBe('OOM killed');
});

test('exception extends BatonException', function () {
    $e = new ProcessCrashedException('abc-123', 1);

    expect($e)->toBeInstanceOf(BatonException::class)
        ->and($e)->toBeInstanceOf(\RuntimeException::class);
});
