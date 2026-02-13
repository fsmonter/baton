<?php

use Baton\Usage;

test('usage has default values', function () {
    $usage = new Usage();

    expect($usage->inputTokens)->toBe(0)
        ->and($usage->outputTokens)->toBe(0)
        ->and($usage->cacheReadTokens)->toBe(0)
        ->and($usage->cacheWriteTokens)->toBe(0)
        ->and($usage->cost)->toBeNull();
});

test('usage calculates total tokens', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50);

    expect($usage->totalTokens())->toBe(150);
});

test('usage can be merged', function () {
    $a = new Usage(inputTokens: 100, outputTokens: 50, cost: 0.01);
    $b = new Usage(inputTokens: 200, outputTokens: 100, cost: 0.02);
    $merged = $a->merge($b);

    expect($merged->inputTokens)->toBe(300)
        ->and($merged->outputTokens)->toBe(150)
        ->and($merged->cost)->toBe(0.03);
});

test('usage converts to array', function () {
    $usage = new Usage(inputTokens: 100, outputTokens: 50, cost: 0.01);

    expect($usage->toArray())->toBe([
        'input_tokens' => 100,
        'output_tokens' => 50,
        'cache_read_tokens' => 0,
        'cache_write_tokens' => 0,
        'cost' => 0.01,
    ]);
});

test('usage can be created from array', function () {
    $usage = Usage::fromArray([
        'input_tokens' => 100,
        'output_tokens' => 50,
        'cost' => 0.01,
    ]);

    expect($usage->inputTokens)->toBe(100)
        ->and($usage->outputTokens)->toBe(50)
        ->and($usage->cost)->toBe(0.01);
});

test('usage from null array returns empty', function () {
    $usage = Usage::fromArray(null);

    expect($usage->totalTokens())->toBe(0);
});
