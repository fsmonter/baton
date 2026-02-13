<?php

use Baton\Result;
use Baton\Usage;

test('result stores success state', function () {
    $result = new Result(success: true, output: 'Done');

    expect($result->success)->toBeTrue()
        ->and($result->output)->toBe('Done')
        ->and($result->error)->toBeNull();
});

test('result stores failure state', function () {
    $result = new Result(success: false, error: 'Something broke');

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('Something broke');
});

test('result stores structured output', function () {
    $result = new Result(success: true, structured: ['score' => 95]);

    expect($result->structured)->toBe(['score' => 95]);
});

test('result stores usage', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);
    $result = new Result(success: true, usage: $usage);

    expect($result->usage)->toBe($usage)
        ->and($result->usage->totalTokens())->toBe(150);
});

test('result stores duration', function () {
    $result = new Result(success: true, durationMs: 5000);

    expect($result->durationMs)->toBe(5000);
});
