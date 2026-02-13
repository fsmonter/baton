<?php

use Baton\BatonManager;
use Baton\Drivers\NullDriver;
use Baton\Output\MessageEvent;

test('null driver spawns a process', function () {
    $driver = new NullDriver();
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $process = $manager->spawn('Test');

    expect($process->isRunning())->toBeTrue()
        ->and($process->driver)->toBe('null');
});

test('null driver can complete a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->complete($process, 'Result text');

    expect($process->isRunning())->toBeFalse()
        ->and($process->result()->success)->toBeTrue()
        ->and($process->result()->output)->toBe('Result text');
});

test('null driver can fail a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->fail($process, 'Boom');

    expect($process->isRunning())->toBeFalse()
        ->and($process->result()->success)->toBeFalse()
        ->and($process->result()->error)->toBe('Boom');
});

test('null driver can terminate a process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->terminate($process);

    expect($process->isRunning())->toBeFalse();
});

test('null driver provides output reader', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->addOutput($process, new MessageEvent(content: 'Hello'));
    $driver->addOutput($process, new MessageEvent(content: 'World'));

    $events = iterator_to_array($process->output()->events());

    expect($events)->toHaveCount(2)
        ->and($events[0]->content)->toBe('Hello')
        ->and($events[1]->content)->toBe('World');
});

test('null driver output reader tail works', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $driver = $manager->driver('null');
    $process = $manager->spawn('Test');

    $driver->addOutput($process, new MessageEvent(content: 'Line 1'));
    $driver->addOutput($process, new MessageEvent(content: 'Line 2'));

    expect($process->output()->tail())->toContain('Line 1')
        ->and($process->output()->tail())->toContain('Line 2');
});

test('null driver reports capabilities', function () {
    $driver = new NullDriver();

    expect($driver->capabilities())->toHaveKey('testing')
        ->and($driver->capabilities()['testing'])->toBeTrue();
});
