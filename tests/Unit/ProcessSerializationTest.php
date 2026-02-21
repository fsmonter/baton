<?php

use Baton\BatonManager;
use Baton\Process;
use Carbon\Carbon;

test('process serializes to array', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $process = $manager->spawn('Test');

    $data = $process->toArray();

    expect($data)->toHaveKeys(['id', 'agent', 'handle', 'work_dir', 'started_at'])
        ->and($data['id'])->toBe($process->id)
        ->and($data['agent'])->toBe('null');
});

test('process deserializes from array', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $agent = $manager->agent('null');

    $data = [
        'id' => 'test-uuid',
        'agent' => 'null',
        'handle' => ['simulated' => true],
        'work_dir' => '/tmp',
        'started_at' => '2026-02-14T10:00:00+00:00',
    ];

    $process = Process::fromArray($data, $agent);

    expect($process->id)->toBe('test-uuid')
        ->and($process->agent)->toBe('null')
        ->and($process->handle)->toBe(['simulated' => true])
        ->and($process->workDir)->toBe('/tmp')
        ->and($process->startedAt)->toBeInstanceOf(Carbon::class);
});

test('round-trip serialization preserves process', function () {
    $manager = new BatonManager(['default' => 'null', 'agents' => ['null' => []]]);
    $original = $manager->spawn('Test');

    $data = $original->toArray();
    $restored = Process::fromArray($data, $manager->agent('null'));

    expect($restored->id)->toBe($original->id)
        ->and($restored->agent)->toBe($original->agent)
        ->and($restored->handle)->toBe($original->handle)
        ->and($restored->workDir)->toBe($original->workDir);
});
