<?php

declare(strict_types=1);

use AgentSubprocess\Contracts\OutputParser;
use AgentSubprocess\Drivers\NullDriver;
use AgentSubprocess\Data\Process;
use AgentSubprocess\Data\TerminationResult;
use AgentSubprocess\Events\Output\MessageEvent;
use AgentSubprocess\Log\LogPosition;
use AgentSubprocess\Log\LogReadResult;
use AgentSubprocess\Models\AgentRun;

beforeEach(function () {
    $this->driver = new NullDriver;
});

it('spawns a simulated process', function () {
    $run = AgentRun::create(['prompt' => 'Test prompt']);

    $process = $this->driver->spawn($run);

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->pid)->toBeInt()
        ->and($process->driver)->toBe('null')
        ->and($process->command)->toContain('null-agent')
        ->and($process->meta['simulated'])->toBeTrue();
});

it('tracks running state of spawned processes', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    expect($this->driver->isRunning($process))->toBeTrue();
});

it('terminates a process', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    $result = $this->driver->terminate($process);

    expect($result)->toBeTrue()
        ->and($this->driver->isRunning($process))->toBeFalse();
});

it('terminates gracefully with simulated result', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    $result = $this->driver->terminateGracefully($process);

    expect($result)->toBeInstanceOf(TerminationResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->method)->toBe('simulated')
        ->and($this->driver->isRunning($process))->toBeFalse();
});

it('parses simulated output', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    $this->driver->simulateOutput($process, new MessageEvent('First message', 'assistant'));
    $this->driver->simulateOutput($process, new MessageEvent('Second message', 'assistant'));

    $events = iterator_to_array($this->driver->parseOutput($process));

    expect($events)->toHaveCount(2)
        ->and($events[0]->content)->toBe('First message')
        ->and($events[1]->content)->toBe('Second message');
});

it('simulates completion', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    $this->driver->simulateComplete($process, 'Task completed successfully');

    $events = iterator_to_array($this->driver->parseOutput($process));

    expect($events)->toHaveCount(1)
        ->and($events[0]->content)->toBe('Task completed successfully')
        ->and($this->driver->isRunning($process))->toBeFalse();
});

it('returns empty incremental log read', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);
    $position = new LogPosition;

    $result = $this->driver->readLogIncremental($process, $position);

    expect($result)->toBeInstanceOf(LogReadResult::class)
        ->and($result->hasEvents())->toBeFalse()
        ->and($result->position)->toBe($position);
});

it('returns empty live output', function () {
    $run = AgentRun::create(['prompt' => 'Test']);
    $process = $this->driver->spawn($run);

    $output = $this->driver->getLiveOutput($process, 50);

    expect($output)->toBe('');
});

it('provides an output parser', function () {
    $parser = $this->driver->getParser();

    expect($parser)->toBeInstanceOf(OutputParser::class);
});

it('reports testing capabilities', function () {
    $capabilities = $this->driver->getCapabilities();

    expect($capabilities)->toHaveKey('testing')
        ->and($capabilities['testing'])->toBeTrue()
        ->and($capabilities['streaming'])->toBeFalse()
        ->and($capabilities['tools'])->toBeFalse();
});

it('spawns multiple independent processes', function () {
    $run1 = AgentRun::create(['prompt' => 'First']);
    $run2 = AgentRun::create(['prompt' => 'Second']);

    $process1 = $this->driver->spawn($run1);
    $process2 = $this->driver->spawn($run2);

    expect($process1->pid)->not->toBe($process2->pid)
        ->and($this->driver->isRunning($process1))->toBeTrue()
        ->and($this->driver->isRunning($process2))->toBeTrue();

    $this->driver->terminate($process1);

    expect($this->driver->isRunning($process1))->toBeFalse()
        ->and($this->driver->isRunning($process2))->toBeTrue();
});

it('returns empty output for unknown process', function () {
    $unknownProcess = new Process(
        pid: 12345,
        driver: 'null',
        command: [],
        workDir: '/tmp',
    );

    $events = iterator_to_array($this->driver->parseOutput($unknownProcess));

    expect($events)->toBe([]);
});
