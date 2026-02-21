<?php

use Baton\BatonManager;
use Baton\PendingProcess;

test('pending process collects prompt', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->prompt('Fix the tests');

    expect($pending->getPrompt())->toBe('Fix the tests');
});

test('pending process collects model', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->model('opus');

    expect($pending->getModel())->toBe('opus');
});

test('pending process collects tools', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->tools(['Read', 'Write']);

    expect($pending->getTools())->toBe(['Read', 'Write']);
});

test('pending process collects directory', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->inDir('/tmp');

    expect($pending->getDirectory())->toBe('/tmp');
});

test('pending process collects agent name', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->via('claude-code');

    expect($pending->getAgentName())->toBe('claude-code');
});

test('pending process collects max turns', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->maxTurns(50);

    expect($pending->getMaxTurns())->toBe(50);
});

test('pending process collects timeout', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))->timeout(3600);

    expect($pending->getTimeout())->toBe(3600);
});

test('pending process collects schema', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $schema = ['type' => 'object', 'properties' => ['score' => ['type' => 'integer']]];
    $pending = (new PendingProcess($manager))->schema($schema);

    expect($pending->getSchema())->toBe($schema);
});

test('pending process is fluent', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $pending = (new PendingProcess($manager))
        ->prompt('Fix tests')
        ->model('opus')
        ->tools(['Read'])
        ->inDir('/tmp')
        ->via('null')
        ->maxTurns(10)
        ->timeout(60)
        ->schema(['type' => 'object']);

    expect($pending->getPrompt())->toBe('Fix tests')
        ->and($pending->getModel())->toBe('opus')
        ->and($pending->getTools())->toBe(['Read'])
        ->and($pending->getDirectory())->toBe('/tmp')
        ->and($pending->getAgentName())->toBe('null')
        ->and($pending->getMaxTurns())->toBe(10)
        ->and($pending->getTimeout())->toBe(60)
        ->and($pending->getSchema())->toBe(['type' => 'object']);
});

test('pending process spawn returns a process', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $process = (new PendingProcess($manager))
        ->prompt('Test')
        ->spawn();

    expect($process)->toBeInstanceOf(\Baton\Process::class)
        ->and($process->agent)->toBe('null')
        ->and($process->isRunning())->toBeTrue();
});
