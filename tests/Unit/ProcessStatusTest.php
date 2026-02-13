<?php

use Baton\ProcessStatus;

test('process status has correct values', function () {
    expect(ProcessStatus::Running->value)->toBe('running');
    expect(ProcessStatus::Completed->value)->toBe('completed');
    expect(ProcessStatus::Failed->value)->toBe('failed');
});

test('process status can be created from value', function () {
    expect(ProcessStatus::from('running'))->toBe(ProcessStatus::Running);
    expect(ProcessStatus::from('completed'))->toBe(ProcessStatus::Completed);
    expect(ProcessStatus::from('failed'))->toBe(ProcessStatus::Failed);
});
