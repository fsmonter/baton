<?php

use Baton\Baton;
use Baton\BatonManager;
use Baton\Contracts\Driver;
use Baton\Drivers\NullDriver;
use Baton\PendingProcess;
use Baton\Process;

test('manager resolves default driver', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);

    expect($manager->driver())->toBeInstanceOf(NullDriver::class);
});

test('manager resolves named driver', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);

    expect($manager->driver('null'))->toBeInstanceOf(NullDriver::class);
});

test('manager throws for unknown driver', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);

    $manager->driver('nonexistent');
})->throws(InvalidArgumentException::class);

test('manager supports extend', function () {
    $manager = new BatonManager(['default' => 'custom', 'drivers' => ['custom' => ['foo' => 'bar']]]);

    $manager->extend('custom', function (array $config) {
        expect($config)->toBe(['foo' => 'bar']);
        return new NullDriver($config);
    });

    expect($manager->driver('custom'))->toBeInstanceOf(NullDriver::class);
});

test('manager prompt returns pending process', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);

    expect($manager->prompt('Test'))->toBeInstanceOf(PendingProcess::class);
});

test('manager spawn returns process directly', function () {
    $manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);

    expect($manager->spawn('Test'))->toBeInstanceOf(Process::class);
});

test('facade resolves manager', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.drivers.null', []);

    expect(Baton::driver('null'))->toBeInstanceOf(NullDriver::class);
});

test('facade spawn works', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.drivers.null', []);

    $process = Baton::spawn('Test');

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->isRunning())->toBeTrue();
});

test('facade prompt builder works', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.drivers.null', []);

    $process = Baton::prompt('Fix tests')
        ->model('opus')
        ->tools(['Read'])
        ->spawn();

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->isRunning())->toBeTrue();
});
