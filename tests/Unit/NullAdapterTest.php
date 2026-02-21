<?php

use Baton\BatonManager;
use Baton\Contracts\OutputReader;
use Baton\Exceptions\ProcessFailedException;

test('null adapter spawns a process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->isRunning())->toBeTrue()
        ->and($process->adapter)->toBe('null');
});

test('null adapter can complete a process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->complete($process, 'All done');

    expect($process->isRunning())->toBeFalse()
        ->and($process->result()->output)->toBe('All done');
});

test('null adapter can fail a process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');
    $process = $manager->spawn('Test');

    $adapter->fail($process, 'Oops');

    expect($process->isRunning())->toBeFalse();
    expect(fn () => $process->result())->toThrow(ProcessFailedException::class, 'Oops');
});

test('null adapter can terminate a process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->kill())->toBeTrue()
        ->and($process->isRunning())->toBeFalse();
});

test('null adapter provides output reader', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->output())->toBeInstanceOf(OutputReader::class);
});

test('null adapter output reader tail works', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->output()->tail())->toBe('');
});

test('null adapter reports capabilities', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);
    $adapter = $manager->adapter('null');

    expect($adapter->capabilities())->toHaveKey('testing', true);
});
