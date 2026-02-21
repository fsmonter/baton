<?php

use Baton\BatonManager;
use Baton\Process;
use Carbon\Carbon;

test('process serializes to array', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    $data = $process->toArray();

    expect($data)->toHaveKeys(['id', 'adapter', 'handle', 'work_dir', 'started_at'])
        ->and($data['id'])->toBe($process->id)
        ->and($data['adapter'])->toBe('null');
});

test('process deserializes from array', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');

    $data = [
        'id' => 'test-uuid',
        'adapter' => 'null',
        'handle' => ['simulated' => true],
        'work_dir' => '/tmp',
        'started_at' => '2026-02-14T10:00:00+00:00',
    ];

    $process = Process::fromArray($data, $adapter);

    expect($process->id)->toBe('test-uuid')
        ->and($process->adapter)->toBe('null')
        ->and($process->handle)->toBe(['simulated' => true])
        ->and($process->workDir)->toBe('/tmp')
        ->and($process->startedAt)->toBeInstanceOf(Carbon::class);
});

test('round-trip serialization preserves process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $original = $manager->spawn('Test');

    $data = $original->toArray();
    $restored = Process::fromArray($data, $manager->adapter('null'));

    expect($restored->id)->toBe($original->id)
        ->and($restored->adapter)->toBe($original->adapter)
        ->and($restored->handle)->toBe($original->handle)
        ->and($restored->workDir)->toBe($original->workDir);
});
