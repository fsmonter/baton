<?php

use Baton\BatonManager;
use Baton\Exceptions\ProcessFailedException;
use Baton\ProcessStatus;

test('process delegates isRunning to adapter', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->isRunning())->toBeTrue();
});

test('process delegates kill to adapter', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->kill())->toBeTrue()
        ->and($process->isRunning())->toBeFalse();
});

test('process status is running when active', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->status())->toBe(ProcessStatus::Running);
});

test('process status is completed after success', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->complete($process, 'Done');

    expect($process->status())->toBe(ProcessStatus::Completed);
});

test('process status is failed after failure', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->fail($process, 'Oops');

    expect($process->status())->toBe(ProcessStatus::Failed);
});

test('process result returns null while running', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->result())->toBeNull();
});

test('process result returns result after completion', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->complete($process, 'All done');

    expect($process->result()->output)->toBe('All done');
});

test('process result throws on failure', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->fail($process, 'Something broke');

    $process->result();
})->throws(ProcessFailedException::class, 'Something broke');

test('process has uuid id', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('process has startedAt timestamp', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->startedAt)->toBeInstanceOf(\Carbon\Carbon::class);
});
