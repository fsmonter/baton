<?php

use Baton\Result;
use Baton\Usage;

test('result stores output', function () {
    $result = new Result(output: 'Hello world');

    expect($result->output)->toBe('Hello world');
});

test('result stores structured output', function () {
    $data = ['score' => 95, 'issues' => []];
    $result = new Result(structured: $data);

    expect($result->structured)->toBe($data);
});

test('result stores usage', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);
    $result = new Result(usage: $usage);

    expect($result->usage)->toBe($usage)
        ->and($result->usage->inputTokens)->toBe(100);
});

test('result stores duration', function () {
    $result = new Result(durationMs: 5000);

    expect($result->durationMs)->toBe(5000);
});

test('result defaults to null', function () {
    $result = new Result();

    expect($result->output)->toBeNull()
        ->and($result->structured)->toBeNull()
        ->and($result->usage)->toBeNull()
        ->and($result->durationMs)->toBeNull();
});
