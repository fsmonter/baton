<?php

declare(strict_types=1);

use AgentSubprocess\Enums\RunStatus;

it('identifies terminal statuses', function () {
    expect(RunStatus::Completed->isTerminal())->toBeTrue()
        ->and(RunStatus::Failed->isTerminal())->toBeTrue()
        ->and(RunStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(RunStatus::Pending->isTerminal())->toBeFalse()
        ->and(RunStatus::Queued->isTerminal())->toBeFalse()
        ->and(RunStatus::Running->isTerminal())->toBeFalse();
});

it('identifies active statuses', function () {
    expect(RunStatus::Queued->isActive())->toBeTrue()
        ->and(RunStatus::Running->isActive())->toBeTrue()
        ->and(RunStatus::Pending->isActive())->toBeFalse()
        ->and(RunStatus::Completed->isActive())->toBeFalse()
        ->and(RunStatus::Failed->isActive())->toBeFalse();
});
