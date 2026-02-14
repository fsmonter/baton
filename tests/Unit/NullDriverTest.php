<?php

use Baton\BatonManager;
use Baton\Contracts\OutputReader;
use Baton\Exceptions\ProcessFailedException;

test('null driver spawns a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->isRunning())->toBeTrue()
        ->and($process->driver)->toBe('null');
});

test('null driver can complete a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->complete($process, 'All done');

    expect($process->isRunning())->toBeFalse()
        ->and($process->result()->output)->toBe('All done');
});

test('null driver can fail a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->fail($process, 'Oops');

    expect($process->isRunning())->toBeFalse();
    expect(fn () => $process->result())->toThrow(ProcessFailedException::class, 'Oops');
});

test('null driver can terminate a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->kill())->toBeTrue()
        ->and($process->isRunning())->toBeFalse();
});

test('null driver provides output reader', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->output())->toBeInstanceOf(OutputReader::class);
});

test('null driver output reader tail works', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->output()->tail())->toBe('');
});

test('null driver reports capabilities', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');

    expect($driver->capabilities())->toHaveKey('testing', true);
});
