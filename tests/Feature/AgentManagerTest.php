<?php

declare(strict_types=1);

use AgentSubprocess\Contracts\AgentDriver;
use AgentSubprocess\Drivers\ClaudeCodeDriver;
use AgentSubprocess\Drivers\NullDriver;
use AgentSubprocess\Facades\Agent;

it('resolves the default driver', function () {
    config(['agents.default' => 'claude-code']);

    $driver = Agent::driver();

    expect($driver)->toBeInstanceOf(ClaudeCodeDriver::class);
});

it('resolves a specific driver', function () {
    $driver = Agent::driver('null');

    expect($driver)->toBeInstanceOf(NullDriver::class);
});

it('allows extending with custom drivers', function () {
    Agent::extend('custom', function ($app, $config) {
        return new NullDriver($config);
    });

    $driver = Agent::driver('custom');

    expect($driver)->toBeInstanceOf(AgentDriver::class);
});

it('throws exception for unknown driver', function () {
    Agent::driver('unknown');
})->throws(InvalidArgumentException::class);
