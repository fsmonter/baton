<?php

use Baton\BatonManager;
use Baton\Contracts\ProcessStore;
use Baton\Events\ProcessCompleted;
use Baton\Events\ProcessFailed;
use Baton\ProcessMonitor;
use Baton\ProcessStatus;
use Baton\Store\FilesystemStore;
use Illuminate\Events\Dispatcher;

beforeEach(function () {
    $this->storeDir = sys_get_temp_dir().'/baton-monitor-test-'.uniqid();
    $this->store = new FilesystemStore($this->storeDir);
    $this->manager = new BatonManager(['default' => 'null', 'drivers' => ['null' => []]]);
    $this->manager->setStore($this->store);
});

afterEach(function () {
    if (is_dir($this->storeDir)) {
        array_map('unlink', glob($this->storeDir.'/*.json'));
        rmdir($this->storeDir);
    }
});

test('check returns running status for active process', function () {
    $process = $this->manager->spawn('Test');
    $monitor = new ProcessMonitor($this->manager, $this->store);

    $results = $monitor->check($process->id);

    expect($results[$process->id])->toBe(ProcessStatus::Running);
});

test('check returns completed status and removes from store', function () {
    $process = $this->manager->spawn('Test');
    $driver = $this->manager->driver('null');

    $driver->complete($process, 'Done');

    $monitor = new ProcessMonitor($this->manager, $this->store);
    $results = $monitor->check($process->id);

    expect($results[$process->id])->toBe(ProcessStatus::Completed)
        ->and($this->store->exists($process->id))->toBeFalse();
});

test('check returns failed status and removes from store', function () {
    $process = $this->manager->spawn('Test');
    $driver = $this->manager->driver('null');

    $driver->fail($process, 'Oops');

    $monitor = new ProcessMonitor($this->manager, $this->store);
    $results = $monitor->check($process->id);

    expect($results[$process->id])->toBe(ProcessStatus::Failed)
        ->and($this->store->exists($process->id))->toBeFalse();
});

test('check all processes', function () {
    $p1 = $this->manager->spawn('Test 1');
    $p2 = $this->manager->spawn('Test 2');
    $driver = $this->manager->driver('null');

    $driver->complete($p1, 'Done');
    // p2 still running

    $monitor = new ProcessMonitor($this->manager, $this->store);
    $results = $monitor->check();

    expect($results[$p1->id])->toBe(ProcessStatus::Completed)
        ->and($results[$p2->id])->toBe(ProcessStatus::Running)
        ->and($this->store->exists($p1->id))->toBeFalse()
        ->and($this->store->exists($p2->id))->toBeTrue();
});

test('check fires ProcessCompleted event', function () {
    $firedEvents = [];
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturnUsing(function ($event) use (&$firedEvents) {
        $firedEvents[] = $event;
    });

    $this->manager->setEventDispatcher($events);
    $process = $this->manager->spawn('Test');
    $driver = $this->manager->driver('null');
    $driver->complete($process, 'Done');

    // Reset fired events to only track monitor events
    $firedEvents = [];

    $monitor = new ProcessMonitor($this->manager, $this->store, $events);
    $monitor->check($process->id);

    expect($firedEvents)->toHaveCount(1)
        ->and($firedEvents[0])->toBeInstanceOf(ProcessCompleted::class)
        ->and($firedEvents[0]->process->id)->toBe($process->id);
});

test('check fires ProcessFailed event', function () {
    $firedEvents = [];
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturnUsing(function ($event) use (&$firedEvents) {
        $firedEvents[] = $event;
    });

    $this->manager->setEventDispatcher($events);
    $process = $this->manager->spawn('Test');
    $driver = $this->manager->driver('null');
    $driver->fail($process, 'Broke');

    $firedEvents = [];

    $monitor = new ProcessMonitor($this->manager, $this->store, $events);
    $monitor->check($process->id);

    expect($firedEvents)->toHaveCount(1)
        ->and($firedEvents[0])->toBeInstanceOf(ProcessFailed::class)
        ->and($firedEvents[0]->error)->toBe('Broke');
});

test('check returns empty array for unknown id', function () {
    $monitor = new ProcessMonitor($this->manager, $this->store);

    expect($monitor->check('nonexistent'))->toBe([]);
});
