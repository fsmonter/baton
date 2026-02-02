<?php

declare(strict_types=1);

use AgentSubprocess\Data\Usage;

it('creates with default values', function () {
    $usage = new Usage;

    expect($usage->inputTokens)->toBe(0)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->cacheReadTokens)->toBe(0)
        ->and($usage->cacheWriteTokens)->toBe(0)
        ->and($usage->costUsd)->toBeNull();
});

it('creates with custom values', function () {
    $usage = new Usage(
        inputTokens: 1000,
        outputTokens: 500,
        cacheReadTokens: 200,
        cacheWriteTokens: 100,
        costUsd: 0.05,
    );

    expect($usage->inputTokens)->toBe(1000)
        ->and($usage->outputTokens)->toBe(500)
        ->and($usage->cacheReadTokens)->toBe(200)
        ->and($usage->cacheWriteTokens)->toBe(100)
        ->and($usage->costUsd)->toBe(0.05);
});

it('calculates total tokens', function () {
    $usage = new Usage(inputTokens: 1000, outputTokens: 500);

    expect($usage->totalTokens())->toBe(1500);
});

it('knows when it has cost', function () {
    $withCost = new Usage(costUsd: 0.05);
    $withoutCost = new Usage;

    expect($withCost->hasCost())->toBeTrue()
        ->and($withoutCost->hasCost())->toBeFalse();
});

it('merges two usage instances', function () {
    $usage1 = new Usage(inputTokens: 100, outputTokens: 50, costUsd: 0.01);
    $usage2 = new Usage(inputTokens: 200, outputTokens: 100, costUsd: 0.02);

    $merged = $usage1->merge($usage2);

    expect($merged->inputTokens)->toBe(300)
        ->and($merged->outputTokens)->toBe(150)
        ->and($merged->costUsd)->toBe(0.03);
});

it('merges with null cost correctly', function () {
    $usage1 = new Usage(inputTokens: 100);
    $usage2 = new Usage(inputTokens: 200, costUsd: 0.05);

    $merged = $usage1->merge($usage2);

    expect($merged->costUsd)->toBe(0.05);
});

it('converts to array and back', function () {
    $original = new Usage(
        inputTokens: 1000,
        outputTokens: 500,
        cacheReadTokens: 200,
        cacheWriteTokens: 100,
        costUsd: 0.05,
    );

    $array = $original->toArray();
    $restored = Usage::fromArray($array);

    expect($restored->inputTokens)->toBe($original->inputTokens)
        ->and($restored->outputTokens)->toBe($original->outputTokens)
        ->and($restored->cacheReadTokens)->toBe($original->cacheReadTokens)
        ->and($restored->cacheWriteTokens)->toBe($original->cacheWriteTokens)
        ->and($restored->costUsd)->toBe($original->costUsd);
});

it('creates from null array', function () {
    $usage = Usage::fromArray(null);

    expect($usage->inputTokens)->toBe(0)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->costUsd)->toBeNull();
});

it('serializes to snake_case keys', function () {
    $usage = new Usage(
        inputTokens: 100,
        outputTokens: 50,
        cacheReadTokens: 25,
        cacheWriteTokens: 10,
        costUsd: 0.01,
    );

    $array = $usage->toArray();

    expect($array)->toHaveKeys([
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'cost_usd',
    ]);
});
