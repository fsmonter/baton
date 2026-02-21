<?php

use Baton\Baton;
use Baton\BatonManager;
use Baton\Adapters\NullAdapter;
use Baton\PendingProcess;
use Baton\Process;

test('manager resolves default adapter', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);

    expect($manager->adapter())->toBeInstanceOf(NullAdapter::class);
});

test('manager resolves named adapter', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);

    expect($manager->adapter('null'))->toBeInstanceOf(NullAdapter::class);
});

test('manager throws for unknown adapter', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);

    $manager->adapter('nonexistent');
})->throws(InvalidArgumentException::class);

test('manager supports extend', function () {
    $manager = new BatonManager(['default' => 'custom', 'adapters' => ['custom' => ['foo' => 'bar']]]);

    $manager->extend('custom', function (array $config) {
        expect($config)->toBe(['foo' => 'bar']);
        return new NullAdapter($config);
    });

    expect($manager->adapter('custom'))->toBeInstanceOf(NullAdapter::class);
});

test('manager prompt returns pending process', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);

    expect($manager->prompt('Test'))->toBeInstanceOf(PendingProcess::class);
});

test('manager spawn returns process directly', function () {
    $manager = new BatonManager(['default' => 'null', 'adapters' => ['null' => []]]);

    expect($manager->spawn('Test'))->toBeInstanceOf(Process::class);
});

test('facade resolves manager', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.adapters.null', []);

    expect(Baton::adapter('null'))->toBeInstanceOf(NullAdapter::class);
});

test('facade spawn works', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.adapters.null', []);

    $process = Baton::spawn('Test');

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->isRunning())->toBeTrue();
});

test('facade prompt builder works', function () {
    $this->app['config']->set('baton.default', 'null');
    $this->app['config']->set('baton.adapters.null', []);

    $process = Baton::prompt('Fix tests')
        ->model('opus')
        ->tools(['Read'])
        ->spawn();

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->isRunning())->toBeTrue();
});
