<?php

declare(strict_types=1);

use AgentSubprocess\Drivers\NullDriver;
use AgentSubprocess\Facades\Agent;
use AgentSubprocess\Models\AgentRun;

it('returns empty string when no process dto exists', function () {
    $run = AgentRun::create(['prompt' => 'Test']);

    expect($run->getLiveOutput())->toBe('');
});

it('returns live output through driver', function () {
    Agent::extend('test-live', function ($app, $config) {
        return new class extends NullDriver
        {
            private string $simulatedOutput = '';

            public function setSimulatedOutput(string $output): void
            {
                $this->simulatedOutput = $output;
            }

            public function getLiveOutput(\AgentSubprocess\Data\Process $process, int $lines = 50): string
            {
                return $this->simulatedOutput;
            }
        };
    });

    $driver = Agent::driver('test-live');
    $driver->setSimulatedOutput("Line 1\nLine 2\nLine 3");

    $run = AgentRun::create([
        'prompt' => 'Test',
        'driver' => 'test-live',
    ]);

    $process = $driver->spawn($run);
    $run->setProcessDto($process)->save();

    expect($run->getLiveOutput())->toBe("Line 1\nLine 2\nLine 3");
});

it('respects line count parameter', function () {
    Agent::extend('test-lines', function ($app, $config) {
        return new class extends NullDriver
        {
            private int $requestedLines = 0;

            public function getRequestedLines(): int
            {
                return $this->requestedLines;
            }

            public function getLiveOutput(\AgentSubprocess\Data\Process $process, int $lines = 50): string
            {
                $this->requestedLines = $lines;

                return 'output';
            }
        };
    });

    $driver = Agent::driver('test-lines');

    $run = AgentRun::create([
        'prompt' => 'Test',
        'driver' => 'test-lines',
    ]);

    $process = $driver->spawn($run);
    $run->setProcessDto($process)->save();

    $run->getLiveOutput(25);

    expect($driver->getRequestedLines())->toBe(25);
});
